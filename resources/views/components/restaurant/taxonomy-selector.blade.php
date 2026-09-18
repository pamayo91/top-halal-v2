@props([
    'kind',
    'items',
    'selected' => [],
    'required' => false,
])

@php
    $isCategory = $kind === 'categories';
    $legend = $isCategory ? 'Catégories / type de cuisine' : 'Services et caractéristiques';
    $field = $isCategory ? 'categories' : 'features';
    $help = $isCategory
        ? 'Choisissez au moins une spécialité.'
        : 'Choisissez au moins un service. Une certification halal éventuelle reste facultative.';
    $error = $isCategory
        ? 'Choisissez au moins une catégorie ou un type de cuisine.'
        : 'Choisissez au moins un service ou une caractéristique.';
@endphp

<fieldset class="taxonomy-fieldset" data-taxonomy-group="{{ $field }}">
    <legend>{{ $legend }}</legend>
    <p class="form-help">{{ $help }}</p>
    <div class="checkbox-grid">
        @foreach($items as $item)
            <label><input type="checkbox" name="{{ $field }}[]" value="{{ $item->id }}" @if($required && $loop->first && count($selected) === 0) required @endif @checked(in_array((int) $item->id, $selected, true))> <span>{{ $item->name }}</span></label>
        @endforeach
    </div>
    @if($required)<p class="field-error" hidden data-taxonomy-error="{{ $field }}">{{ $error }}</p>@endif
    @error($field)<p class="field-error">{{ $message }}</p>@enderror
</fieldset>
