@extends('layouts.app')
@section('title', 'People')
@section('content')
<div class="page-head"><div><a class="btn sm" href="{{ route('resources.index') }}">← Resource planner</a><h1 style="margin-top:12px">People</h1>
<p class="muted">Everyone whose time is planned. Adding someone here adds them to the planner; no sheet to copy or link.</p></div>
@if($canEdit)<a class="btn primary" href="{{ route('resources.staff.create') }}">+ Add a person</a>@endif</div>
<div class="panel"><div class="tbl"><table>
<thead><tr><th>Name</th><th>Position</th><th>Team</th><th class="num">Days/week</th><th>Dates</th><th>Portal login</th><th></th></tr></thead><tbody>
@forelse($staff as $s)
<tr class="{{ $s->active ? '' : 'task-done' }}"><td><a href="{{ route('resources.person', $s) }}"><b>{{ $s->name }}</b></a>@unless($s->active)<div class="small muted">Not in the planner</div>@endunless</td>
<td class="small">{{ $s->position }}</td><td class="small">{{ $s->team }}</td><td class="num">{{ rtrim(rtrim($s->days_per_week, '0'), '.') }}</td>
<td class="small">@if($s->starts_on)Starts {{ $s->starts_on->format('j M Y') }}@endif @if($s->leaves_on)Leaves {{ $s->leaves_on->format('j M Y') }}@endif</td>
<td class="small">{{ $s->user?->email ?? '—' }}</td>
<td>@if($canEdit)<a class="btn sm" href="{{ route('resources.staff.edit', $s) }}">Edit</a>@endif</td></tr>
@empty<tr><td colspan="7" class="muted">Nobody yet.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
