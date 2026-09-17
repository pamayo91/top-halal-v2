<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
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
            'categories' => ['required', 'array', 'min:1', 'max:10'],
            'categories.*' => ['integer', 'distinct', Rule::exists('categories', 'id')],
            'features' => ['required', 'array', 'min:1', 'max:20'],
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
            'owner_full_name' => ['exclude_unless:submitter_role,owner', 'required', 'string', 'max:255'],
            'owner_company' => ['exclude_unless:submitter_role,owner', 'required', 'string', 'max:255'],
            'owner_siret' => ['exclude_unless:submitter_role,owner', 'required', 'digits:14'],
            'owner_certified' => ['exclude_unless:submitter_role,owner', 'accepted'],
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
            $siret = (string) $this->input('owner_siret');
            if ($this->input('submitter_role') === 'owner' && $siret !== '' && ! $this->isValidSiret($siret)) $validator->errors()->add('owner_siret', 'Le SIRET doit comporter 14 chiffres valides.');
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(
            redirect()->to($this->getRedirectUrl())
                ->withInput()
                ->withErrors($validator)
                ->with('submission_error_step', $this->firstErrorStep($validator))
        );
    }

    private function firstErrorStep(ValidatorContract $validator): int
    {
        foreach (array_keys($validator->errors()->messages()) as $field) {
            if (in_array($field, ['name', 'halal_meat', 'halal_chicken'], true)) return 1;
            if (in_array($field, ['address_suggestion_token', 'latitude', 'longitude', 'map_moved'], true)) return 2;
            if (str_starts_with($field, 'categories') || str_starts_with($field, 'features') || str_starts_with($field, 'hours.') || in_array($field, ['phone', 'website_url', 'instagram_url', 'facebook_url', 'tiktok_url', 'description'], true)) return 3;
            if (str_starts_with($field, 'cover_photo') || str_starts_with($field, 'gallery_photos')) return 4;
        }

        return 5;
    }

    protected function prepareForValidation(): void { if ($this->has('owner_siret')) $this->merge(['owner_siret'=>preg_replace('/\D+/', '', (string) $this->input('owner_siret'))]); }
    private function isValidSiret(string $siret): bool { if (! preg_match('/^\d{14}$/',$siret)) return false; $sum=0; foreach(str_split($siret) as $i=>$digit){$n=(int)$digit; if($i%2===0){$n*=2;if($n>9)$n-=9;}$sum+=$n;} return $sum%10===0; }

    public function messages(): array
    {
        return [
            'categories.required' => 'Choisissez au moins une catégorie ou un type de cuisine.',
            'categories.min' => 'Choisissez au moins une catégorie ou un type de cuisine.',
            'features.required' => 'Choisissez au moins un service ou une caractéristique.',
            'features.min' => 'Choisissez au moins un service ou une caractéristique.',
            'cover_photo.dimensions' => 'La photo de couverture doit mesurer au moins 800 pixels de large.',
            'gallery_photos.*.dimensions' => 'Chaque photo de galerie doit mesurer au moins 800 pixels de large.',
            'submitter_role.required' => 'Choisissez si vous êtes le gérant ou propriétaire.',
            'owner_full_name.required' => 'Indiquez vos nom et prénom.',
            'owner_company.required' => 'Indiquez le nom de votre société.',
            'owner_siret.required' => 'Indiquez le SIRET.',
            'owner_siret.digits' => 'Le SIRET doit comporter 14 chiffres valides.',
            'owner_certified.accepted' => 'Cochez la certification pour continuer.',
            'email.required' => 'Indiquez votre adresse e-mail.',
        ];
    }
}
