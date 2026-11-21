@extends('layouts.app')
@section('title', 'Contacts')
@section('content')
<div class="page-head"><div><h1>Contacts</h1><p class="muted">Everyone you deal with, at clients, prospects, suppliers and partners.</p></div>
  <a class="btn primary" href="{{ route('contacts.create') }}">+ Add a person</a></div>
<form method="get" class="row"><input type="search" name="q" value="{{ $q }}" placeholder="Search by name, company, email or job title" aria-label="Search contacts" style="max-width:380px"><button class="btn">Search</button>@if($q)<a class="btn" href="{{ route('contacts.index') }}">Clear</a>@endif</form>
<div class="panel"><div class="tbl"><table>
<thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th></tr></thead><tbody>
@forelse($contacts as $c)
<tr><td><a href="{{ route('contacts.show', $c) }}"><b>{{ $c->name() }}</b></a>@if($c->job_title)<div class="small muted">{{ $c->job_title }}</div>@endif</td>
<td>@if($c->organisation)<a href="{{ route('companies.show', $c->organisation) }}">{{ $c->organisation->name }}</a>@else<span class="muted">—</span>@endif</td>
<td class="small">@if($c->email)<a href="mailto:{{ $c->email }}">{{ $c->email }}</a>@endif</td>
<td class="small">{{ $c->mobile ?: $c->phone }}</td></tr>
@empty<tr><td colspan="4" class="muted">No contacts{{ $q ? ' match "'.$q.'"' : ' yet' }}.</td></tr>@endforelse
</tbody></table></div></div>
{{ $contacts->links('crm.pager') }}
@endsection
