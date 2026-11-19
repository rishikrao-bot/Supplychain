@extends('layouts.app')
@section('title', 'Sites')
@section('content')
<div class="page-head"><div><h1>Site register</h1><p class="muted">Each site is set up once. Orders pick up its zone, access notes and asbestos information automatically.</p></div><a class="btn primary" href="{{ route('sites.create') }}">+ Add a site</a></div>
<form method="get" class="row"><input type="search" name="q" value="{{ $q }}" placeholder="Search by name, address or postcode" aria-label="Search sites" style="max-width:360px"><button class="btn">Search</button>@if($q)<a class="btn" href="{{ route('sites.index') }}">Clear</a>@endif</form>
@foreach($contracts as $c)
<div class="panel"><div class="panel-h"><div><h2>{{ $c->client->name }} · {{ $c->name }}</h2>@if($c->end_client)<div class="small muted">{{ $c->end_client }}</div>@endif</div><span class="small muted">{{ $c->sites->count() }} site{{ $c->sites->count() === 1 ? '' : 's' }}</span></div>
@if($c->sites->isEmpty())<div class="panel-b muted">No sites yet.</div>@else
<div class="tbl"><table><thead><tr><th>Site</th><th>Zone</th><th>Contact</th><th>Access &amp; asbestos</th><th></th></tr></thead><tbody>
@foreach($c->sites as $s)
<tr><td><b>{{ $s->name }}</b><div class="small muted">{{ $s->address }} {{ $s->postcode }}@if($s->site_type) · {{ $s->site_type }}@endif</div></td>
<td><span class="chip">Zone {{ $s->zone }}</span>@if($s->zone === 'B')<div class="small muted">+ travel supplement</div>@endif</td>
<td class="small">{{ $s->contact_name ?: '—' }}@if($s->contact_phone)<br><a href="tel:{{ preg_replace('/\s/', '', $s->contact_phone) }}">{{ $s->contact_phone }}</a>@endif</td>
<td class="small">{{ $s->access_notes ?: '—' }}@if($s->asbestos_notes)<div class="muted">{{ $s->asbestos_notes }}</div>@endif</td>
<td><a class="btn sm" href="{{ route('sites.edit', $s) }}">Edit</a></td></tr>
@endforeach
</tbody></table></div>@endif
</div>
@endforeach
@endsection
