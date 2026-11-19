@extends('layouts.app')
@section('title', 'Competencies')
@section('content')
<div><h1>Competencies</h1><p class="muted">What partners can be offered. The number shows how many active partners hold each one; a competency with 0 is a gap in the supply chain.</p></div>
@foreach($groups as $g)
<div class="panel"><div class="panel-h"><h2>{{ $g->name }}</h2></div><div class="panel-b"><div>
@foreach($g->competencies as $c)<span class="ctag">{{ $c->name }}@if($c->certificate_required) <span class="muted">(needs {{ $c->certificate_required }})</span>@endif · <b>{{ $c->organisations_count }}</b></span>@endforeach
</div></div></div>
@endforeach
@if($canEdit)
<div class="panel"><div class="panel-h"><h2>Add a competency</h2></div>
<form method="post" action="{{ route('competencies.store') }}" class="panel-b">@csrf
  <div class="grid g2">
    <div class="field"><label for="name">Competency</label><input id="name" type="text" name="name" required placeholder="e.g. Solar PV, EV charging"></div>
    <div class="field"><label for="group_id">Group</label><select id="group_id" name="group_id"><option value="">New group…</option>@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
    <div class="field"><label for="new_group">New group name (if chosen)</label><input id="new_group" type="text" name="new_group" placeholder="e.g. Renewables"></div>
    <div class="field"><label for="certificate_required">Certificate required (blank if none)</label><input id="certificate_required" type="text" name="certificate_required" placeholder="e.g. MCS"></div>
  </div>
  <div class="row end"><button class="btn primary">Add competency</button></div>
</form></div>
@endif
@endsection
