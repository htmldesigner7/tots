{{-- Generated from index.html — design-matched section. --}}
<section class="world" aria-labelledby="world-title{{ $s->uid }}">
<div class="wrap">
<div class="world__top">
<div class="heading" data-anim="fade-up">
<h2 id="world-title{{ $s->uid }}">
<span class="h-display h-caps">{!! $s->html('heading_first_line') !!}</span>
<span class="h-script">{!! $s->html('heading_script_line') !!}</span>
</h2>
<img class="uline uline--world" data-anim="draw" src="{{ asset('assets/img/underline.png') }}" width="473" height="63" alt="">
</div>
</div>
<ul class="pillars">
@foreach($s->items('items') as $it0)<li class="pillar" data-anim="fade-up">
<img {!! $it0->img('image') !!} loading="lazy">
<span class="pillar__eyebrow">{!! $it0->html('text') !!}</span>
<h3>{!! $it0->html('title') !!}</h3>
<p>{!! $it0->html('text_2') !!}</p>
</li>
@endforeach</ul>
</div>
</section>
