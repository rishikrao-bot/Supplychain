@extends('layouts.app')
@section('title', $contact->exists ? 'Edit '.$contact->name() : 'Add a person')
@section('content')
@php($back = $contact->exists ? route('contacts.show', $contact) : ($contact->organisation_id ? route('companies.show', $contact->organisation_id) : route('contacts.index')))
<div><a class="btn sm" href="{{ $back }}">← Back</a><h1 style="margin-top:12px">{{ $contact->exists ? 'Edit '.$contact->name() : 'Add a person' }}</h1></div>
<form method="post" action="{{ $contact->exists ? route('contacts.update', $contact) : route('contacts.store') }}" class="panel">@csrf @if($contact->exists) @method('put') @endif
<div class="panel-b">
  <div class="grid g2">
    <div class="field"><label for="first_name">First name</label><input id="first_name" type="text" name="first_name" value="{{ old('first_name', $contact->first_name) }}" required></div>
    <div class="field"><label for="last_name">Last name</label><input id="last_name" type="text" name="last_name" value="{{ old('last_name', $contact->last_name) }}"></div>
    <div class="field"><label for="organisation_id">Company</label><select id="organisation_id" name="organisation_id"><option value="">None</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('organisation_id', $contact->organisation_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label for="job_title">Job title</label><input id="job_title" type="text" name="job_title" value="{{ old('job_title', $contact->job_title) }}"></div>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $contact->email) }}"></div>
    <div class="field"><label for="mobile">Mobile</label><input id="mobile" type="tel" name="mobile" value="{{ old('mobile', $contact->mobile) }}"></div>
    <div class="field"><label for="phone">Phone</label><input id="phone" type="tel" name="phone" value="{{ old('phone', $contact->phone) }}"></div>
    <div class="field"><label for="owner_id">Owner</label><select id="owner_id" name="owner_id"><option value="">Nobody</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected(old('owner_id', $contact->owner_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
  </div>
  <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes">{{ old('notes', $contact->notes) }}</textarea></div>
  <div class="row end"><a class="btn" href="{{ $back }}">Cancel</a><button class="btn primary">{{ $contact->exists ? 'Save' : 'Add person' }}</button></div>
</div></form>
@endsection
