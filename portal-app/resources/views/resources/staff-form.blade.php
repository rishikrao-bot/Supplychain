@extends('layouts.app')
@section('title', $person->exists ? 'Edit '.$person->name : 'Add a person')
@section('content')
<div><a class="btn sm" href="{{ route('resources.staff') }}">← People</a><h1 style="margin-top:12px">{{ $person->exists ? 'Edit '.$person->name : 'Add a person' }}</h1></div>
<form method="post" action="{{ $person->exists ? route('resources.staff.update', $person) : route('resources.staff.store') }}" class="panel">@csrf @if($person->exists) @method('put') @endif
<div class="panel-b">
  <div class="grid g2">
    <div class="field"><label for="name">Name</label><input id="name" type="text" name="name" value="{{ old('name', $person->name) }}" required></div>
    <div class="field"><label for="position">Position</label><input id="position" type="text" name="position" value="{{ old('position', $person->position) }}" placeholder="e.g. MEP Manager"></div>
    <div class="field"><label for="team">Team</label><select id="team" name="team">@foreach(\App\Models\Staff::TEAMS as $t)<option @selected(old('team', $person->team) === $t)>{{ $t }}</option>@endforeach</select></div>
    <div class="field"><label for="days_per_week">Working days a week</label><input id="days_per_week" type="number" step="0.5" min="0.5" max="7" name="days_per_week" value="{{ old('days_per_week', $person->days_per_week) }}" required></div>
    <div class="field"><label for="starts_on">Start date (new starters)</label><input id="starts_on" type="date" name="starts_on" value="{{ old('starts_on', $person->starts_on?->format('Y-m-d')) }}"></div>
    <div class="field"><label for="leaves_on">Leaving date</label><input id="leaves_on" type="date" name="leaves_on" value="{{ old('leaves_on', $person->leaves_on?->format('Y-m-d')) }}"></div>
    <div class="field"><label for="user_id">Their portal login (optional)</label><select id="user_id" name="user_id"><option value="">None</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('user_id', $person->user_id) == $u->id)>{{ $u->name }} · {{ $u->email }}</option>@endforeach</select></div>
  </div>
  <label class="small row"><input type="checkbox" name="active" value="1" @checked(old('active', $person->active))> Show in the planner</label>
  <div class="row end"><a class="btn" href="{{ route('resources.staff') }}">Cancel</a><button class="btn primary">{{ $person->exists ? 'Save' : 'Add person' }}</button></div>
</div></form>
@endsection
