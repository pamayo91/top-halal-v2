<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'content' => ['required', 'string', 'min:2', 'max:2000', function (string $attribute, mixed $value, \Closure $fail): void {
                $text = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('/https?:\/\/|www\.|<\s*\/?a\b|(?:[a-z0-9-]+\.)+[a-z]{2,}\b/i', $text)) $fail('Les liens et URLs ne sont pas autorisés dans les commentaires.');
            }],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
