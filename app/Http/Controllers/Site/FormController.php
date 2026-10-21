<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\CareersFormRequest;
use App\Http\Requests\EnquiryFormRequest;
use App\Mail\NewSubmission;
use App\Models\FormSubmission;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function enquiry(EnquiryFormRequest $request): JsonResponse|RedirectResponse
    {
        if (! $request->filled('website')) {
            $data = $request->safe()->only(['name', 'phone', 'email', 'centre', 'programme', 'age', 'type', 'message']);
            $submission = FormSubmission::create($this->base($request, 'enquiry') + [
                'name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'],
                'data' => $data,
            ]);
            $this->notify($submission, Settings::get('notify_email'));
        }

        return $this->done($request, 'enquiry', 'Thank you! Our team will be in touch soon.');
    }

    public function careers(CareersFormRequest $request): JsonResponse|RedirectResponse
    {
        if (! $request->filled('website')) {
            $data = $request->safe()->only(['name', 'phone', 'email', 'position', 'qualification', 'experience', 'message']);
            $dir = 'submissions/'.now()->format('Y/m');
            $cv = $request->file('cv');
            $cvPath = $cv->storeAs($dir, Str::uuid().'.'.strtolower($cv->getClientOriginalExtension()), 'local');
            if ($photo = $request->file('photo')) {
                $data['photo_path'] = $photo->storeAs($dir, Str::uuid().'.'.strtolower($photo->extension()), 'local');
                $data['photo_name'] = Str::limit($photo->getClientOriginalName(), 200, '');
            }
            $submission = FormSubmission::create($this->base($request, 'careers') + [
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'data' => $data,
                'attachment_path' => $cvPath, 'attachment_name' => Str::limit($cv->getClientOriginalName(), 200, ''),
            ]);
            $this->notify($submission, Settings::get('careers_email') ?: Settings::get('notify_email'));
        }

        return $this->done($request, 'careers', 'Thank you for applying! The school will get in touch soon.');
    }

    public function thanks(Request $request)
    {
        $form = $request->query('form') === 'careers' ? 'careers' : 'enquiry';

        return response()->view('site.thanks', ['form' => $form]);
    }

    private function base(Request $request, string $form): array
    {
        return [
            'form' => $form,
            'source_url' => Str::limit((string) $request->headers->get('referer'), 490, ''),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ];
    }

    private function notify(FormSubmission $submission, ?string $to): void
    {
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new NewSubmission($submission));
        } catch (\Throwable $e) {
            // the submission is safely stored; a mail outage must not lose it or show an error
            Log::warning('Form notification email failed', ['id' => $submission->id, 'error' => $e->getMessage()]);
        }
    }

    private function done(Request $request, string $form, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => route('thanks', ['form' => $form])]);
        }

        return redirect()->route('thanks', ['form' => $form]);
    }
}
