{{-- Generated from learning-approach.html — design-matched section. --}}
<div class="strip"><ul class="w">@foreach($s->items('items') as $it0)<li data-anim="fade-up"><img {!! $it0->img('icon') !!} aria-hidden="true" decoding="async" loading="lazy"><span>{!! $it0->html('text') !!}</span></li>@endforeach</ul></div>
