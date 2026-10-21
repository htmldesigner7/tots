{{-- Generated from index.html — design-matched section. --}}
<section class="day" aria-labelledby="day-title{{ $s->uid }}">
<div class="wrap">
<div class="heading" data-anim="fade-up">
<h2 id="day-title{{ $s->uid }}">
<span class="h-display h-caps">{!! $s->html('heading_first_line') !!}</span>
<span class="h-script">{!! $s->html('heading_script_line') !!}</span>
</h2>
<img class="uline uline--day" data-anim="draw" src="{{ asset('assets/img/underline.png') }}" width="505" height="63" alt="">
</div>
<div class="day__board" data-anim="zoom">
<img {!! $s->img('image') !!} class="day__art" loading="lazy">
<figure class="dcap dcap--1" data-anim="fade"><span>{!! $s->html('text') !!}</span><p>{!! $s->html('text_2') !!}</p></figure>
<figure class="dcap dcap--2" data-anim="fade"><span>{!! $s->html('text_3') !!}</span><p>{!! $s->html('text_4') !!}</p></figure>
<figure class="dcap dcap--3" data-anim="fade"><span>{!! $s->html('text_5') !!}</span><p>{!! $s->html('text_6') !!}</p></figure>
<figure class="dcap dcap--4" data-anim="fade"><span>{!! $s->html('text_7') !!}</span><p>{!! $s->html('text_8') !!}</p></figure>
<div class="checklist" data-anim="pop">
<p class="checklist__title">{!! $s->html('checklist_title') !!}</p>
<ul>
@foreach($s->list('list_items') as $li)<li>{!! $li !!}</li>
@endforeach</ul>
</div>
</div>
</div>
</section>
