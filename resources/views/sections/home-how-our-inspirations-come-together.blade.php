{{-- Generated from index.html — design-matched section. --}}
<section class="more" id="{{ $s->anchor }}" aria-labelledby="more-title{{ $s->uid }}">
<img {!! $s->img('image') !!} class="more__bg" loading="lazy">
<div class="wrap more__top">
<div class="heading" data-anim="fade-up">
<h2 id="more-title{{ $s->uid }}">
<span class="h-display h-caps">{!! $s->html('heading_first_line') !!}</span>
<span class="h-script">{!! $s->html('heading_script_line') !!}</span>
</h2>
<img class="uline uline--more" data-anim="draw" src="{{ asset('assets/img/underline.png') }}" width="505" height="63" alt="">
</div>
<div class="more__intro" data-anim="fade-up"><p>{!! $s->html('text') !!}</p>
<ul class="more__chips">@foreach($s->list('list') as $li)<li>{!! $li !!}</li>@endforeach</ul></div>
</div>
</section>
