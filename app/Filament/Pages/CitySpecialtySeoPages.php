<?php

namespace App\Filament\Pages;

use App\Models\{Category, CitySpecialtySeoPage, Restaurant};
use App\Services\{AdminAudit, CityPageResolver, ContentSanitizer};
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
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class CitySpecialtySeoPages extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'facettes-seo';
    protected static ?string $title = 'Facettes SEO';
    protected static ?string $navigationLabel = 'Facettes SEO';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static string|\UnitEnum|null $navigationGroup = 'Contenu';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.pages.city-specialty-seo-pages';

    public ?array $data = [];
    public string $cityCode = '';
    public ?int $categoryId = null;
    public string $cityName = '';
    public string $specialtyName = '';

    public function mount(?string $city = null, ?int $specialty = null): void
    {
        $this->data = $this->defaultFormData();
        $city ??= request()->query('city');
        $specialty ??= request()->integer('specialty') ?: null;
        if (is_string($city) && $city !== '' && $specialty !== null) {
            $this->selectFacet($city, $specialty);
        }
    }

    public function table(Table $table): Table
    {
        return $table->query($this->facetQuery())->columns([
            TextColumn::make('city_name')->label('Ville')->searchable(query: fn (Builder $query, string $search): Builder => $query->where('restaurants.city_name', 'like', '%'.$search.'%'))->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('MIN(restaurants.city_name) '.$direction)),
            TextColumn::make('category_name')->label('Spécialité')->searchable(query: fn (Builder $query, string $search): Builder => $query->where('categories.name', 'like', '%'.$search.'%'))->sortable(),
            TextColumn::make('restaurants_count')->label('Nombre de restaurants')->numeric()->sortable(),
            BadgeColumn::make('facet_state')->label('État')->state(fn (Restaurant $record): string => $record->facet_state === 'open' ? 'Ouverte' : 'Fermée')->color(fn (string $state): string => $state === 'Ouverte' ? 'success' : 'gray'),
            BadgeColumn::make('has_content')->label('Contenu personnalisé')->state(fn (Restaurant $record): string => filled($record->content_top) || filled($record->content_bottom) ? 'Oui' : 'Non')->color(fn (string $state): string => $state === 'Oui' ? 'success' : 'gray'),
        ])->filters([
            SelectFilter::make('specialty')->label('Spécialité')->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->where('categories.id', $data['value']) : $query),
            SelectFilter::make('state')->label('État')->options(['open' => 'Ouverte', 'closed' => 'Fermée'])->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                'open' => $query->where('city_specialty_seo_pages.state', 'open'),
                'closed' => $query->where(fn (Builder $states) => $states->whereNull('city_specialty_seo_pages.state')->orWhere('city_specialty_seo_pages.state', 'closed')),
                default => $query,
            }),
        ])->recordActions([
            Action::make('edit')->label('Modifier')->url(fn (Restaurant $record): string => static::getUrl(['city' => $record->city_code, 'specialty' => $record->category_id]).'#facet-seo-editor'),
        ])->defaultSort('restaurants_count', 'desc')->paginated([25, 50]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('state')->label('État')->options(['closed' => 'Fermée', 'open' => 'Ouverte'])->required(),
            TextInput::make('h1')->label('H1 personnalisé')->maxLength(255),
            TextInput::make('seo_title')->label('Title personnalisé')->maxLength(255),
            Textarea::make('seo_description')->label('Meta description personnalisée')->rows(3)->maxLength(500),
            RichEditor::make('content_top')->label('Contenu haut'),
            RichEditor::make('content_bottom')->label('Contenu bas'),
        ])->statePath('data');
    }

    public function selectFacet(string $cityCode, int $categoryId): void
    {
        $city = app(CityPageResolver::class)->cityForCode($cityCode);
        $category = Category::find($categoryId);
        if ($city === null || $category === null) return;

        $this->cityCode = $city->city_code;
        $this->categoryId = $category->id;
        $this->cityName = $city->city_name;
        $this->specialtyName = $category->name;
        $config = CitySpecialtySeoPage::firstWhere(['city_code' => $this->cityCode, 'category_id' => $this->categoryId]);
        $this->form->fill([
            'state' => $config?->state ?? 'closed',
            'h1' => $config?->h1 ?? '',
            'seo_title' => $config?->seo_title ?? '',
            'seo_description' => $config?->seo_description ?? '',
            'content_top' => $config?->content_top ?? '',
            'content_bottom' => $config?->content_bottom ?? '',
        ]);
    }

    public function save(): void
    {
        if ($this->cityCode === '' || $this->categoryId === null) return;
        $data = $this->form->getState();
        foreach (['content_top', 'content_bottom'] as $key) $data[$key] = app(ContentSanitizer::class)->sanitize($data[$key] ?? '')['html'];
        foreach (['h1', 'seo_title', 'seo_description'] as $key) $data[$key] = filled($data[$key] ?? null) ? $data[$key] : null;

        $isDefaultClosed = $data['state'] === 'closed' && collect(['h1', 'seo_title', 'seo_description', 'content_top', 'content_bottom'])->every(fn (string $key): bool => ! filled($data[$key]));
        if ($isDefaultClosed) {
            CitySpecialtySeoPage::query()->where('city_code', $this->cityCode)->where('category_id', $this->categoryId)->delete();
        } else {
            CitySpecialtySeoPage::updateOrCreate(['city_code' => $this->cityCode, 'category_id' => $this->categoryId], $data);
        }
        app(AdminAudit::class)->record('city_specialty_seo.updated', 'city_specialty_seo', ['city_code' => $this->cityCode, 'category_id' => $this->categoryId]);
        Notification::make()->title('Facette SEO enregistrée')->success()->send();
        $this->resetTable();
    }

    private function facetQuery(): Builder
    {
        $cityCode = $this->canonicalCityCodeSql();
        return Restaurant::query()
            ->join('restaurant_category', 'restaurant_category.restaurant_id', '=', 'restaurants.id')
            ->join('categories', 'categories.id', '=', 'restaurant_category.category_id')
            ->leftJoin('city_specialty_seo_pages', fn (JoinClause $join) => $join->on('city_specialty_seo_pages.city_code', '=', DB::raw($cityCode))->on('city_specialty_seo_pages.category_id', '=', 'categories.id'))
            ->where('restaurants.status', 'published')->whereNotNull('restaurants.city_code')->where('restaurants.city_code', '!=', '')
            ->selectRaw("MIN(restaurants.id) as id, MIN(restaurants.city_name) as city_name, {$cityCode} as city_code, categories.id as category_id, categories.name as category_name, COUNT(*) as restaurants_count, city_specialty_seo_pages.state as facet_state, city_specialty_seo_pages.content_top, city_specialty_seo_pages.content_bottom")
            ->groupByRaw("{$cityCode}, categories.id, categories.name, city_specialty_seo_pages.state, city_specialty_seo_pages.content_top, city_specialty_seo_pages.content_bottom");
    }

    private function defaultFormData(): array { return ['state' => 'closed', 'h1' => '', 'seo_title' => '', 'seo_description' => '', 'content_top' => '', 'content_bottom' => '']; }
    private function canonicalCityCodeSql(): string { $paris=implode(', ',array_map(fn(int $n):string=>"'751".str_pad((string)$n,2,'0',STR_PAD_LEFT)."'",range(1,20)));$lyon=implode(', ',array_map(fn(int $n):string=>"'6938{$n}'",range(1,9)));$marseille=implode(', ',array_map(fn(int $n):string=>"'132".str_pad((string)$n,2,'0',STR_PAD_LEFT)."'",range(1,16)));return "CASE WHEN restaurants.city_code IN ('75056', {$paris}) THEN '75056' WHEN restaurants.city_code IN ('69123', {$lyon}) THEN '69123' WHEN restaurants.city_code IN ('13055', {$marseille}) THEN '13055' ELSE restaurants.city_code END"; }
}
