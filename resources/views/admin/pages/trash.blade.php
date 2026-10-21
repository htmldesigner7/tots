@extends('admin.layout')
@section('title', 'Trash')
@section('content')
<div class="head"><h1>Trash</h1><a class="btn ghost" href="{{ route('admin.pages.index') }}">← All pages</a></div>
<p class="muted">Deleted pages can be restored (as drafts) or deleted permanently.</p>
<div class="tblwrap"><table class="tbl">
  <thead><tr><th>Title</th><th>Deleted</th><th></th></tr></thead>
  <tbody>
  @forelse($pages as $p)
    <tr><td>{{ $p->title }}</td><td class="small">{{ $p->deleted_at->diffForHumans() }}</td>
      <td class="actions">
        <form method="post" action="{{ route('admin.pages.restore', $p->id) }}">@csrf<button class="btn sm">Restore</button></form>
        <form method="post" action="{{ route('admin.pages.force', $p->id) }}" data-confirm="Permanently delete “{{ $p->title }}”? This cannot be undone.">@csrf @method('DELETE')<button class="btn sm danger">Delete permanently</button></form>
      </td></tr>
  @empty <tr><td colspan="3" class="muted">The trash is empty.</td></tr>
  @endforelse
  </tbody>
</table></div>
{{ $pages->links() }}
@endsection
