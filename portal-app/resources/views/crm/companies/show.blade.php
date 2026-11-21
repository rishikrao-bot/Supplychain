@extends('layouts.app')
@section('title', $company->name)
@section('content')
@php($links = ['organisation_id' => $company->id])
<div class="page-head"><div><a class="btn sm" href="{{ route('companies.index') }}">← Companies</a>
  <h1 style="margin-top:12px">{{ $company->name }}</h1>
  <div class="row small muted" style="margin-top:4px"><span class="chip {{ ['client' => 'ok', 'prospect' => 'info', 'partner' => 'warn'][$company->type] ?? '' }}">{{ $company->typeLabel() }}</span>@if($company->sector)<span>{{ $company->sector }}</span>@endif @if($company->owner)<span>Owner: {{ $company->owner->name }}</span>@endif</div></div>
  <div class="row"><a class="btn" href="{{ route('pipeline.create', ['company' => $company->id]) }}">+ Enquiry or tender</a><a class="btn primary" href="{{ route('companies.edit', $company) }}">Edit</a></div></div>

<div class="cols">
<div class="stack">
  <div class="panel"><div class="panel-h"><h2>Details</h2></div><div class="panel-b"><dl class="dl">
    <dt>Phone</dt><dd>@if($p = $company->phone ?: $company->contact_phone)<a href="tel:{{ preg_replace('/\s/', '', $p) }}">{{ $p }}</a>@else — @endif</dd>
    <dt>Email</dt><dd>@if($e = $company->email ?: $company->contact_email)<a href="mailto:{{ $e }}">{{ $e }}</a>@else — @endif</dd>
    <dt>Website</dt><dd>@if($company->website)<a href="{{ \Illuminate\Support\Str::start($company->website, 'https://') }}" target="_blank" rel="noopener">{{ $company->website }}</a>@else — @endif</dd>
    <dt>Address</dt><dd>{{ trim($company->address.' '.$company->postcode) ?: '—' }}</dd>
    @if($jobs !== null)<dt>Jobs</dt><dd>{{ $jobs }} in the portal</dd>@endif
    @if($company->isPartner())<dt>Supply chain</dt><dd><a href="{{ route('contractors.edit', $company) }}">Compliance, rates and competencies</a></dd>@endif
  </dl>@if($company->notes)<div class="small" style="white-space:pre-line">{{ $company->notes }}</div>@endif</div></div>

  <div class="panel"><div class="panel-h"><h2>People</h2><a class="btn sm" href="{{ route('contacts.create', ['company' => $company->id]) }}">+ Add a person</a></div>
  @if($company->contacts->isEmpty())<div class="panel-b small muted">No contacts yet.</div>@else
  <div class="tbl"><table><tbody>@foreach($company->contacts as $c)
    <tr><td><a href="{{ route('contacts.show', $c) }}"><b>{{ $c->name() }}</b></a><div class="small muted">{{ $c->job_title }}</div></td>
    <td class="small">@if($c->email)<a href="mailto:{{ $c->email }}">{{ $c->email }}</a><br>@endif{{ $c->mobile ?: $c->phone }}</td></tr>
  @endforeach</tbody></table></div>@endif</div>

  <div class="panel"><div class="panel-h"><h2>Pipeline</h2><a class="btn sm" href="{{ route('pipeline.create', ['company' => $company->id]) }}">+ Add</a></div>
  @if($company->opportunities->isEmpty())<div class="panel-b small muted">No enquiries or tenders yet.</div>@else
  <div class="tbl"><table><tbody>@foreach($company->opportunities as $o)
    <tr><td><a href="{{ route('pipeline.show', $o) }}">{{ $o->title }}</a><div class="small muted">{{ \App\Models\Opportunity::KINDS[$o->kind] ?? $o->kind }}@if($o->return_date) · due {{ $o->return_date->format('j M Y') }}@endif</div></td>
    <td><span class="chip {{ $o->stageChip() }}">{{ $o->stageLabel() }}</span></td><td class="num">{{ $o->value ? '£'.number_format($o->value) : '' }}</td></tr>
  @endforeach</tbody></table></div>@endif</div>

  @include('crm._tasks', ['tasks' => $company->tasks])
</div>
<div class="stack">
  @include('crm._timeline')
  @include('crm._emails', ['hasAddresses' => (bool) ($company->email || $company->contacts->whereNotNull('email')->isNotEmpty())])
</div>
</div>
@endsection
