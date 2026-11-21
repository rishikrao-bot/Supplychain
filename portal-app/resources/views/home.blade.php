@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div><span class="eyebrow">{{ now()->format('l j F Y') }}</span><h1>Welcome, {{ $user->name }}</h1>
<p class="muted">This is the live Compass MEP portal. Logins and user management are ready; jobs, orders, contractors, sites and the engineer app are being added next.</p></div>
@if($user->canUseCrm())
<div class="grid g2" style="align-items:start;gap:20px">
<div class="panel"><div class="panel-h"><h2>My tasks</h2><a class="btn sm" href="{{ route('tasks.index') }}">All tasks</a></div>
@if($myTasks->isEmpty())<div class="panel-b small muted">Nothing on your list.</div>@else
<div class="tbl"><table><tbody>@foreach($myTasks as $t)<tr><td><a href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a>@if($t->about())<div class="small muted">{{ $t->about() }}</div>@endif</td><td class="small num {{ $t->isOverdue() ? 'overdue' : '' }}">{{ $t->due_on?->format('D j M') }}</td></tr>@endforeach</tbody></table></div>@endif</div>
<div class="panel"><div class="panel-h"><h2>Tender and quote deadlines</h2><span class="small muted">Next 3 weeks</span></div>
@if($deadlines->isEmpty())<div class="panel-b small muted">No returns due in the next 3 weeks.</div>@else
<div class="tbl"><table><tbody>@foreach($deadlines as $o)<tr><td><a href="{{ route('pipeline.show', $o) }}">{{ $o->title }}</a><div class="small muted">{{ $o->organisation?->name }}</div></td><td class="small num">{{ $o->return_date->format('D j M') }}</td></tr>@endforeach</tbody></table></div>@endif</div>
</div>
@endif
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
