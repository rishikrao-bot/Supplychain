@extends('layouts.app')
@section('title', 'Tasks')
@section('content')
@php($me = auth()->user())
<div class="page-head"><div><h1>Tasks</h1><p class="muted">Follow-ups for the team. Each person's tasks also appear in their Microsoft To Do, in a list called "Compass MEP Portal".</p></div></div>
@error('microsoft')<div class="note crit" role="alert">{{ $message }}</div>@enderror

<div class="panel"><div class="panel-b row" style="justify-content:space-between">
@if($me->hasMicrosoft())
  <div><b>Outlook &amp; To Do connected</b><div class="small muted">Changes sync both ways every 5 minutes.@if($me->todo_synced_at) Last sync {{ $me->todo_synced_at->diffForHumans() }}.@endif</div></div>
  <div class="row"><form method="post" action="{{ route('tasks.sync') }}">@csrf<button class="btn sm">Sync now</button></form>
  <form method="post" action="{{ route('tasks.disconnect') }}" onsubmit="return confirm('Disconnect Outlook and To Do?')">@csrf<button class="btn sm">Disconnect</button></form></div>
@elseif($microsoftReady)
  <div><b>Connect Outlook &amp; To Do</b><div class="small muted">See your tasks in Microsoft To Do and your emails with clients on their CRM pages. You'll sign in with your Compass Microsoft 365 account.</div></div>
  <a class="btn primary" href="{{ route('login.microsoft') }}">Connect</a>
@else
  <div class="small muted">Microsoft To Do sync will be available once Microsoft 365 is set up for the portal.</div>
@endif
</div></div>

<div class="panel"><form method="post" action="{{ route('tasks.store') }}" class="panel-b">@csrf
  <div class="row"><input type="text" name="title" required placeholder="Add a task, e.g. Call Jo at Wates about the Castle Point quote" aria-label="New task" style="flex:1;min-width:220px">
  <select name="assigned_to" aria-label="For" style="width:auto">@foreach($staff as $s)<option value="{{ $s->id }}" @selected($s->id === $me->id)>{{ $s->id === $me->id ? 'Me' : $s->name }}</option>@endforeach</select>
  <input type="date" name="due_on" aria-label="Due date" style="width:auto"><button class="btn primary">Add</button></div>
</form></div>

<div class="tabs">@foreach(['mine' => 'My open tasks ('.$counts['mine'].')', 'all' => 'Everyone\'s open tasks ('.$counts['all'].')', 'done' => 'Done (60 days)'] as $k => $l)<a class="btn sm {{ $show === $k ? 'primary' : '' }}" href="{{ route('tasks.index', ['show' => $k]) }}">{{ $l }}</a>@endforeach</div>
<div class="panel"><div class="tbl"><table>
<thead><tr><th></th><th>Task</th><th>About</th>@if($show !== 'mine')<th>For</th>@endif<th>{{ $show === 'done' ? 'Done' : 'Due' }}</th><th></th></tr></thead><tbody>
@forelse($tasks as $t)
<tr class="{{ $t->isDone() ? 'task-done' : '' }}">
  <td style="width:1%"><form method="post" action="{{ route('tasks.toggle', $t) }}">@csrf<button class="btn sm" aria-label="{{ $t->isDone() ? 'Reopen' : 'Mark done' }}: {{ $t->title }}">{{ $t->isDone() ? '↺' : '✓' }}</button></form></td>
  <td><a class="t" href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a>@if($t->todo_error)<div class="small" style="color:var(--crit)">Not in To Do yet: {{ \Illuminate\Support\Str::limit($t->todo_error, 100) }}</div>@endif</td>
  <td class="small">@if($t->opportunity)<a href="{{ route('pipeline.show', $t->opportunity) }}">{{ $t->opportunity->title }}</a>@elseif($t->contact)<a href="{{ route('contacts.show', $t->contact) }}">{{ $t->contact->name() }}</a>@elseif($t->organisation)<a href="{{ route('companies.show', $t->organisation) }}">{{ $t->organisation->name }}</a>@else<span class="muted">—</span>@endif</td>
  @if($show !== 'mine')<td class="small">{{ $t->assignee?->name ?? 'Unassigned' }}</td>@endif
  <td class="small {{ $t->isOverdue() ? 'overdue' : '' }}">{{ $show === 'done' ? $t->completed_at?->format('j M') : ($t->due_on?->format('D j M') ?? '—') }}</td>
  <td class="small muted">@if($t->todo_task_id)<span title="In Microsoft To Do">To Do ✓</span>@endif</td>
</tr>
@empty<tr><td colspan="6" class="muted">{{ $show === 'mine' ? 'Nothing on your list. Nice.' : 'No tasks here.' }}</td></tr>@endforelse
</tbody></table></div></div>
@endsection
