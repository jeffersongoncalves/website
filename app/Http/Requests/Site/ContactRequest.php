<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind'    => ['required', Rule::in(array_keys(config('site.contact_kinds')))],
            'name'    => ['required', 'string', 'min:2', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'email'   => ['required', 'email:rfc', 'max:160'],
            'budget'  => ['nullable', Rule::in(config('site.budget_options'))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
