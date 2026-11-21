@extends('layouts.app')
@section('title', 'Pipeline & tenders')
@section('content')
@php
  $S = \App\Models\Opportunity::STAGES;
  $w = $year['won']->n ?? 0;
  $l = $year['lost']->n ?? 0;
@endphp
<div class="page-head"><div><h1>Pipeline &amp; tenders</h1><p class="muted">Enquiries, tenders and frameworks from first contact to won or lost. Weighted value uses each item's chance of winning (50% if not set).</p></div>
  <a class="btn primary" href="{{ route('pipeline.create') }}">+ Add enquiry or tender</a></div>
<div class="grid g4">
  <div class="panel stat"><span class="eyebrow">Open</span><span class="v">{{ $summary->sum('n') }}</span><span class="small muted">£{{ number_format($summary->sum('total')) }}</span></div>
  <div class="panel stat"><span class="eyebrow">Weighted</span><span class="v">£{{ number_format($summary->sum('weighted')) }}</span></div>
  <div class="panel stat"><span class="eyebrow">Won (12 months)</span><span class="v">{{ $year['won']->n ?? 0 }}</span><span class="small muted">£{{ number_format($year['won']->total ?? 0) }}</span></div>
  <div class="panel stat"><span class="eyebrow">Win rate (12 months)</span><span class="v">{{ $w + $l ? round(100 * $w / ($w + $l)).'%' : '—' }}</span><span class="small muted">{{ $w }} won · {{ $l }} lost</span></div>
</div>
<div class="panel"><div class="tbl"><table><thead><tr>@foreach(\App\Models\Opportunity::openStages() as $st)<th>{{ $S[$st][0] }}</th>@endforeach</tr></thead>
<tbody><tr>@foreach(\App\Models\Opportunity::openStages() as $st)<td><b>{{ $summary[$st]->n ?? 0 }}</b><div class="small muted">£{{ number_format($summary[$st]->total ?? 0) }}</div></td>@endforeach</tr></tbody></table></div></div>
<div class="row" style="justify-content:space-between">
  <div class="tabs">@foreach(['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost', 'all' => 'All'] as $k => $l)<a class="btn sm {{ $view === $k ? 'primary' : '' }}" href="{{ route('pipeline.index', ['show' => $k, 'mine' => $mine ?: null]) }}">{{ $l }}</a>@endforeach</div>
  <a class="btn sm {{ $mine ? 'primary' : '' }}" href="{{ route('pipeline.index', ['show' => $view, 'mine' => $mine ? null : 1]) }}">{{ $mine ? 'Showing mine' : 'Only mine' }}</a>
</div>
<div class="panel"><div class="tbl"><table>
<thead><tr><th>Enquiry / tender</th><th>Company</th><th>Stage</th><th>Return date</th><th class="num">Value</th><th class="num">Chance</th><th>Owner</th></tr></thead><tbody>
@forelse($items as $o)
<tr><td><a href="{{ route('pipeline.show', $o) }}"><b>{{ $o->title }}</b></a><div class="small muted">{{ \App\Models\Opportunity::KINDS[$o->kind] ?? $o->kind }}</div></td>
<td>@if($o->organisation)<a href="{{ route('companies.show', $o->organisation) }}">{{ $o->organisation->name }}</a>@else — @endif</td>
<td><span class="chip {{ $o->stageChip() }}">{{ $o->stageLabel() }}</span></td>
<td class="small {{ $o->isOpen() && $o->return_date?->isPast() && ! $o->return_date?->isToday() ? 'overdue' : '' }}">{{ $o->return_date?->format('j M Y') ?? '—' }}</td>
<td class="num">{{ $o->value ? '£'.number_format($o->value) : '—' }}</td><td class="num">{{ $o->probability !== null ? $o->probability.'%' : '—' }}</td>
<td class="small">{{ $o->owner?->name ?? '—' }}</td></tr>
@empty<tr><td colspan="7" class="muted">Nothing here yet.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
