<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email' => [$this->user() ? 'nullable' : 'required', 'email:rfc', 'max:255'],
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
