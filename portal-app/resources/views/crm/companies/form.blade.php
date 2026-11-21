@extends('layouts.app')
@section('title', $company->exists ? 'Edit '.$company->name : 'Add a company')
@section('content')
@php
  $types = \App\Models\Organisation::CRM_TYPES;
  if (! $company->isPartner()) unset($types['partner']);
  if ($company->isPartner()) $types = ['partner' => $types['partner']];
@endphp
<div><a class="btn sm" href="{{ $company->exists ? route('companies.show', $company) : route('companies.index') }}">← Back</a><h1 style="margin-top:12px">{{ $company->exists ? 'Edit '.$company->name : 'Add a company' }}</h1>
@if(! $company->exists)<p class="muted small">Supply chain partners are added under Contractors, where their compliance and rates are set up.</p>@endif</div>
<form method="post" action="{{ $company->exists ? route('companies.update', $company) : route('companies.store') }}" class="panel">@csrf @if($company->exists) @method('put') @endif
<div class="panel-b">
  <div class="grid g2">
    <div class="field"><label for="name">Company name</label><input id="name" type="text" name="name" value="{{ old('name', $company->name) }}" required></div>
    <div class="field"><label for="type">Type</label><select id="type" name="type">@foreach($types as $k => $l)<option value="{{ $k }}" @selected(old('type', $company->type) === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="phone">Phone</label><input id="phone" type="tel" name="phone" value="{{ old('phone', $company->phone) }}"></div>
    <div class="field"><label for="email">Main email</label><input id="email" type="email" name="email" value="{{ old('email', $company->email) }}"></div>
    <div class="field"><label for="website">Website</label><input id="website" type="text" name="website" value="{{ old('website', $company->website) }}" placeholder="e.g. example.co.uk"></div>
    <div class="field"><label for="sector">Sector</label><input id="sector" type="text" name="sector" value="{{ old('sector', $company->sector) }}" list="sectors" placeholder="e.g. Main contractor, Local authority"><datalist id="sectors"><option>Main contractor</option><option>Local authority</option><option>Housing association</option><option>Facilities management</option><option>Education</option><option>Healthcare</option><option>Commercial property</option></datalist></div>
    <div class="field"><label for="address">Address</label><input id="address" type="text" name="address" value="{{ old('address', $company->address) }}"></div>
    <div class="field"><label for="postcode">Postcode</label><input id="postcode" type="text" name="postcode" value="{{ old('postcode', $company->postcode) }}"></div>
    <div class="field"><label for="owner_id">Account owner</label><select id="owner_id" name="owner_id"><option value="">Nobody</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected(old('owner_id', $company->owner_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
  </div>
  <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes">{{ old('notes', $company->notes) }}</textarea></div>
  <div class="row end"><a class="btn" href="{{ $company->exists ? route('companies.show', $company) : route('companies.index') }}">Cancel</a><button class="btn primary">{{ $company->exists ? 'Save' : 'Add company' }}</button></div>
</div></form>
@endsection
