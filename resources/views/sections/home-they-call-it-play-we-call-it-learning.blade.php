{{-- Generated from index.html — design-matched section. --}}
<section class="play" id="{{ $s->anchor }}" aria-labelledby="play-title{{ $s->uid }}">
<img class="play__edge play__edge--top" src="{{ asset('assets/img/dark-top.webp') }}" width="1440" height="70" loading="lazy" alt="">
<div class="play__body">
<div class="wrap">
<div class="play__top">
<div class="heading" data-anim="fade-up">
<h2 id="play-title{{ $s->uid }}">
<span class="h-display">{!! $s->html('heading_first_line') !!}</span>
<span class="h-script">{!! $s->html('heading_script_line') !!}</span>
</h2>
<img class="uline uline--play" data-anim="draw" src="{{ asset('assets/img/underline.png') }}" width="505" height="63" alt="">
</div>
<p class="play__intro" data-anim="fade-up">{!! $s->html('text') !!}</p>
</div>
<div class="play__media" data-anim="zoom">
<img {!! $s->img('image') !!} class="play__art" loading="lazy">
<ul class="play__labels">
@foreach($s->list('list') as $li)<li>{!! $li !!}</li>@endforeach</ul>
<p class="play__line">{!! $s->html('text_2') !!}</p>
</div>
<a {!! $s->linkAttrs('button') !!} class="btn btn--wide play__btn" data-anim="fade-up">{!! $s->linkLabel('button') !!}</a>
<img class="play__plane" data-anim="slide-l" src="{{ asset('assets/img/plane-doodle.png') }}" width="240" height="128" loading="lazy" alt="">
</div>
</div>
<img class="play__edge play__edge--bottom" src="{{ asset('assets/img/dark-bottom.webp') }}" width="1440" height="62" loading="lazy" alt="">
</section>
