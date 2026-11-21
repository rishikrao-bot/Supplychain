@extends('layouts.app')
@section('title', $dashboard->title)
@section('content')
<div class="page-head"><div><a class="btn sm" href="{{ route('reports.index') }}">← Management reports</a><h1 style="margin-top:12px">{{ $dashboard->title }}</h1>
@if($dashboard->description)<p class="muted">{{ $dashboard->description }}</p>@endif</div>
<div class="row">@if($others->count() > 1)<select aria-label="Other reports" onchange="if(this.value) location.href=this.value">@foreach($others as $o)<option value="{{ route('reports.show', $o) }}" @selected($o->id === $dashboard->id)>{{ $o->title }}</option>@endforeach</select>@endif
<a class="btn" href="{{ $dashboard->embed_url }}" target="_blank" rel="noopener">Open full screen</a></div></div>
<iframe class="report-frame" title="{{ $dashboard->title }}" src="{{ $dashboard->embed_url }}" allowfullscreen="true" loading="lazy"></iframe>
<p class="small muted">Blank or asking you to sign in? Use your work Microsoft account. If it says you don't have access, ask for the report to be shared with you in Power BI.</p>
@endsection
