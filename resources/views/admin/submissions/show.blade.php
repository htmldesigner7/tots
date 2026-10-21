@extends('admin.layout')
@section('title', 'Submission #'.$s->id)
@php($tz = \App\Support\Settings::get('timezone'))
@section('content')
<div class="head"><div><a class="small" href="{{ route('admin.submissions.index') }}">← Enquiries</a><h1>{{ $s->form === 'careers' ? 'Job application' : 'Enquiry' }} from {{ $s->name }}</h1></div></div>
<section class="card" style="max-width:800px">
  <dl class="kv">
    <dt>Received</dt><dd>{{ $s->created_at->timezone($tz)->format('d M Y, H:i') }}</dd>
    @foreach($s->data as $k => $v)
      @continue(str_ends_with($k, '_path') || $k === 'photo_name')
      <dt>{{ Str::headline($k) }}</dt><dd style="white-space:pre-line">{{ is_array($v) ? implode(', ', $v) : $v }}</dd>
    @endforeach
    @if($s->attachment_path)<dt>CV</dt><dd><a href="{{ route('admin.submissions.download', [$s, 'cv']) }}">{{ $s->attachment_name }}</a></dd>@endif
    @if(!empty($s->data['photo_path']))<dt>Photograph</dt><dd><a href="{{ route('admin.submissions.download', [$s, 'photo']) }}">{{ $s->data['photo_name'] ?? 'Download' }}</a></dd>@endif
    <dt>Sent from</dt><dd>{{ $s->source_url }}</dd>
    <dt>IP address</dt><dd>{{ $s->ip }}</dd>
  </dl>
  <div class="btnrow" style="margin-top:16px">
    @if($s->email)<a class="btn primary" href="mailto:{{ $s->email }}">Reply by email</a>@endif
    @if($s->phone)<a class="btn" href="tel:{{ preg_replace('/[^+\d]/', '', $s->phone) }}">Call</a>@endif
    @can('submissions.delete')<form method="post" action="{{ route('admin.submissions.destroy', $s) }}" data-confirm="Delete this submission and its attachments permanently?">@csrf @method('DELETE')<button class="btn danger">Delete</button></form>@endcan
  </div>
</section>
@endsection
