<?php

namespace App\Filament\Pages;

use App\Models\{Category, CityServiceSeoPage, CitySpecialtySeoPage, Feature, Restaurant};
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
    public string $facetType = 'specialty';
    public string $cityCode = '';
    public ?int $termId = null;
    public string $cityName = '';
    public string $facetName = '';

    public function mount(?string $city = null, ?int $specialty = null, ?int $service = null, ?string $type = null): void
    {
        $type ??= request()->query('type');
        $this->facetType = $type === 'service' || $service !== null ? 'service' : 'specialty';
        $this->data = $this->defaults();
        $city ??= request()->query('city');
        $term = $this->facetType === 'service' ? ($service ?? request()->integer('service')) : ($specialty ?? request()->integer('specialty'));
        if (is_string($city) && $city !== '' && $term > 0) $this->selectFacet($city, $term);
    }

    public function updatedFacetType(): void
    {
        $this->facetType = $this->facetType === 'service' ? 'service' : 'specialty';
        $this->cityCode = $this->cityName = $this->facetName = ''; $this->termId = null;
        $this->form->fill($this->defaults()); $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $label = $this->facetType === 'service' ? 'Service' : 'Spécialité'; $config = $this->configTable(); $terms = $this->termTable();
        return $table->query($this->facetQuery())->columns([
            TextColumn::make('facet_type')->label('Type')->formatStateUsing(fn (): string => $label),
            TextColumn::make('city_name')->label('Ville')->searchable(query: fn (Builder $q, string $s): Builder => $q->where('restaurants.city_name', 'like', '%'.$s.'%'))->sortable(query: fn (Builder $q, string $d): Builder => $q->orderByRaw('MIN(restaurants.city_name) '.$d)),
            TextColumn::make('term_name')->label($label)->searchable(query: fn (Builder $q, string $s): Builder => $q->where($terms.'.name', 'like', '%'.$s.'%'))->sortable(),
            TextColumn::make('restaurants_count')->label('Nombre de restaurants')->numeric()->sortable(),
            BadgeColumn::make('facet_state')->label('État')->state(fn (Restaurant $r): string => $r->facet_state === 'open' ? 'Ouverte' : 'Fermée')->color(fn (string $state): string => $state === 'Ouverte' ? 'success' : 'gray')->url(fn (Restaurant $r): ?string => $r->facet_state === 'open' ? $this->publicUrl($r) : null)->openUrlInNewTab(),
            BadgeColumn::make('has_content')->label('Contenu personnalisé')->state(fn (Restaurant $r): string => filled($r->content_top) || filled($r->content_bottom) ? 'Oui' : 'Non')->color(fn (string $state): string => $state === 'Oui' ? 'success' : 'gray'),
        ])->filters([
            SelectFilter::make('term')->label($label)->options(fn (): array => $this->termModel()::query()->orderBy('name')->pluck('name', 'id')->all())->query(fn (Builder $q, array $data): Builder => filled($data['value'] ?? null) ? $q->where($terms.'.id', $data['value']) : $q),
            SelectFilter::make('state')->label('État')->options(['open' => 'Ouverte', 'closed' => 'Fermée'])->query(fn (Builder $q, array $data): Builder => match ($data['value'] ?? null) {'open' => $q->where($config.'.state', 'open'), 'closed' => $q->where(fn (Builder $s) => $s->whereNull($config.'.state')->orWhere($config.'.state', 'closed')), default => $q}),
        ])->recordActions([Action::make('edit')->label('Modifier')->url(fn (Restaurant $r): string => static::getUrl($this->editorParameters($r)).'#facet-seo-editor')])->defaultSort('restaurants_count', 'desc')->paginated([25, 50]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('state')->label('État')->options(['closed' => 'Fermée', 'open' => 'Ouverte'])->required(), TextInput::make('h1')->label('H1 personnalisé')->maxLength(255), TextInput::make('seo_title')->label('Title personnalisé')->maxLength(255), Textarea::make('seo_description')->label('Meta description personnalisée')->rows(3)->maxLength(500), RichEditor::make('content_top')->label('Contenu haut'), RichEditor::make('content_bottom')->label('Contenu bas')])->statePath('data');
    }

    public function selectFacet(string $cityCode, int $termId): void
    {
        $cities = app(CityPageResolver::class); $cities->forget(); $city = $cities->cityForCode($cityCode); $term = $this->termModel()::find($termId);
        if ($city === null || $term === null) return;
        $this->cityCode = $city->city_code; $this->termId = $term->id; $this->cityName = $city->city_name; $this->facetName = $term->name;
        $config = $this->configModel()::firstWhere(['city_code' => $this->cityCode, $this->foreignKey() => $this->termId]);
        $this->form->fill(['state' => $config?->state ?? 'closed', 'h1' => $config?->h1 ?? '', 'seo_title' => $config?->seo_title ?? '', 'seo_description' => $config?->seo_description ?? '', 'content_top' => $config?->content_top ?? '', 'content_bottom' => $config?->content_bottom ?? '']);
    }

    public function save(): void
    {
        if ($this->cityCode === '' || $this->termId === null) return;
        $data = $this->form->getState();
        foreach (['content_top', 'content_bottom'] as $key) { $html = app(ContentSanitizer::class)->sanitize($data[$key] ?? '')['html']; $data[$key] = filled(trim(strip_tags($html))) ? $html : null; }
        foreach (['h1', 'seo_title', 'seo_description'] as $key) $data[$key] = filled($data[$key] ?? null) ? $data[$key] : null;
        $keys = ['city_code' => $this->cityCode, $this->foreignKey() => $this->termId]; $empty = $data['state'] === 'closed' && collect(['h1', 'seo_title', 'seo_description', 'content_top', 'content_bottom'])->every(fn (string $key): bool => ! filled($data[$key]));
        if ($empty) $this->configModel()::query()->where($keys)->delete(); else $this->configModel()::updateOrCreate($keys, $data);
        app(AdminAudit::class)->record('city_'.$this->facetType.'_seo.updated', 'city_'.$this->facetType.'_seo', $keys); Notification::make()->title('Facette SEO enregistrée')->success()->send(); $this->resetTable();
    }

    private function facetQuery(): Builder
    {
        $city = $this->canonicalCityCodeSql(); $term = $this->termTable(); $config = $this->configTable(); $pivot = $this->pivotTable(); $key = $this->foreignKey();
        return Restaurant::query()->join($pivot, $pivot.'.restaurant_id', '=', 'restaurants.id')->join($term, $term.'.id', '=', $pivot.'.'.$key)->leftJoin($config, fn (JoinClause $j) => $j->on($config.'.city_code', '=', DB::raw($city))->on($config.'.'.$key, '=', $term.'.id'))->where('restaurants.status', 'published')->whereNotNull('restaurants.city_code')->where('restaurants.city_code', '!=', '')->selectRaw("MIN(restaurants.id) as id, '{$this->facetType}' as facet_type, MIN(restaurants.city_name) as city_name, {$city} as city_code, {$term}.id as term_id, {$term}.name as term_name, {$term}.slug as term_slug, COUNT(*) as restaurants_count, {$config}.state as facet_state, {$config}.content_top, {$config}.content_bottom")->groupByRaw("{$city}, {$term}.id, {$term}.name, {$term}.slug, {$config}.state, {$config}.content_top, {$config}.content_bottom");
    }

    private function configModel(): string { return $this->facetType === 'service' ? CityServiceSeoPage::class : CitySpecialtySeoPage::class; } private function termModel(): string { return $this->facetType === 'service' ? Feature::class : Category::class; } private function configTable(): string { return $this->facetType === 'service' ? 'city_service_seo_pages' : 'city_specialty_seo_pages'; } private function termTable(): string { return $this->facetType === 'service' ? 'features' : 'categories'; } private function pivotTable(): string { return $this->facetType === 'service' ? 'restaurant_feature' : 'restaurant_category'; } private function foreignKey(): string { return $this->facetType === 'service' ? 'feature_id' : 'category_id'; } private function defaults(): array { return ['state' => 'closed', 'h1' => '', 'seo_title' => '', 'seo_description' => '', 'content_top' => '', 'content_bottom' => '']; }
    private function editorParameters(Restaurant $r): array { return $this->facetType === 'service' ? ['type' => 'service', 'city' => $r->city_code, 'service' => $r->term_id] : ['city' => $r->city_code, 'specialty' => $r->term_id]; } private function publicUrl(Restaurant $r): ?string { $city = app(CityPageResolver::class)->cityForCode($r->city_code); return $city === null ? null : route('city-specialties.show', ['city' => $city->slug, 'facet' => $r->term_slug]); }
    private function canonicalCityCodeSql(): string { $p=implode(', ',array_map(fn(int $n):string=>"'751".str_pad((string)$n,2,'0',STR_PAD_LEFT)."'",range(1,20)));$l=implode(', ',array_map(fn(int $n):string=>"'6938{$n}'",range(1,9)));$m=implode(', ',array_map(fn(int $n):string=>"'132".str_pad((string)$n,2,'0',STR_PAD_LEFT)."'",range(1,16)));return "CASE WHEN restaurants.city_code IN ('75056', {$p}) THEN '75056' WHEN restaurants.city_code IN ('69123', {$l}) THEN '69123' WHEN restaurants.city_code IN ('13055', {$m}) THEN '13055' ELSE restaurants.city_code END"; }
}
