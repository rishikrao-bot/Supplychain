@extends('layouts.app')
@section('title', $org->exists ? 'Edit contractor' : 'Add contractor')
@section('content')
@php($rates = $org->rates ?? [])
<div><a class="btn sm" href="{{ route('contractors.index') }}">← Contractors</a><h1 style="margin-top:12px">{{ $org->exists ? 'Edit '.$org->name : 'Add a contractor' }}</h1></div>
<form method="post" action="{{ $org->exists ? route('contractors.update', $org) : route('contractors.store') }}" class="grid">@csrf @if($org->exists) @method('put') @endif
<div class="panel"><div class="panel-h"><h2>Company</h2></div><div class="panel-b">
  <div class="grid g2">
    <div class="field"><label for="name">Company name</label><input id="name" type="text" name="name" value="{{ old('name', $org->name) }}" required></div>
    <div class="field"><label for="partner_type">Type</label><select id="partner_type" name="partner_type"><option value="subcontractor" @selected(old('partner_type', $org->partner_type) === 'subcontractor')>Subcontractor: fixed rates</option><option value="specialist" @selected(old('partner_type', $org->partner_type) === 'specialist')>Specialist: quotes per job</option></select></div>
    <div class="field"><label for="contact_name">Contact name</label><input id="contact_name" type="text" name="contact_name" value="{{ old('contact_name', $org->contact_name) }}"></div>
    <div class="field"><label for="contact_phone">Contact phone</label><input id="contact_phone" type="tel" name="contact_phone" value="{{ old('contact_phone', $org->contact_phone) }}"></div>
    <div class="field"><label for="contact_email">Contact email</label><input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $org->contact_email) }}"></div>
    <div class="field"><label for="address">Address</label><input id="address" type="text" name="address" value="{{ old('address', $org->address) }}"></div>
    <div class="field"><span class="small muted" style="font-weight:500">Zones covered</span><div class="opts">@foreach(['A', 'B', 'C'] as $z)<label class="opt"><input type="checkbox" name="zones[]" value="{{ $z }}" @checked(in_array($z, old('zones', $org->zones ?? [])))> Zone {{ $z }}</label>@endforeach</div></div>
    <div class="field"><label for="job_limit">Job value limit (£, blank for none)</label><input id="job_limit" type="number" step="50" min="0" name="job_limit" value="{{ old('job_limit', $org->job_limit) }}"></div>
  </div>
  <label class="opt" style="align-self:flex-start"><input type="checkbox" name="vat_registered" value="1" @checked(old('vat_registered', $org->vat_registered))> VAT registered</label>
</div></div>
<div class="panel"><div class="panel-h"><h2>Their rates</h2><span class="small muted">Subcontractors only. What you pay them; never shown to clients.</span></div><div class="panel-b"><div class="grid g4">
  <div class="field"><label for="rate_normal">Normal hours (£/h)</label><input id="rate_normal" type="number" step="0.01" min="0" name="rate_normal" value="{{ old('rate_normal', $rates['normal'] ?? '') }}"></div>
  <div class="field"><label for="rate_ooh">Out of hours (£/h)</label><input id="rate_ooh" type="number" step="0.01" min="0" name="rate_ooh" value="{{ old('rate_ooh', $rates['ooh'] ?? '') }}"></div>
  <div class="field"><label for="rate_sun">Sunday / bank holiday (£/h)</label><input id="rate_sun" type="number" step="0.01" min="0" name="rate_sun" value="{{ old('rate_sun', $rates['sun'] ?? '') }}"></div>
  <div class="field"><label for="rate_callout">Call-out (£)</label><input id="rate_callout" type="number" step="0.01" min="0" name="rate_callout" value="{{ old('rate_callout', $rates['callout'] ?? '') }}"></div>
</div></div></div>
<div class="panel"><div class="panel-h"><h2>Competencies</h2><span class="small muted">Tick everything they're competent in. Jobs are only offered to partners holding the competency, with any required certificate in date.</span></div><div class="panel-b"><div class="cgrid">
  @foreach($groups as $g)<fieldset><legend>{{ $g->name }}</legend><div class="opts">@foreach($g->competencies as $c)<label class="opt"><input type="checkbox" name="competencies[]" value="{{ $c->id }}" @checked(in_array($c->id, old('competencies', $selected)))> {{ $c->name }}@if($c->certificate_required) <span class="muted small">({{ $c->certificate_required }})</span>@endif</label>@endforeach</div></fieldset>@endforeach
</div></div></div>
<div class="panel"><div class="panel-b"><div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes">{{ old('notes', $org->notes) }}</textarea></div>
<div class="row end"><a class="btn" href="{{ route('contractors.index') }}">Cancel</a><button class="btn primary">{{ $org->exists ? 'Save' : 'Add contractor' }}</button></div></div></div>
</form>
@endsection
