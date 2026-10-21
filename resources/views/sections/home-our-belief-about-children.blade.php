{{-- Generated from index.html — design-matched section. --}}
<section class="about" id="{{ $s->anchor }}" aria-labelledby="about-title{{ $s->uid }}">
<div class="wrap about__inner">
<img {!! $s->img('image') !!} class="about__art" data-anim="slide-l" loading="lazy">
<div class="about__content" data-anim="fade-up">
<div class="heading" data-anim="fade-up">
<h2 id="about-title{{ $s->uid }}">
<span class="h-display h-caps">{!! $s->html('heading_first_line') !!}</span>
<span class="h-script">{!! $s->html('heading_script_line') !!}</span>
</h2>
<img class="uline uline--about" data-anim="draw" src="{{ asset('assets/img/underline.png') }}" width="474" height="63" alt="">
</div>
<p>{!! $s->html('text') !!}</p>
<p>{!! $s->html('text_2') !!}</p>
<a {!! $s->linkAttrs('button') !!} class="btn">{!! $s->linkLabel('button') !!}</a>
</div>
</div>
</section>
