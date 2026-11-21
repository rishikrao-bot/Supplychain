@extends('layouts.app')
@section('title', $project->label().' · Resource plan')
@section('content')
@php
  use App\Services\ResourcePlan;
  $total = (float) ($days->total ?? 0); $toDate = (float) ($days->to_date ?? 0); $allowed = $project->days_allowed !== null ? (float) $project->days_allowed : null;
  $fmt = fn ($n) => rtrim(rtrim(number_format($n, 1), '0'), '.') ?: '0';
@endphp
<div class="page-head"><div><a class="btn sm" href="{{ route('resources.projects') }}">← Projects</a>
  <h1 style="margin-top:12px">{{ $project->label() }}</h1>
  <div class="row small muted" style="margin-top:4px">@if($project->status === 'complete')<span class="chip">Complete</span>@endif @if($project->type)<span class="chip info">{{ $project->type }}</span>@endif
  @if($project->clientLabel())<span>{{ $project->clientLabel() }}</span>@endif @if($project->lead)<span>Lead: {{ $project->lead->name }}</span>@endif
  @if($project->completion_on)<span>Completion {{ $project->completion_on->format('j M Y') }}</span>@elseif($project->completion_note)<span>Completion {{ $project->completion_note }}</span>@endif</div></div>
  @if($canEdit)<a class="btn" href="{{ route('resources.projects.edit', $project) }}">Edit project</a>@endif</div>
<div class="grid g4">
  <div class="panel stat"><span class="eyebrow">Days allowed</span><span class="v">{{ $allowed !== null ? $fmt($allowed) : '—' }}</span></div>
  <div class="panel stat"><span class="eyebrow">Planned in total</span><span class="v">{{ $fmt($total) }}</span>@if($allowed)<span class="small {{ $total > $allowed ? 'overdue' : 'muted' }}">{{ $total > $allowed ? $fmt($total - $allowed).' over allowance' : $fmt($allowed - $total).' left' }}</span>@endif</div>
  <div class="panel stat"><span class="eyebrow">Up to this week</span><span class="v">{{ $fmt($toDate) }}</span></div>
  <div class="panel stat"><span class="eyebrow">Still to come</span><span class="v">{{ $fmt($total - $toDate) }}</span></div>
</div>
@include('resources._weeknav')
<form method="post" action="{{ route('resources.save') }}" class="panel">@csrf
<div class="tbl"><table class="plan">
<thead><tr><th class="sticky" style="text-align:left">Person</th>@foreach($weeks as $w)<th class="{{ $w->toDateString() === $thisWeek ? 'now' : '' }}">w/e<br>{{ $w->format('j M') }}</th>@endforeach</tr></thead>
<tbody>
@forelse($people as $s)
<tr><td class="sticky"><a href="{{ route('resources.person', ['staff' => $s] + request()->only('from', 'weeks')) }}">{{ $s->name }}</a><div class="small muted">{{ $s->position }}</div></td>
@foreach($weeks as $w)
  @php($v = $grid[$s->id][$w->toDateString()] ?? null)
  @php($all = $totals[$s->id][$w->toDateString()] ?? 0)
  <td class="c {{ $w->toDateString() === $thisWeek ? 'now' : '' }}" title="{{ $s->name }} is {{ round(100 * $all / max(0.5, (float) $s->days_per_week)) }}% booked this week">@if($canEdit)<input class="cell {{ $all > (float) $s->days_per_week ? 'u-over' : '' }}" type="number" min="0" max="7" step="0.5" name="cells[{{ $project->id }}-{{ $s->id }}][{{ $w->toDateString() }}]" value="{{ $v !== null ? $fmt($v) : '' }}" aria-label="{{ $s->name }}, w/e {{ $w->format('j M') }}, days">@else{{ $v !== null ? $fmt($v) : '' }}@endif</td>
@endforeach</tr>
@empty
<tr><td class="sticky muted">Nobody planned in these weeks.</td><td colspan="{{ count($weeks) }}"></td></tr>
@endforelse
</tbody></table></div>
@if($canEdit)<div class="panel-b row" style="justify-content:space-between">
  <span class="small muted">Red boxes: that person is over 100% across all their projects that week.</span>
  <div class="row"><select aria-label="Add a person row" onchange="if(this.value) location.href=this.value"><option value="">+ Add a person…</option>@foreach($allStaff->whereNotIn('id', $people->pluck('id')) as $s)<option value="{{ request()->fullUrlWithQuery(['add' => $s->id]) }}">{{ $s->name }}</option>@endforeach</select>
  <button class="btn primary">Save changes</button></div></div>@endif
</form>
@if($canEdit)@include('resources._fill', ['fixedProject' => $project, 'staffList' => $allStaff])@endif
@if($project->notes)<div class="panel"><div class="panel-b small" style="white-space:pre-line">{{ $project->notes }}</div></div>@endif
@endsection
