@php
    $eb = $errors->getBag('careers');
    $inv = fn ($f) => $eb->has($f) ? ' class="is-invalid" aria-invalid="true" aria-describedby="'.$prefix.'-'.$f.'-err"' : '';
    $err = fn ($f) => $eb->has($f) ? '<span class="cms-err" id="'.$prefix.'-'.$f.'-err">'.e($eb->first($f)).'</span>' : '';
    $positions = \App\Support\Settings::lines('careers_positions');
    $status = session('form_status.careers');
@endphp
<form action="{{ route('forms.careers') }}#{{ $prefix }}-form" aria-label="Job application form" class="form{{ $white ? ' form--white' : '' }}" enctype="multipart/form-data" method="post" novalidate="" id="{{ $prefix }}-form" data-ajax="1">
@csrf
<input type="hidden" name="_anchor" value="{{ $prefix }}-form">
<div class="f"><label for="{{ $prefix }}-name">Full Name <span aria-hidden="true" class="req">*</span></label><input autocomplete="name" id="{{ $prefix }}-name" name="name" placeholder="Your Name" required="" type="text" value="{{ old('name') }}" maxlength="120"{!! $inv('name') !!}>{!! $err('name') !!}</div>
<div class="f"><label for="{{ $prefix }}-phone">Phone <span aria-hidden="true" class="req">*</span></label><input autocomplete="tel" id="{{ $prefix }}-phone" name="phone" placeholder="Your Phone Number" required="" type="tel" value="{{ old('phone') }}" maxlength="30"{!! $inv('phone') !!}>{!! $err('phone') !!}</div>
<div class="f"><label for="{{ $prefix }}-email">Email <span aria-hidden="true" class="req">*</span></label><input autocomplete="email" id="{{ $prefix }}-email" name="email" placeholder="Your Email Address" required="" type="email" value="{{ old('email') }}" maxlength="150"{!! $inv('email') !!}>{!! $err('email') !!}</div>
<div class="f"><label for="{{ $prefix }}-pos">Position Applied For <span aria-hidden="true" class="req">*</span></label><select id="{{ $prefix }}-pos" name="position" required=""{!! $inv('position') !!}><option value="">Select a Position</option>@foreach($positions as $p)<option @selected(old('position') === $p)>{{ $p }}</option>@endforeach</select>{!! $err('position') !!}</div>
<div class="f"><label for="{{ $prefix }}-qual">Qualification <span aria-hidden="true" class="req">*</span></label><input id="{{ $prefix }}-qual" name="qualification" placeholder="e.g. ECCE, B.Ed." required="" type="text" value="{{ old('qualification') }}" maxlength="200"{!! $inv('qualification') !!}>{!! $err('qualification') !!}</div>
<div class="f"><label for="{{ $prefix }}-exp">Years of Experience</label><input id="{{ $prefix }}-exp" inputmode="numeric" name="experience" placeholder="Years" type="text" value="{{ old('experience') }}" maxlength="20"{!! $inv('experience') !!}>{!! $err('experience') !!}</div>
<div class="f"><label for="{{ $prefix }}-cv">Upload CV <span aria-hidden="true" class="req">*</span></label><input accept=".pdf,.doc,.docx" id="{{ $prefix }}-cv" name="cv" required="" type="file"{!! $inv('cv') !!}>{!! $err('cv') !!}</div>
<div class="f"><label for="{{ $prefix }}-photo">Upload Photograph</label><input accept="image/*" id="{{ $prefix }}-photo" name="photo" type="file"{!! $inv('photo') !!}>{!! $err('photo') !!}</div>
<div class="f f--full"><label for="{{ $prefix }}-msg">Message (Optional)</label><textarea id="{{ $prefix }}-msg" name="message" placeholder="Tell us a little about yourself…" maxlength="3000"{!! $inv('message') !!}>{{ old('message') }}</textarea>{!! $err('message') !!}</div>
<div class="cms-hp" aria-hidden="true"><label for="{{ $prefix }}-website">Leave this field empty</label><input id="{{ $prefix }}-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
<div class="f--full consent"><label><input name="consent" required="" type="checkbox" value="1" @checked(old('consent')){!! $inv('consent') !!}> <span>I agree to be contacted about my application.</span></label><button class="btn btn--wide" type="submit">Submit Application</button></div>
<p aria-live="polite" class="form-status" role="status">@if($status){{ $status }}@elseif($eb->any())Please fill in the highlighted fields.@endif</p>
</form>
