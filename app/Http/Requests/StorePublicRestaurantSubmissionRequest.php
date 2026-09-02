<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublicRestaurantSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'halal_meat' => ['nullable', 'boolean'],
            'halal_chicken' => ['nullable', 'boolean'],
            'address_suggestion_token' => ['required', 'uuid'],
            'latitude' => ['nullable', 'numeric', 'between:41,52'],
            'longitude' => ['nullable', 'numeric', 'between:-5.5,10'],
            'map_moved' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array', 'max:10'],
            'categories.*' => ['integer', 'distinct', Rule::exists('categories', 'id')],
            'features' => ['nullable', 'array', 'max:20'],
            'features.*' => ['integer', 'distinct', Rule::exists('features', 'id')],
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.status' => ['required', Rule::in(['closed', 'all_day', 'slots'])],
            'hours.*.first_open' => ['nullable', 'date_format:H:i'],
            'hours.*.first_close' => ['nullable', 'date_format:H:i'],
            'hours.*.second_open' => ['nullable', 'date_format:H:i'],
            'hours.*.second_close' => ['nullable', 'date_format:H:i'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]{6,30}$/'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:2048'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:2048'],
            'tiktok_url' => ['nullable', 'url:http,https', 'max:2048'],
            'description' => ['nullable', 'string', 'max:3000'],
            'cover_photo' => ['required', 'file', 'image', 'dimensions:min_width=800', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'gallery_photos' => ['nullable', 'array', 'max:10'],
            'gallery_photos.*' => ['file', 'image', 'dimensions:min_width=800', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'submitter_role' => ['required', Rule::in(['owner', 'employee', 'customer'])],
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('halal_meat') && ! $this->boolean('halal_chicken')) {
                $validator->errors()->add('halal_meat', 'Cochez au moins « Viande halal » ou « Poulet halal ».');
            }

            if ($this->boolean('map_moved') && (! filled($this->input('latitude')) || ! filled($this->input('longitude')))) {
                $validator->errors()->add('latitude', 'La position déplacée doit contenir des coordonnées valides.');
            }

            if (preg_match('/(?:https?:\/\/|www\.)/iu', (string) $this->input('description'))) {
                $validator->errors()->add('description', 'La description ne doit pas contenir d’URL. Les liens du restaurant se renseignent dans les champs dédiés.');
            }

            foreach ((array) $this->input('hours', []) as $day => $hours) {
                if (($hours['status'] ?? null) !== 'slots') continue;
                $firstOpen = $hours['first_open'] ?? null;
                $firstClose = $hours['first_close'] ?? null;
                $secondOpen = $hours['second_open'] ?? null;
                $secondClose = $hours['second_close'] ?? null;
                if (! $firstOpen || ! $firstClose) {
                    $validator->errors()->add("hours.$day.first_open", 'Indiquez les deux heures de la première plage.');
                    continue;
                }
                if ($firstClose <= $firstOpen) $validator->errors()->add("hours.$day.first_close", 'La fermeture doit être postérieure à l’ouverture.');
                if (($secondOpen && ! $secondClose) || (! $secondOpen && $secondClose)) $validator->errors()->add("hours.$day.second_open", 'Indiquez les deux heures de la seconde plage, ou laissez-les toutes deux vides.');
                if ($secondOpen && $secondClose) {
                    if ($secondClose <= $secondOpen) $validator->errors()->add("hours.$day.second_close", 'La seconde fermeture doit être postérieure à son ouverture.');
                    if ($secondOpen <= $firstClose) $validator->errors()->add("hours.$day.second_open", 'La seconde plage doit commencer après la première.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'cover_photo.dimensions' => 'La photo de couverture doit mesurer au moins 800 pixels de large.',
            'gallery_photos.*.dimensions' => 'Chaque photo de galerie doit mesurer au moins 800 pixels de large.',
        ];
    }
}
