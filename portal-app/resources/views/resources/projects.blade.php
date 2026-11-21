@extends('layouts.app')
@section('title', 'Projects')
@section('content')
@php($fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.') ?: '0')
<div class="page-head"><div><h1>Projects</h1><p class="muted">One list for everyone. A new project appears for every person straight away.</p></div>
  @if($canEdit)<a class="btn primary" href="{{ route('resources.projects.create') }}">+ New project</a>@endif</div>
<div class="row" style="justify-content:space-between">
  <div class="tabs"><a class="btn sm {{ $show === 'active' ? 'primary' : '' }}" href="{{ route('resources.projects') }}">Active</a><a class="btn sm {{ $show === 'complete' ? 'primary' : '' }}" href="{{ route('resources.projects', ['show' => 'complete']) }}">Complete</a></div>
  <form method="get" class="row">@if($show === 'complete')<input type="hidden" name="show" value="complete">@endif<input type="search" name="q" value="{{ $q }}" placeholder="Search number, name or client" aria-label="Search projects" style="max-width:280px"><button class="btn sm">Search</button></form>
</div>
<div class="panel"><div class="tbl"><table>
<thead><tr><th>Project</th><th>Client</th><th>Type</th><th>Lead</th><th class="num">Days allowed</th><th class="num">Planned</th><th class="num">To date</th><th>Completion</th></tr></thead><tbody>
@forelse($projects as $p)
@php($d = $days[$p->id] ?? null)
<tr><td><a href="{{ route('resources.project', $p) }}"><b>{{ $p->number ?: 'TBC' }}</b> {{ $p->name }}</a>@if($p->internal)<div class="small muted">Not a project: holiday, training or overhead</div>@endif</td>
<td class="small">{{ $p->clientLabel() ?? '—' }}</td><td class="small">{{ $p->type ?? '—' }}</td><td class="small">{{ $p->lead?->name ?? '—' }}</td>
<td class="num">{{ $p->days_allowed !== null ? $fmt($p->days_allowed) : '—' }}</td>
<td class="num {{ $p->days_allowed !== null && $d && $d->total > $p->days_allowed ? 'overdue' : '' }}">{{ $d ? $fmt($d->total) : '—' }}</td>
<td class="num">{{ $d ? $fmt($d->to_date) : '—' }}</td>
<td class="small">{{ $p->completion_on?->format('j M Y') ?? ($p->completion_note ?: '—') }}</td></tr>
@empty<tr><td colspan="8" class="muted">No projects{{ $q ? ' match "'.$q.'"' : '' }}.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
