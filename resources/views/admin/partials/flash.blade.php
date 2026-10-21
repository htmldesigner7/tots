@if(session('success'))<div class="alert ok" role="status">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert bad" role="alert">{{ session('error') }}</div>@endif
@if($errors->any())
<div class="alert bad" role="alert"><strong>Please fix the following:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
