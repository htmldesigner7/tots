{{-- Generated from index.html — design-matched section. --}}
<section class="care" id="{{ $s->anchor }}" aria-labelledby="care-title{{ $s->uid }}">
<img {!! $s->img('image') !!} class="care__bg" loading="lazy">
<div class="wrap care__inner" data-anim="fade-up">
<div class="heading heading--center" data-anim="fade-up">
<h2 id="care-title{{ $s->uid }}">
<span class="h-display h-caps">{!! $s->html('heading_first_line') !!}</span>
<span class="h-script">{!! $s->html('heading_script_line') !!}</span>
</h2>
<img class="uline uline--care" data-anim="draw" src="{{ asset('assets/img/underline.png') }}" width="804" height="66" alt="">
</div>
<p>{!! $s->html('text') !!}</p>
<a {!! $s->linkAttrs('button') !!} class="btn">{!! $s->linkLabel('button') !!}</a>
</div>
</section>
