@php
    $eb = $errors->getBag('enquiry');
    $inv = fn ($f) => $eb->has($f) ? ' class="is-invalid" aria-invalid="true" aria-describedby="'.$prefix.'-'.$f.'-err"' : '';
    $err = fn ($f) => $eb->has($f) ? '<span class="cms-err" id="'.$prefix.'-'.$f.'-err">'.e($eb->first($f)).'</span>' : '';
    $centres = \App\Support\Settings::lines('enquiry_centres');
    $programmes = \App\Support\Settings::lines('enquiry_programmes');
    $type = old('type', request()->query('type') === 'visit' ? 'visit' : 'general');
    $status = session('form_status.enquiry');
@endphp
<form action="{{ route('forms.enquiry') }}#{{ $prefix }}-form" aria-label="Enquiry form" class="form{{ $white ? ' form--white' : '' }}" method="post" novalidate="" id="{{ $prefix }}-form" data-ajax="1">
@csrf
<input type="hidden" name="_anchor" value="{{ $prefix }}-form">
<div class="f"><label for="{{ $prefix }}-name">Parent / Caregiver Name <span aria-hidden="true" class="req">*</span></label><input autocomplete="name" id="{{ $prefix }}-name" name="name" placeholder="Your Name" required="" type="text" value="{{ old('name') }}" maxlength="120"{!! $inv('name') !!}>{!! $err('name') !!}</div>
<div class="f"><label for="{{ $prefix }}-phone">Phone Number <span aria-hidden="true" class="req">*</span></label><input autocomplete="tel" id="{{ $prefix }}-phone" name="phone" placeholder="Your Phone Number" required="" type="tel" value="{{ old('phone') }}" maxlength="30"{!! $inv('phone') !!}>{!! $err('phone') !!}</div>
<div class="f"><label for="{{ $prefix }}-email">Email Address (Optional)</label><input autocomplete="email" id="{{ $prefix }}-email" name="email" placeholder="Your Email Address" type="email" value="{{ old('email') }}" maxlength="150"{!! $inv('email') !!}>{!! $err('email') !!}</div>
<div class="f"><label for="{{ $prefix }}-centre">Preferred Centre <span aria-hidden="true" class="req">*</span></label><select id="{{ $prefix }}-centre" name="centre" required=""{!! $inv('centre') !!}><option value="">Select a Centre</option>@foreach($centres as $c)<option @selected(old('centre') === $c)>{{ $c }}</option>@endforeach</select>{!! $err('centre') !!}</div>
<div class="f"><label for="{{ $prefix }}-prog">Programme of Interest <span aria-hidden="true" class="req">*</span></label><select id="{{ $prefix }}-prog" name="programme" required=""{!! $inv('programme') !!}><option value="">Select a Programme</option>@foreach($programmes as $p)<option value="{{ $p }}" @selected(old('programme') === $p)>{{ $p }}</option>@endforeach</select>{!! $err('programme') !!}</div>
<div class="f"><label for="{{ $prefix }}-age">Child’s Age (Optional)</label><input id="{{ $prefix }}-age" name="age" placeholder="Years and Months" type="text" value="{{ old('age') }}" maxlength="40"{!! $inv('age') !!}>{!! $err('age') !!}</div>
<fieldset class="f f--full"><legend>Enquiry Type <span aria-hidden="true" class="req">*</span></legend><div class="radios"><label><input @checked($type === 'general') name="type" type="radio" value="general"> General Enquiry</label><label><input @checked($type === 'visit') name="type" type="radio" value="visit"> Arrange a Visit</label></div></fieldset>
<div class="f f--full"><label for="{{ $prefix }}-msg">Your Message (Optional)</label><textarea id="{{ $prefix }}-msg" name="message" placeholder="Tell us a little about your enquiry…" maxlength="3000"{!! $inv('message') !!}>{{ old('message') }}</textarea>{!! $err('message') !!}</div>
<div class="cms-hp" aria-hidden="true"><label for="{{ $prefix }}-website">Leave this field empty</label><input id="{{ $prefix }}-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
<div class="f--full consent"><label><input name="consent" required="" type="checkbox" value="1" @checked(old('consent')){!! $inv('consent') !!}> <span>By submitting this form, you agree to be contacted about your enquiry.{!! $breakConsent ?? false ? '<br>' : ' ' !!}Read our <a href="{{ \App\Support\SiteUrl::local(\App\Support\Settings::get('privacy_url')) }}">Privacy Policy</a>.</span></label><button class="btn" type="submit">Send Enquiry</button></div>
<p aria-live="polite" class="form-status" role="status">@if($status){{ $status }}@elseif($eb->any())Please fill in the highlighted fields.@endif</p>
</form>
