@extends('layouts.app')
@section('title', $opp->title)
@section('content')
@php($links = ['opportunity_id' => $opp->id])
<div class="page-head"><div><a class="btn sm" href="{{ route('pipeline.index') }}">← Pipeline</a>
  <h1 style="margin-top:12px">{{ $opp->title }}</h1>
  <div class="row small muted" style="margin-top:4px"><span class="chip {{ $opp->stageChip() }}">{{ $opp->stageLabel() }}</span><span>{{ \App\Models\Opportunity::KINDS[$opp->kind] ?? $opp->kind }}</span>@if($opp->organisation)<a href="{{ route('companies.show', $opp->organisation) }}">{{ $opp->organisation->name }}</a>@endif</div></div>
  <a class="btn primary" href="{{ route('pipeline.edit', $opp) }}">Edit or change stage</a></div>
<div class="cols">
<div class="stack">
  <div class="panel"><div class="panel-h"><h2>Details</h2></div><div class="panel-b"><dl class="dl">
    <dt>Value</dt><dd>{{ $opp->value ? '£'.number_format($opp->value, 2) : '—' }}</dd>
    <dt>Chance of winning</dt><dd>{{ $opp->probability !== null ? $opp->probability.'%' : '—' }}</dd>
    <dt>Return date</dt><dd>{{ $opp->return_date?->format('l j F Y') ?? '—' }}</dd>
    <dt>Decision expected</dt><dd>{{ $opp->decision_date?->format('j F Y') ?? '—' }}</dd>
    <dt>Contact</dt><dd>@if($opp->contact)<a href="{{ route('contacts.show', $opp->contact) }}">{{ $opp->contact->name() }}</a>@else — @endif</dd>
    <dt>Owner</dt><dd>{{ $opp->owner?->name ?? '—' }}</dd>
    @if($opp->closed_at)<dt>Closed</dt><dd>{{ $opp->closed_at->format('j M Y') }}</dd>@endif
    @if($opp->stage === 'lost' && $opp->lost_reason)<dt>Why lost</dt><dd>{{ $opp->lost_reason }}</dd>@endif
  </dl>@if($opp->notes)<div class="small" style="white-space:pre-line">{{ $opp->notes }}</div>@endif</div></div>
  @include('crm._tasks', ['tasks' => $opp->tasks])
</div>
<div class="stack">@include('crm._timeline')</div>
</div>
@endsection
