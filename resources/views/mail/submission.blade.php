A new {{ $submission->form === 'careers' ? 'job application' : 'enquiry' }} was submitted on the website.

@foreach($submission->data as $key => $value)
@if(!str_ends_with($key, '_path'))
{{ \Illuminate\Support\Str::headline($key) }}: {{ is_array($value) ? implode(', ', $value) : $value }}
@endif
@endforeach

Received: {{ $submission->created_at->format('d M Y, H:i') }}
@if($submission->attachment_name)
Attachment: {{ $submission->attachment_name }} (download it from the admin panel)
@endif

Open in the admin panel: {{ route('admin.submissions.show', $submission) }}
