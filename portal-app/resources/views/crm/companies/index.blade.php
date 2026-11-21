@extends('layouts.app')
@section('title', 'Companies')
@section('content')
@php($types = \App\Models\Organisation::CRM_TYPES)
<div class="page-head"><div><h1>Companies</h1><p class="muted">Clients, prospects, suppliers and supply chain partners, with their people, history, pipeline and tasks.</p></div>
  <a class="btn primary" href="{{ route('companies.create') }}">+ Add a company</a></div>
<div class="tabs"><a class="btn sm {{ ! $type ? 'primary' : '' }}" href="{{ route('companies.index', ['q' => $q ?: null]) }}">All ({{ $counts->sum() }})</a>
@foreach($types as $k => $l)<a class="btn sm {{ $type === $k ? 'primary' : '' }}" href="{{ route('companies.index', ['type' => $k, 'q' => $q ?: null]) }}">{{ $l }}s ({{ $counts[$k] ?? 0 }})</a>@endforeach</div>
<form method="get" class="row">@if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
  <input type="search" name="q" value="{{ $q }}" placeholder="Search by name, postcode or email" aria-label="Search companies" style="max-width:360px"><button class="btn">Search</button>@if($q)<a class="btn" href="{{ route('companies.index', ['type' => $type]) }}">Clear</a>@endif</form>
<div class="panel"><div class="tbl"><table>
<thead><tr><th>Company</th><th>Type</th><th>Contact details</th><th class="num">People</th><th class="num">Open pipeline</th><th class="num">Open tasks</th><th>Owner</th></tr></thead><tbody>
@forelse($companies as $c)
<tr><td><a href="{{ route('companies.show', $c) }}"><b>{{ $c->name }}</b></a>@if($c->sector)<div class="small muted">{{ $c->sector }}</div>@endif</td>
<td><span class="chip {{ ['client' => 'ok', 'prospect' => 'info', 'partner' => 'warn'][$c->type] ?? '' }}">{{ $c->typeLabel() }}</span></td>
<td class="small">{{ $c->phone ?: $c->contact_phone }}@if($c->email ?: $c->contact_email)<br>{{ $c->email ?: $c->contact_email }}@endif</td>
<td class="num">{{ $c->contacts_count }}</td>
<td class="num">{{ $c->pipeline_value ? '£'.number_format($c->pipeline_value) : '—' }}</td>
<td class="num">{{ $c->open_tasks_count ?: '—' }}</td>
<td class="small">{{ $c->owner?->name ?? '—' }}</td></tr>
@empty<tr><td colspan="7" class="muted">No companies{{ $q ? ' match "'.$q.'"' : ' yet' }}.</td></tr>@endforelse
</tbody></table></div></div>
{{ $companies->links('crm.pager') }}
@endsection
