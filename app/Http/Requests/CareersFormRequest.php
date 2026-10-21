<?php

namespace App\Http\Requests;

use App\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CareersFormRequest extends FormRequest
{
    protected $errorBag = 'careers';

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
            'email' => ['required', 'email:rfc', 'max:150'],
            'position' => ['required', Rule::in(Settings::lines('careers_positions'))],
            'qualification' => ['required', 'string', 'max:200'],
            'experience' => ['nullable', 'string', 'max:20'],
            'cv' => ['required', 'file', 'max:5120', 'mimes:pdf,doc,docx', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip,application/octet-stream'],
            'photo' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp', 'image'],
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
            'consent.accepted' => 'Please agree to be contacted about your application.',
            'cv.mimes' => 'Please upload your CV as a PDF or Word document.',
            'cv.max' => 'The CV must be smaller than 5 MB.',
            'photo.max' => 'The photograph must be smaller than 5 MB.',
        ];
    }
}
