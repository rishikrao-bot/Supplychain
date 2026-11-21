@extends('layouts.app')
@section('title', $staff->name.' · Resource plan')
@section('content')
@php use App\Services\ResourcePlan; @endphp
<div class="page-head"><div><a class="btn sm" href="{{ route('resources.index', request()->only('from', 'weeks')) }}">← Resource planner</a>
  <h1 style="margin-top:12px">{{ $staff->name }}</h1><div class="small muted">{{ collect([$staff->position, $staff->team, rtrim(rtrim($staff->days_per_week, '0'), '.').' days a week'])->filter()->implode(' · ') }}@if($staff->starts_on) · starts {{ $staff->starts_on->format('j M Y') }}@endif</div></div>
  @if($canEdit)<a class="btn" href="{{ route('resources.staff.edit', $staff) }}">Edit person</a>@endif</div>
@include('resources._weeknav')
<form method="post" action="{{ route('resources.save') }}" class="panel">@csrf
<div class="tbl"><table class="plan">
<thead><tr><th class="sticky" style="text-align:left">Project</th>@foreach($weeks as $w)<th class="{{ $w->toDateString() === $thisWeek ? 'now' : '' }}">w/e<br>{{ $w->format('j M') }}</th>@endforeach</tr></thead>
<tbody>
@forelse($projects as $p)
<tr><td class="sticky"><a href="{{ route('resources.project', ['project' => $p] + request()->only('from', 'weeks')) }}">{{ $p->label() }}</a>@if($p->clientLabel())<div class="small muted">{{ $p->clientLabel() }}</div>@endif</td>
@foreach($weeks as $w)
  @php($v = $grid[$p->id][$staff->id][$w->toDateString()] ?? null)
  <td class="c {{ $w->toDateString() === $thisWeek ? 'now' : '' }}">@if($canEdit)<input class="cell" type="number" min="0" max="7" step="0.5" name="cells[{{ $p->id }}-{{ $staff->id }}][{{ $w->toDateString() }}]" value="{{ $v !== null ? rtrim(rtrim(number_format($v, 1), '0'), '.') : '' }}" aria-label="{{ $p->name }}, w/e {{ $w->format('j M') }}, days">@else{{ $v !== null ? rtrim(rtrim(number_format($v, 1), '0'), '.') : '' }}@endif</td>
@endforeach</tr>
@empty
<tr><td class="sticky muted">Nothing planned in these weeks.</td><td colspan="{{ count($weeks) }}"></td></tr>
@endforelse
<tr class="total"><td class="sticky">Total (days · %)</td>
@foreach($weeks as $w)
  @php($d = $totals[$w->toDateString()] ?? 0)
  @php($pct = round(100 * $d / max(0.5, (float) $staff->days_per_week)))
  <td class="c {{ ResourcePlan::band($pct) }}">{{ rtrim(rtrim(number_format($d, 1), '0'), '.') ?: '0' }}<br><span class="small">{{ $pct }}%</span></td>
@endforeach</tr>
</tbody></table></div>
@if($canEdit)<div class="panel-b row" style="justify-content:space-between">
  <span class="small muted">Days per week (½ days allowed). Leave blank for none. Changes are saved together.</span>
  <div class="row"><select aria-label="Add a project row" onchange="if(this.value) location.href=this.value"><option value="">+ Add a project…</option>@foreach($allProjects->whereNotIn('id', $projects->pluck('id')) as $p)<option value="{{ request()->fullUrlWithQuery(['add' => $p->id]) }}">{{ $p->label() }}</option>@endforeach</select>
  <button class="btn primary">Save changes</button></div></div>@endif
</form>
@if($canEdit)@include('resources._fill', ['fixedStaff' => $staff, 'projectsList' => $allProjects])@endif
@endsection
