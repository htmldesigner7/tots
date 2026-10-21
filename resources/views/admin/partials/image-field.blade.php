{{-- Settings / page-level image chooser. $name, $value (array), $label, $media (map id=>info) --}}
@php($m = !empty($value['media_id']) ? ($media[$value['media_id']] ?? null) : null)
<div class="imgfield" data-imgfield data-name="{{ $name }}" @if(!empty($idMode)) data-mode="id" @endif>
  <input type="hidden" name="{{ $name }}" value="{{ !empty($idMode) ? ($value['media_id'] ?? '') : json_encode($value ?? (object) []) }}" data-imgfield-input>
  <div class="imgfield__prev" data-imgfield-prev>
    @if($m)<img src="{{ $m['thumb'] }}" alt="">@elseif(!empty($value['src']))<img src="{{ asset($value['src']) }}" alt="">@else<span class="muted">No image</span>@endif
  </div>
  <div class="imgfield__act">
    <button type="button" class="btn sm" data-imgfield-choose>Choose image</button>
    @if(empty($noClear))<button type="button" class="btn sm ghost" data-imgfield-clear>{{ !empty($value['src']) ? 'Use design default' : 'Remove' }}</button>@endif
    <span class="muted small" data-imgfield-name>{{ $m['filename'] ?? (!empty($value['src']) ? 'Design asset: '.basename($value['src']) : '') }}</span>
  </div>
</div>
