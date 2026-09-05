<?php

namespace App\Filament\Pages;

use App\Models\{CitySeoPage, Restaurant, Setting};
use App\Services\{AdminAudit, CitySeoService, ContentSanitizer};
use Filament\Actions\Action;
use Filament\Forms\Components\{RichEditor, Select, TextInput, Textarea};
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{BadgeColumn, TextColumn};
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CitySeoPages extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'pages-villes-seo';
    protected static ?string $title = 'Pages villes SEO';
    protected static ?string $navigationLabel = 'Pages villes SEO';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';
    protected static string|\UnitEnum|null $navigationGroup = 'Contenu';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.city-seo-pages';
    public ?array $data = [];
    public string $cityName = '';

    public function mount(?string $city = null): void { $this->data = $this->defaultFormData(); $city ??= request()->query('city'); if (is_string($city) && $city !== '') $this->selectCity($city); }

    public function table(Table $table): Table
    {
        return $table->query($this->cityQuery())->columns([
            TextColumn::make('city_name')->label('Ville')
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('restaurants.city_name', 'like', '%'.$search.'%'))
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('restaurants.city_name', $direction)),
            TextColumn::make('city_slug')->label('Slug')->state(fn (Restaurant $record) => Str::slug($record->city_name))->sortable(),
            TextColumn::make('restaurants_count')->label('Nombre de restaurants')->numeric()->sortable(),
            BadgeColumn::make('seo_state')->label('État SEO')->state(fn (Restaurant $record) => $this->seoState($record))->color(fn (string $state) => str_contains($state, 'ouverte') ? 'success' : 'gray'),
            BadgeColumn::make('has_content')->label('Contenu personnalisé')->state(fn (Restaurant $record) => filled($record->content_top) || filled($record->content_bottom) ? 'Oui' : 'Non')->color(fn (string $state) => $state === 'Oui' ? 'success' : 'gray'),
        ])->filters([
            SelectFilter::make('seo_state')->label('État SEO')->options(['auto_open'=>'Auto ouverte','auto_closed'=>'Auto fermée','forced_open'=>'Forcée ouverte','forced_closed'=>'Forcée fermée'])->query(function (Builder $query, array $data): Builder {
                $value = $data['value'] ?? null; $threshold = app(CitySeoService::class)->threshold();
                return match ($value) {
                    'forced_open', 'forced_closed' => $query->having('city_state', $value),
                    'auto_open' => $query->havingRaw("(city_state is null or city_state = 'auto') and count(*) >= ?", [$threshold]),
                    'auto_closed' => $query->havingRaw("(city_state is null or city_state = 'auto') and count(*) < ?", [$threshold]),
                    default => $query,
                };
            }),
        ])->recordActions([Action::make('edit')->label('Modifier')->url(fn (Restaurant $record): string => static::getUrl(['city' => $record->city_name]))])
            ->defaultSort('restaurants_count', 'desc')->paginated([25, 50]);
    }

    private function cityQuery(): Builder
    {
        return Restaurant::query()->where('restaurants.status', 'published')->whereNotNull('restaurants.city_name')->where('restaurants.city_name', '!=', '')
            ->leftJoin('city_seo_pages', 'city_seo_pages.city_name', '=', 'restaurants.city_name')
            ->selectRaw('MIN(restaurants.id) as id, restaurants.city_name, COUNT(*) as restaurants_count, city_seo_pages.state as city_state, city_seo_pages.content_top, city_seo_pages.content_bottom')
            ->groupBy('restaurants.city_name', 'city_seo_pages.state', 'city_seo_pages.content_top', 'city_seo_pages.content_bottom');
    }

    private function seoState(Restaurant $record): string { return match ($record->city_state) {'forced_open'=>'Forcée ouverte','forced_closed'=>'Forcée fermée',default=>$record->restaurants_count >= app(CitySeoService::class)->threshold() ? 'Auto ouverte' : 'Auto fermée'}; }
    public function form(Schema $schema): Schema { return $schema->components([TextInput::make('threshold')->label('Nombre minimum de restaurants pour ouvrir automatiquement une page ville')->integer()->minValue(1)->required(),Select::make('state')->label('État')->options(['auto'=>'Automatique','forced_open'=>'Forcée ouverte','forced_closed'=>'Forcée fermée'])->required(),TextInput::make('h1')->label('H1 personnalisé')->maxLength(255),TextInput::make('seo_title')->label('Title personnalisé')->maxLength(255),Textarea::make('seo_description')->label('Meta description personnalisée')->rows(3)->maxLength(500),RichEditor::make('content_top')->label('Contenu haut'),RichEditor::make('content_bottom')->label('Contenu bas')])->statePath('data'); }
    public function selectCity(string $city): void { $this->cityName=$city;$config=CitySeoPage::firstWhere('city_name',$city);$this->form->fill([...$this->defaultFormData(),'state'=>$config?->state??'auto','h1'=>$config?->h1??'','seo_title'=>$config?->seo_title??'','seo_description'=>$config?->seo_description??'','content_top'=>$config?->content_top??'','content_bottom'=>$config?->content_bottom??'']); }
    public function save(): void { $data=$this->form->getState();Setting::updateOrCreate(['key'=>'city_seo_minimum_restaurants'],['value'=>['value'=>(int)$data['threshold']],'group'=>'seo']);if($this->cityName!==''){foreach(['content_top','content_bottom'] as $key)$data[$key]=app(ContentSanitizer::class)->sanitize($data[$key]??'')['html'];unset($data['threshold']);CitySeoPage::updateOrCreate(['city_name'=>$this->cityName],$data);}app(CitySeoService::class)->forget();app(AdminAudit::class)->record('city_seo.updated','city_seo',['city_name' => $this->cityName]);Notification::make()->title('Réglages enregistrés')->success()->send();$this->resetTable(); }

    private function defaultFormData(): array { return ['threshold' => app(CitySeoService::class)->threshold(), 'state' => 'auto', 'h1' => '', 'seo_title' => '', 'seo_description' => '', 'content_top' => '', 'content_bottom' => '']; }
}
