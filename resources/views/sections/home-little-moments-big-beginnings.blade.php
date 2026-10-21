{{-- Generated from index.html — design-matched section. --}}
<section class="hero" aria-labelledby="hero-title{{ $s->uid }}">
<div class="wrap hero__inner">
<div class="hero__content">
<div class="heading" data-anim="fade-up">
<p class="hero__eyebrow">{!! $s->html('text') !!}</p>
<h1 id="hero-title{{ $s->uid }}">
<span class="h-display h-caps">{!! $s->html('heading_first_line') !!}</span>
<span class="h-script">{!! $s->html('heading_script_line') !!}</span>
</h1>
<img class="uline uline--hero" data-anim="draw" src="{{ asset('assets/img/underline.png') }}" width="473" height="63" alt="">
</div>
<p class="hero__text" data-anim="fade-up">{!! $s->html('text_2') !!}</p>
<a {!! $s->linkAttrs('button') !!} class="btn hero__btn" data-anim="fade-up">{!! $s->linkLabel('button') !!}</a>
<img {!! $s->img('image') !!} class="hero__note sway" data-anim="pop">
<img class="hero__heart float" data-anim="pop" src="{{ asset('assets/img/heart.png') }}" width="84" height="84" alt="">
</div>
<img {!! $s->img('image_2') !!} class="hero__art" fetchpriority="high">
</div>
</section>
