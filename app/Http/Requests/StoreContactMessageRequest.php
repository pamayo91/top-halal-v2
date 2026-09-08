<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreContactMessageRequest extends FormRequest
{
    public function rules(): array { return ['name'=>['required','string','max:120'],'email'=>['required','email:rfc,dns','max:254'],'subject'=>['required','string','max:180'],'message'=>['required','string','max:5000'],'website'=>['nullable','max:0']]; }
}
