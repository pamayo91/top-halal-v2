<?php

namespace App\Services\WebEnrichment;

use App\Models\Restaurant;
use App\Models\RestaurantOpeningHour;
use App\Models\RestaurantWebEnrichment;
use Illuminate\Support\Facades\DB;

class RestaurantWebEnricher
{
    public function __construct(private RestaurantWebSourceProvider $provider) {}

    public function process(Restaurant $restaurant, bool $dryRun = false): array
    {
        $audit = $dryRun
            ? new RestaurantWebEnrichment(['restaurant_id'=>$restaurant->id, 'legacy_wp_id'=>$restaurant->legacy_wp_id])
            : RestaurantWebEnrichment::firstOrCreate(['restaurant_id'=>$restaurant->id], ['legacy_wp_id'=>$restaurant->legacy_wp_id]);
        $beforeHours = $this->hours($restaurant);
        $beforeDescription = $restaurant->description;
        $evidence = $this->provider->find($restaurant);
        $base = ['legacy_wp_id'=>$restaurant->legacy_wp_id, 'sources'=>$evidence['sources'] ?? [], 'matching'=>$evidence['matching'] ?? [], 'activity_status'=>$evidence['activity_status'] ?? null, 'facts'=>$evidence['facts'] ?? [], 'hours_before'=>$beforeHours, 'description_before'=>$beforeDescription, 'hours_after'=>null, 'description_after'=>null, 'hours_source'=>null, 'description_sources'=>[], 'closure_sources'=>[], 'confidence'=>is_numeric($evidence['confidence'] ?? null) ? (int) $evidence['confidence'] : null, 'confidence_level'=>$evidence['confidence_level'] ?? null, 'matching_confidence'=>$evidence['matching_confidence'] ?? null, 'activity_confidence'=>$evidence['activity_confidence'] ?? null, 'hours_confidence'=>$evidence['hours_confidence'] ?? null, 'description_confidence'=>$evidence['description_confidence'] ?? null, 'technical_error'=>null];

        if (($evidence['state'] ?? null) === 'unavailable') return $this->finish($audit, array_replace($base, ['status'=>'ERROR','reason'=>'Web source provider is not configured','technical_error'=>$evidence['reason'] ?? 'provider unavailable']), $dryRun);
        if (($evidence['state'] ?? null) === 'error') return $this->finish($audit, array_replace($base, ['status'=>'ERROR','reason'=>'Technical provider error','technical_error'=>$evidence['reason'] ?? 'unknown']), $dryRun);
        if (($evidence['state'] ?? null) !== 'matched') return $this->finish($audit, array_replace($base, ['status'=>'INSUFFICIENT_DATA','reason'=>$evidence['reason'] ?? 'No sufficiently reliable name and address match']), $dryRun);
        if (($evidence['closure'] ?? null) === 'confirmed') return $this->finish($audit, array_replace($base, ['status'=>'CLOSED_CONFIRMED_REVIEW','reason'=>'Matched establishment is permanently closed or the matching company is deregistered','closure_sources'=>$evidence['closure_sources'] ?? [],'confidence'=>$evidence['confidence'] ?? null]), $dryRun);
        if (($evidence['closure'] ?? null) === 'possible') return $this->finish($audit, array_replace($base, ['status'=>'CLOSED_POSSIBLE_REVIEW','reason'=>'Potential closure requires human confirmation','closure_sources'=>$evidence['closure_sources'] ?? [],'confidence'=>$evidence['confidence'] ?? null]), $dryRun);
        if (($evidence['closure'] ?? null) === 'conflict') return $this->finish($audit, array_replace($base, ['status'=>'CLOSURE_CONFLICT','reason'=>'Deregistered company conflicts with an active matched establishment','closure_sources'=>$evidence['closure_sources'] ?? [],'confidence'=>$evidence['confidence'] ?? null]), $dryRun);
        if (($evidence['conflict'] ?? false) === true) return $this->finish($audit, array_replace($base, ['status'=>'SOURCE_CONFLICT','reason'=>'Reliable sources disagree','confidence'=>$evidence['confidence'] ?? null]), $dryRun);
        if (($evidence['insufficient_data'] ?? false) === true) return $this->finish($audit, array_replace($base, ['status'=>'INSUFFICIENT_DATA','reason'=>$evidence['reason'] ?? 'Evidence is insufficient for a safe automatic change','confidence'=>$evidence['confidence'] ?? null]), $dryRun);

        $hours = $beforeHours === [] ? ($evidence['hours'] ?? []) : [];
        $description = $this->descriptionMayChange($beforeDescription) ? trim((string) ($evidence['description'] ?? '')) : null;
        if ($description !== null && !$this->validDescription($description)) $description = null;
        if ($hours === [] && $description === null) return $this->finish($audit, array_replace($base, ['status'=>'UNCHANGED','reason'=>'No safe eligible enrichment','confidence'=>$evidence['confidence'] ?? null]), $dryRun);
        $changes = array_replace($base, ['status'=>'UPDATED','reason'=>null,'confidence'=>$evidence['confidence'] ?? null,'hours_after'=>$hours ?: null,'description_after'=>$description,'hours_source'=>$hours ? ($evidence['hours_source'] ?? null) : null,'description_sources'=>$description ? ($evidence['description_sources'] ?? []) : []]);
        if (!$dryRun) DB::transaction(function () use ($restaurant, $hours, $description, $audit, $changes): void {
            if ($hours !== []) foreach ($hours as $hour) RestaurantOpeningHour::create(['restaurant_id'=>$restaurant->id, 'day'=>$hour['day'], 'opens_at'=>$hour['opens_at'], 'closes_at'=>$hour['closes_at'], 'is_closed'=>$hour['is_closed'], 'legacy_key'=>'web-enrichment:'.now()->format('YmdHis').':'.$hour['day'].':'.($hour['opens_at'] ?? 'closed')]);
            if ($description !== null) $restaurant->forceFill(['description'=>$description])->save();
            $this->persist($audit, $changes);
        });
        return $changes;
    }

    private function finish(RestaurantWebEnrichment $audit, array $values, bool $dryRun): array { if (!$dryRun) $this->persist($audit, $values); return $values; }
    private function persist(RestaurantWebEnrichment $audit, array $values): void { $audit->forceFill($values + ['processed_at'=>now(),'processing_started_at'=>null])->save(); }
    private function hours(Restaurant $restaurant): array { return $restaurant->openingHours()->orderBy('id')->get()->map(fn ($h) => ['day'=>$h->day,'opens_at'=>$this->time($h->opens_at),'closes_at'=>$this->time($h->closes_at),'is_closed'=>(bool) $h->is_closed])->all(); }
    private function time(mixed $value): ?string { return $value instanceof \DateTimeInterface ? $value->format('H:i:s') : ($value === null ? null : (string) $value); }
    private function descriptionMayChange(?string $description): bool { $text = mb_strtolower(trim((string) $description)); return $text === '' || str_starts_with($text, 'kebab frites de') || str_starts_with($text, 'description de votre restaurant unique'); }
    private function validDescription(string $text): bool { $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY); return count($sentences) >= 2 && count($sentences) <= 3 && mb_strlen($text) <= 700; }
}
