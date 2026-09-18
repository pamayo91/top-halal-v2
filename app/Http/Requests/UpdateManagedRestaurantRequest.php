<?php

namespace App\Http\Requests;

use App\Services\RestaurantHours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateManagedRestaurantRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'phone' => ['nullable', 'string', 'max:100', 'regex:/^[0-9+().\s-]{6,100}$/'],
            'halal_meat' => ['nullable', 'boolean'],
            'halal_chicken' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array', 'max:10'],
            'categories.*' => ['integer', 'distinct', Rule::exists('categories', 'id')],
            'features' => ['nullable', 'array', 'max:20'],
            'features.*' => ['integer', 'distinct', Rule::exists('features', 'id')],
            'hours' => ['nullable', 'array', 'size:7'],
            'hours.*.day' => ['required', Rule::in(array_keys(RestaurantHours::DAYS))],
            'hours.*.status' => ['required', Rule::in(['closed', 'all_day', 'slots'])],
            'hours.*.hour_ids' => ['nullable', 'array'],
            'hours.*.hour_ids.*' => ['integer', 'distinct'],
            'hours.*.slots' => ['nullable', 'array', 'max:8'],
            'hours.*.slots.*.id' => ['nullable', 'integer'],
            'hours.*.slots.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.slots.*.closes_at' => ['nullable', 'date_format:H:i'],
            'location_changed' => ['nullable', 'boolean'],
            'address_suggestion_token' => ['required_if:location_changed,1', 'nullable', 'uuid'],
            'map_moved' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'required_if:map_moved,1', 'numeric', 'between:41,52'],
            'longitude' => ['nullable', 'required_if:map_moved,1', 'numeric', 'between:-5.5,10'],
            'new_photos' => ['nullable', 'array', 'max:10'],
            'new_photos.*' => ['file', 'image', 'dimensions:min_width=800', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'remove_media_ids' => ['nullable', 'array'],
            'remove_media_ids.*' => ['integer', 'distinct'],
            'media_order' => ['nullable', 'array'],
            'media_order.*' => ['integer', 'distinct'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:2048'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:2048'],
            'tiktok_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (($this->has('halal_meat') || $this->has('halal_chicken')) && ! $this->boolean('halal_meat') && ! $this->boolean('halal_chicken')) {
                $validator->errors()->add('halal_meat', 'Cochez au moins « Viande halal » ou « Poulet halal ».');
            }
            if (preg_match('/(?:https?:\/\/|www\.)/iu', (string) $this->input('description'))) {
                $validator->errors()->add('description', 'La description ne doit pas contenir d’URL. Les liens du restaurant se renseignent dans les champs dédiés.');
            }
            if ($this->has('hours')) {
                try {
                    app(RestaurantHours::class)->validatedEditorRows((array) $this->input('hours', []));
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) foreach ($messages as $message) $validator->errors()->add($field, $message);
                }
            }
        });
    }
}
