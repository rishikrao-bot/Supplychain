@extends('layouts.app')
@section('title', 'Resource planner')
@section('content')
@php use App\Services\ResourcePlan; @endphp
<div class="page-head"><div><h1>Resource planner</h1><p class="muted">How full everyone is, week by week. Click a name to see and change their projects.</p></div>
  <div class="row"><a class="btn" href="{{ route('resources.export', request()->only('from')) }}">Download for Excel</a>
  @if($canEdit)<a class="btn" href="{{ route('resources.staff') }}">People</a><a class="btn primary" href="{{ route('resources.projects.create') }}">+ New project</a>@endif</div></div>
@include('resources._weeknav')
<div class="legend"><span class="u-low">Under 50%</span><span class="u-part">50–89%</span><span class="u-full">90–100%</span><span class="u-over">Over 100%</span><span class="muted">Percent of each person's working days</span></div>
<div class="panel"><div class="tbl"><table class="plan">
<thead><tr><th class="sticky" style="text-align:left">Person</th>@foreach($weeks as $w)<th class="{{ $w->toDateString() === $thisWeek ? 'now' : '' }}">w/e<br>{{ $w->format('j M') }}</th>@endforeach</tr></thead>
<tbody>
@forelse($teams as $team => $people)
  <tr class="team"><td class="sticky">{{ $team }}</td><td colspan="{{ count($weeks) }}"></td></tr>
  @foreach($people as $s)
  <tr><td class="sticky"><a href="{{ route('resources.person', ['staff' => $s] + request()->only('from', 'weeks')) }}"><b>{{ $s->name }}</b></a><div class="small muted">{{ $s->position }}@if((float) $s->days_per_week !== 5.0) · {{ rtrim(rtrim($s->days_per_week, '0'), '.') }} days/wk @endif</div></td>
  @foreach($weeks as $w)
    @php($d = $totals[$s->id][$w->toDateString()] ?? 0)
    @php($absent = $s->absentIn($w))
    @php($pct = $absent ? null : round(100 * $d / max(0.5, (float) $s->days_per_week)))
    <td class="c {{ $absent ? 'muted' : ResourcePlan::band($pct) }} {{ $w->toDateString() === $thisWeek ? 'now' : '' }}" title="{{ $absent ?: $d.' of '.$s->days_per_week.' days' }}">{{ $absent ? ($absent === 'Not started' ? 'New' : '—') : $pct.'%' }}</td>
  @endforeach</tr>
  @endforeach
@empty
  <tr><td class="muted" colspan="{{ count($weeks) + 1 }}">No people yet. @if($canEdit)<a href="{{ route('resources.staff.create') }}">Add the team</a>, or import the old tracker (see RESOURCES.md).@endif</td></tr>
@endforelse
</tbody></table></div></div>
@endsection
