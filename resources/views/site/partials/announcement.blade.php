@php($set = \App\Support\Settings::all())
<div class="cms-announce" role="region" aria-label="Announcement"><p>{{ $set['announcement_text'] }}@if($set['announcement_link_label'] && $set['announcement_url']) <a href="{{ \App\Support\SiteUrl::local($set['announcement_url']) }}">{{ $set['announcement_link_label'] }}</a>@endif</p></div>
