@props([
    'meat' => false,
    'chicken' => false,
    'required' => false,
    'includeFalseValues' => false,
])

<fieldset class="submission-choice-group" data-halal-group>
    <legend>Que propose le restaurant ?</legend>
    <p class="form-help">@if($required)Au moins une option est nécessaire pour continuer.@else Sélectionnez les options qui correspondent à votre établissement.@endif</p>
    @if($includeFalseValues)<input type="hidden" name="halal_meat" value="0"><input type="hidden" name="halal_chicken" value="0">@endif
    <label class="choice-card"><input type="checkbox" name="halal_meat" value="1" @checked($meat) data-halal-option> <span><b>Viande halal</b><small>Le restaurant propose de la viande halal.</small></span></label>
    <label class="choice-card"><input type="checkbox" name="halal_chicken" value="1" @checked($chicken) data-halal-option> <span><b>Poulet halal</b><small>Le restaurant propose du poulet halal.</small></span></label>
    @if($required)<p class="field-error" hidden data-halal-error>Choisissez au moins une des deux options pour continuer.</p>@endif
    @error('halal_meat')<p class="field-error">{{ $message }}</p>@enderror
</fieldset>
