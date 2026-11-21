@extends('layouts.app')
@section('title', $opp->exists ? 'Edit '.$opp->title : 'Add enquiry or tender')
@section('content')
@php($back = $opp->exists ? route('pipeline.show', $opp) : route('pipeline.index'))
<div><a class="btn sm" href="{{ $back }}">← Back</a><h1 style="margin-top:12px">{{ $opp->exists ? 'Edit '.$opp->title : 'Add enquiry or tender' }}</h1></div>
<form method="post" action="{{ $opp->exists ? route('pipeline.update', $opp) : route('pipeline.store') }}" class="panel">@csrf @if($opp->exists) @method('put') @endif
<div class="panel-b">
  <div class="field"><label for="title">Title</label><input id="title" type="text" name="title" value="{{ old('title', $opp->title) }}" required placeholder="e.g. Castle Point reactive M&E framework 2027"></div>
  <div class="grid g2">
    <div class="field"><label for="kind">Type</label><select id="kind" name="kind">@foreach(\App\Models\Opportunity::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind', $opp->kind) === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="stage">Stage</label><select id="stage" name="stage">@foreach(\App\Models\Opportunity::STAGES as $k => [$l])<option value="{{ $k }}" @selected(old('stage', $opp->stage) === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="organisation_id">Company</label><select id="organisation_id" name="organisation_id"><option value="">None</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('organisation_id', $opp->organisation_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label for="contact_id">Contact</label><select id="contact_id" name="contact_id"><option value="">None</option>@foreach($contacts as $c)<option value="{{ $c->id }}" @selected(old('contact_id', $opp->contact_id) == $c->id)>{{ $c->name() }}{{ $c->organisation ? ' · '.$c->organisation->name : '' }}</option>@endforeach</select></div>
    <div class="field"><label for="value">Value (£)</label><input id="value" type="number" step="0.01" min="0" name="value" value="{{ old('value', $opp->value) }}"></div>
    <div class="field"><label for="probability">Chance of winning (%)</label><input id="probability" type="number" min="0" max="100" name="probability" value="{{ old('probability', $opp->probability) }}"></div>
    <div class="field"><label for="return_date">Return / quote due date</label><input id="return_date" type="date" name="return_date" value="{{ old('return_date', $opp->return_date?->format('Y-m-d')) }}"></div>
    <div class="field"><label for="decision_date">Decision expected</label><input id="decision_date" type="date" name="decision_date" value="{{ old('decision_date', $opp->decision_date?->format('Y-m-d')) }}"></div>
    <div class="field"><label for="owner_id">Owner</label><select id="owner_id" name="owner_id"><option value="">Nobody</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected(old('owner_id', $opp->owner_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div class="field"><label for="lost_reason">If lost, why</label><input id="lost_reason" type="text" name="lost_reason" value="{{ old('lost_reason', $opp->lost_reason) }}" placeholder="e.g. Price, scope, went with incumbent"></div>
  </div>
  <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes">{{ old('notes', $opp->notes) }}</textarea></div>
  <p class="small muted">Marking a prospect's tender as won makes that company a client.</p>
  <div class="row end"><a class="btn" href="{{ $back }}">Cancel</a><button class="btn primary">{{ $opp->exists ? 'Save' : 'Add' }}</button></div>
</div></form>
@endsection
