<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RestaurantOpeningStatus
{
    private const TIMEZONE = 'Europe/Paris';

    private const DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /**
     * @param Collection<int, mixed> $openingHours
     * @return array{day: string, message: string|null}
     */
    public function for(Collection $openingHours, ?CarbonImmutable $at = null): array
    {
        $now = ($at ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE);
        $today = strtolower($now->englishDayOfWeek);
        $hoursByDay = $openingHours->groupBy('day');

        if (! $this->hasCompleteDay($hoursByDay->get($today))) {
            return ['day' => $today, 'message' => null];
        }

        $current = $now->format('H:i');
        $todaySlots = $this->openSlots($hoursByDay->get($today));
        $previousDay = self::DAYS[(array_search($today, self::DAYS, true) + 6) % 7];
        $previousSlots = $hoursByDay->get($previousDay);

        if ($this->hasCompleteDay($previousSlots)) {
            foreach ($this->openSlots($previousSlots) as $slot) {
                if ($slot['closes_at'] < $slot['opens_at'] && $current < $slot['closes_at']) {
                    return ['day' => $today, 'message' => 'Ouvert actuellement · Ferme à '.$slot['closes_at']];
                }
            }
        }

        foreach ($todaySlots as $slot) {
            if ($slot['closes_at'] > $slot['opens_at'] && $current >= $slot['opens_at'] && $current < $slot['closes_at']) {
                return ['day' => $today, 'message' => 'Ouvert actuellement · Ferme à '.$slot['closes_at']];
            }

            if ($slot['closes_at'] < $slot['opens_at'] && $current >= $slot['opens_at']) {
                return ['day' => $today, 'message' => 'Ouvert actuellement · Ferme à '.$slot['closes_at']];
            }
        }

        foreach ($todaySlots as $slot) {
            if ($current < $slot['opens_at']) {
                return ['day' => $today, 'message' => 'Fermé actuellement · Ouvre à '.$slot['opens_at']];
            }
        }

        if ($todaySlots === []) {
            return ['day' => $today, 'message' => 'Fermé aujourd’hui'];
        }

        $tomorrow = self::DAYS[(array_search($today, self::DAYS, true) + 1) % 7];
        $tomorrowSlots = $hoursByDay->get($tomorrow);
        if ($this->hasCompleteDay($tomorrowSlots) && ($firstSlot = $this->openSlots($tomorrowSlots)[0] ?? null)) {
            return ['day' => $today, 'message' => 'Fermé actuellement · Ouvre demain à '.$firstSlot['opens_at']];
        }

        return ['day' => $today, 'message' => null];
    }

    /** @param Collection<int, mixed>|null $hours */
    private function hasCompleteDay(?Collection $hours): bool
    {
        if ($hours === null || $hours->isEmpty()) {
            return false;
        }

        if ($hours->contains(fn ($hour) => (bool) $hour->is_closed)) {
            return $hours->every(fn ($hour) => (bool) $hour->is_closed);
        }

        return $hours->every(fn ($hour) => ! $hour->is_closed && $this->time($hour->opens_at) !== null && $this->time($hour->closes_at) !== null);
    }

    /** @param Collection<int, mixed>|null $hours
     * @return list<array{opens_at: string, closes_at: string}>
     */
    private function openSlots(?Collection $hours): array
    {
        if ($hours === null) {
            return [];
        }

        return $hours
            ->reject(fn ($hour) => (bool) $hour->is_closed)
            ->map(fn ($hour) => ['opens_at' => $this->time($hour->opens_at), 'closes_at' => $this->time($hour->closes_at), 'slot' => $hour->slot ?? 1])
            ->filter(fn (array $slot) => $slot['opens_at'] !== null && $slot['closes_at'] !== null)
            ->sortBy([['slot', 'asc'], ['opens_at', 'asc']])
            ->values()
            ->map(fn (array $slot) => ['opens_at' => $slot['opens_at'], 'closes_at' => $slot['closes_at']])
            ->all();
    }

    private function time(mixed $time): ?string
    {
        if ($time === null || ! preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d/', (string) $time, $matches)) {
            return null;
        }

        return $matches[0];
    }
}
