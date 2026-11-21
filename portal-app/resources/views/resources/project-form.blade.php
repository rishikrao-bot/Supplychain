@extends('layouts.app')
@section('title', $project->exists ? 'Edit '.$project->label() : 'New project')
@section('content')
@php($back = $project->exists ? route('resources.project', $project) : route('resources.projects'))
<div><a class="btn sm" href="{{ $back }}">← Back</a><h1 style="margin-top:12px">{{ $project->exists ? 'Edit '.$project->label() : 'New project' }}</h1></div>
<form method="post" action="{{ $project->exists ? route('resources.projects.update', $project) : route('resources.projects.store') }}" class="panel">@csrf @if($project->exists) @method('put') @endif
<div class="panel-b">
  <div class="grid g2">
    <div class="field"><label for="number">Project number</label><input id="number" type="text" name="number" value="{{ old('number', $project->number) }}" placeholder="e.g. P0257 (blank while TBC)"></div>
    <div class="field"><label for="name">Project name</label><input id="name" type="text" name="name" value="{{ old('name', $project->name) }}" required></div>
    <div class="field"><label for="organisation_id">Client (from the CRM)</label><select id="organisation_id" name="organisation_id"><option value="">Not in the CRM</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('organisation_id', $project->organisation_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label for="client_name">…or client name</label><input id="client_name" type="text" name="client_name" value="{{ old('client_name', $project->client_name) }}"></div>
    <div class="field"><label for="type">Type</label><input id="type" type="text" name="type" value="{{ old('type', $project->type) }}" list="ptypes"><datalist id="ptypes">@foreach(\App\Models\Project::TYPES as $t)<option>{{ $t }}</option>@endforeach</datalist></div>
    <div class="field"><label for="lead_id">Compass lead</label><select id="lead_id" name="lead_id"><option value="">—</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected(old('lead_id', $project->lead_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div class="field"><label for="days_allowed">Days allowed</label><input id="days_allowed" type="number" step="0.5" min="0" name="days_allowed" value="{{ old('days_allowed', $project->days_allowed) }}"></div>
    <div class="field"><label for="completion_on">Completion date</label><input id="completion_on" type="date" name="completion_on" value="{{ old('completion_on', $project->completion_on?->format('Y-m-d')) }}"></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="active" @selected(old('status', $project->status) === 'active')>Active</option><option value="complete" @selected(old('status', $project->status) === 'complete')>Complete (hidden from the "add a project" lists)</option></select></div>
  </div>
  <label class="small row"><input type="checkbox" name="internal" value="1" @checked(old('internal', $project->internal))> Not a client project (holiday, training, overhead)</label>
  <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes">{{ old('notes', $project->notes) }}</textarea></div>
  <div class="row end"><a class="btn" href="{{ $back }}">Cancel</a><button class="btn primary">{{ $project->exists ? 'Save' : 'Add project' }}</button></div>
</div></form>
@endsection
