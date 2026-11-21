@extends('layouts.app')
@section('title', $task->title)
@section('content')
<div><a class="btn sm" href="{{ route('tasks.index') }}">← Tasks</a><h1 style="margin-top:12px">{{ $task->title }}</h1>
<div class="row small muted" style="margin-top:4px"><span class="chip {{ $task->isDone() ? 'ok' : ($task->isOverdue() ? 'crit' : '') }}">{{ $task->isDone() ? 'Done' : ($task->isOverdue() ? 'Overdue' : 'Open') }}</span>
@if($task->todo_task_id)<span>In {{ $task->assignee?->name }}'s Microsoft To Do</span>@endif @if($task->dataverse_id)<span>Imported from the old CRM</span>@endif</div></div>
<form method="post" action="{{ route('tasks.update', $task) }}" class="panel">@csrf @method('put')
<div class="panel-b">
  <div class="field"><label for="title">Task</label><input id="title" type="text" name="title" value="{{ old('title', $task->title) }}" required></div>
  <div class="grid g2">
    <div class="field"><label for="assigned_to">For</label><select id="assigned_to" name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected(old('assigned_to', $task->assigned_to) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div class="field"><label for="due_on">Due</label><input id="due_on" type="date" name="due_on" value="{{ old('due_on', $task->due_on?->format('Y-m-d')) }}"></div>
    <div class="field"><label for="organisation_id">Company</label><select id="organisation_id" name="organisation_id"><option value="">None</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected(old('organisation_id', $task->organisation_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label for="contact_id">Contact</label><select id="contact_id" name="contact_id"><option value="">None</option>@foreach($contacts as $c)<option value="{{ $c->id }}" @selected(old('contact_id', $task->contact_id) == $c->id)>{{ $c->name() }}{{ $c->organisation ? ' · '.$c->organisation->name : '' }}</option>@endforeach</select></div>
    <div class="field"><label for="opportunity_id">Enquiry / tender</label><select id="opportunity_id" name="opportunity_id"><option value="">None</option>@foreach($opportunities as $o)<option value="{{ $o->id }}" @selected(old('opportunity_id', $task->opportunity_id) == $o->id)>{{ $o->title }}</option>@endforeach
      @if($task->opportunity && ! $opportunities->contains('id', $task->opportunity_id))<option value="{{ $task->opportunity_id }}" selected>{{ $task->opportunity->title }}</option>@endif</select></div>
  </div>
  <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes">{{ old('notes', $task->notes) }}</textarea></div>
  <div class="row end"><a class="btn" href="{{ route('tasks.index') }}">Cancel</a><button class="btn primary">Save</button></div>
</div></form>
<div class="row">
  <form method="post" action="{{ route('tasks.toggle', $task) }}">@csrf<button class="btn">{{ $task->isDone() ? 'Reopen' : 'Mark done' }}</button></form>
  <form method="post" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task? It is removed from To Do too.')">@csrf @method('delete')<button class="btn danger">Delete</button></form>
</div>
@endsection
