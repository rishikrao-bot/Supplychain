@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div><span class="eyebrow">{{ now()->format('l j F Y') }}</span><h1>Welcome, {{ $user->name }}</h1>
<p class="muted">This is the live Compass MEP portal. Logins and user management are ready; jobs, orders, contractors, sites and the engineer app are being added next.</p></div>
@if($user->canManageUsers())
<div class="panel"><div class="panel-b row" style="justify-content:space-between"><div><h2>{{ $user->isCompassAdmin() ? 'Users & access' : 'Your users' }}</h2><p class="small muted">Invite people, change roles and remove access.</p></div><a class="btn primary" href="{{ route('users.index') }}">Manage users</a></div></div>
@endif
@if($onHold->isNotEmpty())
<div class="panel"><div class="panel-h"><h2>Still being agreed</h2><span class="small muted">Kept as settings so nothing is fixed until agreed</span></div>
<div class="tbl"><table><thead><tr><th>Rule</th><th>Current setting</th><th>Status</th></tr></thead><tbody>
@foreach($onHold as $s)<tr><td>{{ $s->note }}</td><td class="mono small">{{ is_bool($s->value) ? ($s->value ? 'yes' : 'no') : json_encode($s->value) }}</td><td><span class="chip {{ $s->status === 'on_hold' ? 'warn' : 'info' }}">{{ $s->status === 'on_hold' ? 'On hold' : 'Proposed' }}</span></td></tr>@endforeach
</tbody></table></div></div>
@endif
@if($activity->isNotEmpty())
<div class="panel"><div class="panel-h"><h2>Recent activity</h2></div><div class="tbl"><table><tbody>
@foreach($activity as $a)<tr><td class="small muted mono" style="white-space:nowrap">{{ $a->created_at->format('j M H:i') }}</td><td>{{ $a->message }}</td></tr>@endforeach
</tbody></table></div></div>
@endif
@endsection
