@extends('admin.layout')
@section('title', 'Enquiries')
@php($tz = \App\Support\Settings::get('timezone'))
@section('content')
<div class="head"><h1>Enquiries &amp; applications</h1>
  <div><a class="btn sm" href="{{ route('admin.submissions.export', ['form' => 'enquiry']) }}">Export enquiries (CSV)</a><a class="btn sm" href="{{ route('admin.submissions.export', ['form' => 'careers']) }}">Export applications (CSV)</a></div></div>
<form class="filters" method="get">
  <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, email or phone" aria-label="Search">
  <select name="form" aria-label="Form"><option value="">All forms</option><option value="enquiry" @selected(request('form') === 'enquiry')>Enquiries</option><option value="careers" @selected(request('form') === 'careers')>Job applications</option></select>
  <button class="btn sm">Filter</button>
</form>
<div class="tblwrap"><table class="tbl">
  <thead><tr><th>Received</th><th>Form</th><th>Name</th><th>Contact</th><th>Details</th><th></th></tr></thead>
  <tbody>@forelse($items as $s)
    <tr @if(!$s->read_at) style="font-weight:600" @endif>
      <td class="small">{{ $s->created_at->timezone($tz)->format('d M Y, H:i') }}</td>
      <td>{{ $s->form === 'careers' ? 'Application' : 'Enquiry' }}</td>
      <td>{{ $s->name }}</td>
      <td class="small">{{ $s->phone }}<br>{{ $s->email }}</td>
      <td class="small">{{ $s->form === 'careers' ? ($s->data['position'] ?? '') : (($s->data['programme'] ?? '').' · '.($s->data['centre'] ?? '').(($s->data['type'] ?? '') === 'visit' ? ' · visit' : '')) }}</td>
      <td class="actions"><a class="btn sm" href="{{ route('admin.submissions.show', $s) }}">Open</a></td>
    </tr>
  @empty <tr><td colspan="6" class="muted">No submissions yet.</td></tr>
  @endforelse</tbody>
</table></div>
{{ $items->links() }}
@endsection
