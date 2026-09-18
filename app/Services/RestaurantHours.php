<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantHours
{
    public const DAYS = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ];

    /** @return array<int, array<string, mixed>> */
    public function editorState(Collection $hours): array
    {
        $dayOrder = array_flip(array_keys(self::DAYS));
        $byDay = $hours->sortBy(fn ($hour): string => sprintf('%02d-%03d', $dayOrder[$hour->day] ?? 99, $hour->slot ?? 1))->groupBy('day');

        return collect(self::DAYS)->map(function (string $label, string $day) use ($byDay): array {
            $entries = $byDay->get($day, collect());

            if ($entries->isEmpty() || $entries->every(fn ($entry): bool => (bool) $entry->is_closed)) {
                return ['day' => $day, 'status' => 'closed', 'hour_ids' => $entries->pluck('id')->all(), 'slots' => []];
            }

            if ($entries->every(fn ($entry): bool => (bool) $entry->is_open_24_hours)) {
                return ['day' => $day, 'status' => 'all_day', 'hour_ids' => $entries->pluck('id')->all(), 'slots' => []];
            }

            return [
                'day' => $day,
                'status' => 'slots',
                'hour_ids' => [],
                'slots' => $entries->reject(fn ($entry): bool => (bool) $entry->is_closed || (bool) $entry->is_open_24_hours)
                    ->values()
                    ->map(fn ($entry): array => ['id' => $entry->id, 'opens_at' => $this->time($entry->opens_at), 'closes_at' => $this->time($entry->closes_at)])
                    ->all(),
            ];
        })->values()->all();
    }

    public function sync(Restaurant $restaurant, array $input): void
    {
        $rows = $this->validatedEditorRows($input);

        DB::transaction(function () use ($restaurant, $rows): void {
            $existing = $restaurant->openingHours()->get()->keyBy('id');
            $requestedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

            if (count($requestedIds) !== count(array_unique($requestedIds)) || collect($requestedIds)->contains(fn (int $id): bool => ! $existing->has($id))) {
                throw ValidationException::withMessages(['hours' => 'Un créneau d’horaires est invalide. Rechargez la fiche avant de l’enregistrer.']);
            }

            $restaurant->openingHours()->whereNotIn('id', $requestedIds)->delete();

            foreach ($rows as $row) {
                $id = $row['id'] ?? null;
                unset($row['id']);
                if ($id) {
                    unset($row['legacy_key']);
                    $existing[$id]->update($row);
                } else {
                    $restaurant->openingHours()->create($row);
                }
            }
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function validatedEditorRows(array $input, string $source = 'admin'): array
    {
        return $this->rows($input, $source);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(array $input, string $source): array
    {
        $errors = [];
        $days = [];
        $rows = [];

        foreach ($input as $index => $entry) {
            $path = "hours.$index";
            $day = $entry['day'] ?? null;
            $status = $entry['status'] ?? null;
            if (! isset(self::DAYS[$day])) {
                $errors["$path.day"] = 'Choisissez un jour.';
                continue;
            }
            if (isset($days[$day])) {
                $errors["$path.day"] = 'Chaque jour ne peut être renseigné qu’une seule fois.';
                continue;
            }
            $days[$day] = true;
            if (! in_array($status, ['closed', 'all_day', 'slots'], true)) {
                $errors["$path.status"] = 'Choisissez le statut du jour.';
                continue;
            }

            $ids = $entry['hour_ids'] ?? [];
            if ($status === 'closed') {
                $rows[] = $this->row($entry['id'] ?? ($ids[0] ?? null), $day, 1, true, false, null, null, $source);
                continue;
            }
            if ($status === 'all_day') {
                $rows[] = $this->row($entry['id'] ?? ($ids[0] ?? null), $day, 1, false, true, '00:00', '23:59', $source);
                continue;
            }

            $slots = $entry['slots'] ?? [];
            if ($slots === []) {
                $errors["$path.slots"] = 'Ajoutez au moins une plage horaire.';
                continue;
            }
            $previousClose = null;
            $slotNumber = 0;
            foreach ($slots as $slotIndex => $slot) {
                $slotNumber++;
                $open = $this->time($slot['opens_at'] ?? null);
                $close = $this->time($slot['closes_at'] ?? null);
                if (! $open || ! $close) {
                    $errors["$path.slots.$slotIndex.opens_at"] = 'Indiquez les deux heures de la plage.';
                    continue;
                }
                if ($close <= $open) {
                    $errors["$path.slots.$slotIndex.closes_at"] = 'La fermeture doit être postérieure à l’ouverture.';
                    continue;
                }
                if ($previousClose !== null && $open <= $previousClose) {
                    $errors["$path.slots.$slotIndex.opens_at"] = 'Chaque plage doit commencer après la précédente.';
                    continue;
                }
                $previousClose = $close;
                $rows[] = $this->row($slot['id'] ?? null, $day, $slotNumber, false, false, $open, $close, $source);
            }
        }

        foreach (array_keys(self::DAYS) as $day) {
            if (! isset($days[$day])) $errors['hours'] = 'Renseignez les horaires des sept jours.';
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);

        return $rows;
    }

    /** @return array<string, mixed> */
    private function row(mixed $id, string $day, int $slot, bool $closed, bool $allDay, ?string $opensAt, ?string $closesAt, string $source): array
    {
        return array_filter([
            'id' => $id ? (int) $id : null,
            'day' => $day,
            'slot' => $slot,
            'is_closed' => $closed,
            'is_open_24_hours' => $allDay,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'legacy_key' => $id ? null : "$source:$day:$slot",
        ], fn (mixed $value, string $key): bool => $key !== 'id' || $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    private function time(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $value = substr((string) $value, 0, 5);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : null;
    }
}
