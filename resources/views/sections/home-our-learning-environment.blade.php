{{-- Generated from index.html — design-matched section. --}}
<section class="campus" id="{{ $s->anchor }}" aria-labelledby="campus-title{{ $s->uid }}">
<div class="wrap">
<div class="campus__top">
<div class="heading" data-anim="fade-up">
<h2 id="campus-title{{ $s->uid }}">
<span class="h-display h-caps">{!! $s->html('heading_first_line') !!}</span>
<span class="h-script">{!! $s->html('heading_script_line') !!}</span>
</h2>
<img class="uline uline--campus" data-anim="draw" src="{{ asset('assets/img/underline.png') }}" width="505" height="63" alt="">
</div>
<div class="campus__intro" data-anim="fade-up">
<p>{!! $s->html('text') !!}</p>
<a {!! $s->linkAttrs('button') !!} class="btn">{!! $s->linkLabel('button') !!}</a>
</div>
<img class="campus__dots" data-anim="pop" src="{{ asset('assets/img/dots.png') }}" width="90" height="68" loading="lazy" alt="">
</div>
<ul class="spaces">
<li class="space" data-anim="fade-up"><img {!! $s->img('image') !!} loading="lazy"><h3>{!! $s->html('title') !!}</h3><p>{!! $s->html('text_2') !!}</p></li>
<li class="space" data-anim="fade-up"><img {!! $s->img('image_2') !!} loading="lazy"><h3>{!! $s->html('title_2') !!}</h3><p>{!! $s->html('text_3') !!}</p></li>
<li class="space" data-anim="fade-up"><img {!! $s->img('image_3') !!} loading="lazy"><h3>{!! $s->html('title_3') !!}</h3><p>{!! $s->html('text_4') !!}</p></li>
<li class="space space--wide" data-anim="fade-up"><img {!! $s->img('image_4') !!} loading="lazy"><h3>{!! $s->html('title_4') !!}</h3><p>{!! $s->html('text_5') !!}</p></li>
<li class="space space--wide" data-anim="fade-up"><img {!! $s->img('image_5') !!} loading="lazy"><h3>{!! $s->html('title_5') !!}</h3><p>{!! $s->html('text_6') !!}</p></li>
</ul>
</div>
</section>
