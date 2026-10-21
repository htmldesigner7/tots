<?php

namespace App\Http\Requests;

use App\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnquiryFormRequest extends FormRequest
{
    protected $errorBag = 'enquiry';

    protected function getRedirectUrl()
    {
        return \App\Support\FormAnchor::back($this);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[+\d][\d\s()-]{6,}$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'centre' => ['required', Rule::in(Settings::lines('enquiry_centres'))],
            'programme' => ['required', Rule::in(Settings::lines('enquiry_programmes'))],
            'age' => ['nullable', 'string', 'max:40'],
            'type' => ['required', Rule::in(['general', 'visit'])],
            'message' => ['nullable', 'string', 'max:3000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string', 'max:200'],
            '_anchor' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid phone number.',
            'consent.accepted' => 'Please agree to be contacted about your enquiry.',
            'centre.required' => 'Please select a centre.',
            'programme.required' => 'Please select a programme.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'name', 'centre' => 'centre', 'programme' => 'programme'];
    }
}
