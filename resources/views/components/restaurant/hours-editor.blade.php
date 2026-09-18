@props([
    'hours' => [],
])

@php
    $states = [];
    foreach ((array) old('hours', $hours) as $key => $entry) {
        if (! is_array($entry)) continue;
        $day = $entry['day'] ?? (is_string($key) ? $key : null);
        if ($day) $states[$day] = $entry + ['day' => $day];
    }
    $days = \App\Services\RestaurantHours::DAYS;
@endphp

<fieldset class="hours-fieldset" data-hours-editor data-hours-max-slots="8">
    <legend>Horaires</legend>
    <p class="form-help">Ajoutez une ou plusieurs plages par jour si nécessaire, par exemple pour le midi et le soir.</p>
    @if($errors->has('hours') || $errors->has('hours.*'))<p class="field-error" role="alert">Vérifiez les horaires indiqués.</p>@endif
    @foreach($days as $day => $label)
        @php
            $entry = $states[$day] ?? ['day' => $day, 'status' => 'closed', 'hour_ids' => [], 'slots' => []];
            $status = $entry['status'] ?? 'closed';
            $slots = array_values($entry['slots'] ?? []);
            if ($status === 'slots' && $slots === []) $slots = [['opens_at' => null, 'closes_at' => null]];
        @endphp
        <div class="hours-day" data-hours-day="{{ $day }}">
            <input type="hidden" name="hours[{{ $loop->index }}][day]" value="{{ $day }}">
            @foreach($entry['hour_ids'] ?? [] as $hourId)<input type="hidden" name="hours[{{ $loop->parent->index }}][hour_ids][]" value="{{ $hourId }}">@endforeach
            <div class="hours-day-heading"><b>{{ $label }}</b><label class="sr-only" for="hours-{{ $day }}-status">État {{ $label }}</label><select id="hours-{{ $day }}-status" name="hours[{{ $loop->index }}][status]" data-hours-status><option value="closed" @selected($status === 'closed')>Fermé</option><option value="all_day" @selected($status === 'all_day')>Ouvert 24h/24</option><option value="slots" @selected($status === 'slots')>Horaires</option></select></div>
            <div class="hours-slots" data-hours-slots @if($status !== 'slots') hidden @endif>
                @foreach($slots as $slot)
                    <div class="hours-slot-row" data-hours-slot>
                        <input type="hidden" name="hours[{{ $loop->parent->index }}][slots][{{ $loop->index }}][id]" value="{{ $slot['id'] ?? '' }}" data-hours-slot-id @if(!($slot['id'] ?? null)) disabled @endif>
                        <label>De <input type="time" name="hours[{{ $loop->parent->index }}][slots][{{ $loop->index }}][opens_at]" value="{{ $slot['opens_at'] ?? '' }}" data-hours-slot-open @required($status === 'slots')></label>
                        <label>à <input type="time" name="hours[{{ $loop->parent->index }}][slots][{{ $loop->index }}][closes_at]" value="{{ $slot['closes_at'] ?? '' }}" data-hours-slot-close @required($status === 'slots')></label>
                        @unless($loop->first)<button class="hours-slot-action" type="button" data-remove-hours-slot>Supprimer cette plage</button>@endunless
                    </div>
                @endforeach
                <button class="hours-slot-action" type="button" data-add-hours-slot>+ Ajouter une plage</button>
            </div>
            <template data-hours-slot-template><div class="hours-slot-row" data-hours-slot><input type="hidden" data-hours-slot-id disabled><label>De <input type="time" data-hours-slot-open></label><label>à <input type="time" data-hours-slot-close></label><button class="hours-slot-action" type="button" data-remove-hours-slot>Supprimer cette plage</button></div></template>
        </div>
    @endforeach
    <div class="hours-copy"><label for="hours-copy-source">Recopier depuis</label><select id="hours-copy-source" data-copy-source>@foreach($days as $day => $label)<option value="{{ $day }}">{{ $label }}</option>@endforeach</select><span class="hours-copy-to" aria-hidden="true">vers :</span><fieldset><legend class="sr-only">Jours à mettre à jour</legend>@foreach($days as $day => $label)<label><input type="checkbox" value="{{ $day }}" data-copy-target="{{ $day }}"> {{ $label }}</label>@endforeach</fieldset><button class="button button-secondary button-small" type="button" data-copy-hours>Recopier</button></div>
</fieldset>
