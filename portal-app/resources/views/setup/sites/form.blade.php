@extends('layouts.app')
@section('title', $site->exists ? 'Edit site' : 'Add a site')
@section('content')
<div><a class="btn sm" href="{{ route('sites.index') }}">← Sites</a><h1 style="margin-top:12px">{{ $site->exists ? 'Edit '.$site->name : 'Add a site' }}</h1></div>
<form method="post" action="{{ $site->exists ? route('sites.update', $site) : route('sites.store') }}" class="panel">@csrf @if($site->exists) @method('put') @endif
<div class="panel-b">
  <div class="grid g2">
    <div class="field"><label for="contract_id">Client and contract</label><select id="contract_id" name="contract_id" required>@foreach($contracts as $c)<option value="{{ $c->id }}" @selected(old('contract_id', $site->contract_id) == $c->id)>{{ $c->client->name }} · {{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label for="name">Site name</label><input id="name" type="text" name="name" value="{{ old('name', $site->name) }}" required></div>
    <div class="field"><label for="address">Address</label><input id="address" type="text" name="address" value="{{ old('address', $site->address) }}"></div>
    <div class="field"><label for="postcode">Postcode</label><input id="postcode" type="text" name="postcode" value="{{ old('postcode', $site->postcode) }}"></div>
    <div class="field"><label for="zone">Zone</label><select id="zone" name="zone">@foreach(['A' => 'A: London, Essex, Kent, Herts (travel included)', 'B' => 'B: up to 90 minutes away (+£75 a visit)', 'C' => 'C: further (travel quoted)'] as $z => $l)<option value="{{ $z }}" @selected(old('zone', $site->zone) === $z)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="site_type">Type of site</label><input id="site_type" type="text" name="site_type" value="{{ old('site_type', $site->site_type) }}" placeholder="e.g. Offices, Leisure centre, Swimming pool" list="site-types"><datalist id="site-types"><option>Offices</option><option>Leisure centre</option><option>Swimming pool</option><option>Community hall</option><option>Depot</option></datalist></div>
    <div class="field"><label for="contact_name">Site contact</label><input id="contact_name" type="text" name="contact_name" value="{{ old('contact_name', $site->contact_name) }}"></div>
    <div class="field"><label for="contact_phone">Contact phone</label><input id="contact_phone" type="tel" name="contact_phone" value="{{ old('contact_phone', $site->contact_phone) }}"></div>
    <div class="field"><label for="latitude">Latitude (for GPS check-in)</label><input id="latitude" type="text" name="latitude" value="{{ old('latitude', $site->latitude) }}" placeholder="e.g. 52.1010"></div>
    <div class="field"><label for="longitude">Longitude</label><input id="longitude" type="text" name="longitude" value="{{ old('longitude', $site->longitude) }}" placeholder="e.g. -1.2010"></div>
  </div>
  <div class="field"><label for="access_notes">Access notes</label><textarea id="access_notes" name="access_notes">{{ old('access_notes', $site->access_notes) }}</textarea></div>
  <div class="field"><label for="asbestos_notes">Asbestos register notes</label><textarea id="asbestos_notes" name="asbestos_notes">{{ old('asbestos_notes', $site->asbestos_notes) }}</textarea></div>
  <div class="field"><label for="notes">Other notes</label><textarea id="notes" name="notes">{{ old('notes', $site->notes) }}</textarea></div>
  <div class="row end"><a class="btn" href="{{ route('sites.index') }}">Cancel</a><button class="btn primary">{{ $site->exists ? 'Save' : 'Add site' }}</button></div>
</div></form>
@endsection
