{{-- Tasks on a record plus a quick-add form. Needs $tasks, $staff and $links. --}}
<div class="panel"><div class="panel-h"><h2>Tasks</h2><span class="small muted">{{ $tasks->where('status', 'open')->count() }} open</span></div>
@if($tasks->isNotEmpty())
<div class="tbl"><table><tbody>
@foreach($tasks as $t)
<tr class="{{ $t->isDone() ? 'task-done' : '' }}">
  <td style="width:1%"><form method="post" action="{{ route('tasks.toggle', $t) }}">@csrf<button class="btn sm" aria-label="{{ $t->isDone() ? 'Reopen' : 'Mark done' }}: {{ $t->title }}">{{ $t->isDone() ? '↺' : '✓' }}</button></form></td>
  <td><a class="t" href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a><div class="small muted">{{ $t->assignee?->name ?? 'Unassigned' }}</div></td>
  <td class="small num {{ $t->isOverdue() ? 'overdue' : '' }}">{{ $t->due_on?->format('j M') }}</td>
</tr>
@endforeach
</tbody></table></div>@endif
<details class="add panel-b" @if($tasks->isEmpty()) open @endif><summary class="btn sm">+ Add a task</summary>
<form method="post" action="{{ route('tasks.store') }}" class="grid" style="margin-top:10px">@csrf
  @foreach($links as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
  <input type="text" name="title" required placeholder="e.g. Chase quote, book site visit" aria-label="Task">
  <div class="grid g2">
    <select name="assigned_to" aria-label="For">@foreach($staff as $s)<option value="{{ $s->id }}" @selected($s->id === auth()->id())>{{ $s->name }}</option>@endforeach</select>
    <input type="date" name="due_on" aria-label="Due">
  </div>
  <div class="row end"><button class="btn primary sm">Add task</button></div>
</form></details>
</div>
