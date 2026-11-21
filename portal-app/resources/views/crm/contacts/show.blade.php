@extends('layouts.app')
@section('title', $contact->name())
@section('content')
@php($links = ['contact_id' => $contact->id])
<div class="page-head"><div><a class="btn sm" href="{{ $contact->organisation ? route('companies.show', $contact->organisation) : route('contacts.index') }}">← {{ $contact->organisation?->name ?? 'Contacts' }}</a>
  <h1 style="margin-top:12px">{{ $contact->name() }}</h1>
  <div class="small muted">{{ collect([$contact->job_title, $contact->organisation?->name])->filter()->implode(' · ') }}</div></div>
  <div class="row"><a class="btn" href="{{ route('pipeline.create', ['contact' => $contact->id]) }}">+ Enquiry or tender</a><a class="btn primary" href="{{ route('contacts.edit', $contact) }}">Edit</a></div></div>
<div class="cols">
<div class="stack">
  <div class="panel"><div class="panel-h"><h2>Details</h2></div><div class="panel-b"><dl class="dl">
    <dt>Email</dt><dd>@if($contact->email)<a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>@else — @endif</dd>
    <dt>Mobile</dt><dd>@if($contact->mobile)<a href="tel:{{ preg_replace('/\s/', '', $contact->mobile) }}">{{ $contact->mobile }}</a>@else — @endif</dd>
    <dt>Phone</dt><dd>@if($contact->phone)<a href="tel:{{ preg_replace('/\s/', '', $contact->phone) }}">{{ $contact->phone }}</a>@else — @endif</dd>
    <dt>Company</dt><dd>@if($contact->organisation)<a href="{{ route('companies.show', $contact->organisation) }}">{{ $contact->organisation->name }}</a>@else — @endif</dd>
    <dt>Owner</dt><dd>{{ $contact->owner?->name ?? '—' }}</dd>
  </dl>@if($contact->notes)<div class="small" style="white-space:pre-line">{{ $contact->notes }}</div>@endif</div></div>
  @if($contact->opportunities->isNotEmpty())
  <div class="panel"><div class="panel-h"><h2>Pipeline</h2></div><div class="tbl"><table><tbody>@foreach($contact->opportunities as $o)
    <tr><td><a href="{{ route('pipeline.show', $o) }}">{{ $o->title }}</a></td><td><span class="chip {{ $o->stageChip() }}">{{ $o->stageLabel() }}</span></td><td class="num">{{ $o->value ? '£'.number_format($o->value) : '' }}</td></tr>
  @endforeach</tbody></table></div></div>@endif
  @include('crm._tasks', ['tasks' => $contact->tasks])
</div>
<div class="stack">
  @include('crm._timeline')
  @include('crm._emails', ['single' => true, 'hasAddresses' => (bool) $contact->email])
</div>
</div>
@endsection
