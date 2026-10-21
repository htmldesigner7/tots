<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('submissions.view');
        $q = FormSubmission::query()->latest();
        if ($f = $request->query('form')) {
            $q->where('form', $f);
        }
        if ($s = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
        }

        return view('admin.submissions.index', ['items' => $q->paginate(30)->withQueryString()]);
    }

    public function show(FormSubmission $submission): View
    {
        $this->authorize('submissions.view');
        if (! $submission->read_at) {
            $submission->forceFill(['read_at' => now()])->save();
        }

        return view('admin.submissions.show', ['s' => $submission]);
    }

    /** Attachments live on the private disk and are only streamed to authorised users. */
    public function download(FormSubmission $submission, string $which): StreamedResponse
    {
        $this->authorize('submissions.view');
        [$path, $name] = match ($which) {
            'cv' => [$submission->attachment_path, $submission->attachment_name],
            'photo' => [$submission->data['photo_path'] ?? null, $submission->data['photo_name'] ?? 'photo'],
            default => [null, null],
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        AuditLogger::log('submission.download', 'Downloaded '.$which.' of submission #'.$submission->id, $submission);

        return Storage::disk('local')->download($path, preg_replace('/[^\w.\- ]/u', '_', (string) $name));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('submissions.view');
        $form = $request->query('form', 'enquiry') === 'careers' ? 'careers' : 'enquiry';
        AuditLogger::log('submission.export', 'Exported '.$form.' submissions as CSV');

        return response()->streamDownload(function () use ($form) {
            $out = fopen('php://output', 'w');
            $cols = $form === 'careers'
                ? ['name', 'phone', 'email', 'position', 'qualification', 'experience', 'message']
                : ['name', 'phone', 'email', 'centre', 'programme', 'age', 'type', 'message'];
            fputcsv($out, array_merge(['id', 'received'], $cols));
            FormSubmission::where('form', $form)->orderBy('id')->chunk(500, function ($rows) use ($out, $cols) {
                foreach ($rows as $r) {
                    // prefix cells that spreadsheets would treat as formulas
                    $vals = array_map(fn ($c) => preg_replace('/^([=+\-@\t\r])/', "'$1", (string) ($r->data[$c] ?? '')), $cols);
                    fputcsv($out, array_merge([$r->id, $r->created_at->toDateTimeString()], $vals));
                }
            });
            fclose($out);
        }, $form.'-submissions-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(FormSubmission $submission): RedirectResponse
    {
        $this->authorize('submissions.delete');
        foreach (array_filter([$submission->attachment_path, $submission->data['photo_path'] ?? null]) as $p) {
            Storage::disk('local')->delete($p);
        }
        $submission->delete();
        AuditLogger::log('submission.deleted', 'Deleted '.$submission->form.' submission #'.$submission->id.' ('.$submission->name.')');

        return redirect()->route('admin.submissions.index')->with('success', 'Submission deleted.');
    }
}
