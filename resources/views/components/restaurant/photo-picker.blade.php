@props([
    'id',
    'name',
    'label',
    'buttonLabel' => 'Choisir des photos',
    'help' => null,
    'maxFiles' => 10,
    'required' => false,
    'kind' => 'gallery',
    'removeLabel' => 'Retirer',
    'reorderable' => false,
    'presentation' => 'public',
])

@php
    $isCover = $kind === 'cover';
    $inputData = $isCover ? 'data-cover-input' : ($kind === 'gallery' ? 'data-gallery-input' : 'data-owner-new-photos-input');
    $previewData = $isCover ? 'data-cover-preview' : ($kind === 'gallery' ? 'data-gallery-preview' : 'data-owner-new-photos-preview');
@endphp

<div
    class="restaurant-photo-picker restaurant-photo-picker--{{ $presentation }} restaurant-photo-picker--{{ $kind }}"
    data-photo-picker
    data-photo-max-files="{{ $maxFiles }}"
    data-photo-min-width="800"
    data-photo-max-bytes="10485760"
    data-photo-reorderable="{{ $reorderable ? 'true' : 'false' }}"
    data-photo-remove-label="{{ $removeLabel }}"
    data-photo-presentation="{{ $presentation }}"
>
    <label class="restaurant-photo-picker-title" for="{{ $id }}">{{ $label }}@if($required)<span aria-hidden="true"> *</span>@endif</label>
    <input
        id="{{ $id }}"
        class="restaurant-photo-picker-input"
        name="{{ $name }}"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        @if(! $isCover) multiple @endif
        @if($required) required @endif
        data-photo-input
        {!! $inputData !!}
    >
    <label class="button button-secondary restaurant-photo-picker-button" for="{{ $id }}">{{ $buttonLabel }}</label>
    <p class="form-help" data-photo-selection-count>Aucune photo sélectionnée.</p>
    @if($help)<p class="form-help">{{ $help }}</p>@endif
    <p class="field-error" role="alert" hidden data-photo-picker-errors></p>
    @if($isCover)
        <div class="photo-cover-preview" data-photo-preview {!! $previewData !!} aria-live="polite"></div>
    @elseif($presentation === 'owner')
        <ol class="owner-media-gallery owner-new-media-gallery" data-photo-preview {!! $previewData !!} aria-live="polite"></ol>
    @else
        <ol class="photo-gallery-preview" data-photo-preview {!! $previewData !!} aria-live="polite"></ol>
    @endif
</div>
