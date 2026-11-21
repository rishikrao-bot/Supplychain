@extends('layouts.app')
@section('title', 'Users')
@section('content')
@php
  use App\Support\Roles; use App\Services\UserAccess;
  $statusChip = ['active' => ['Active', 'ok'], 'invited' => ['Invite sent', 'warn'], 'disabled' => ['Access removed', 'crit']];
  $tabOrgs = $orgs->where('type', $tab);
  $list = $users->filter(fn ($u) => $u->organisation->type === $tab);
@endphp
<div><h1>{{ $actor->isOperatorAdmin() ? 'Users & access' : 'Users' }}</h1>
<p class="muted">Each person has their own login with two-step verification and sees only what their role allows.
@if($actor->isOperatorAdmin()) Client admins can add their own users, and partner managers add their own site staff. @endif</p></div>

<div class="grid g4">
  <div class="panel stat"><span class="eyebrow">Active</span><span class="v">{{ $users->where('status', 'active')->count() }}</span></div>
  <div class="panel stat"><span class="eyebrow">Invites pending</span><span class="v">{{ $users->where('status', 'invited')->count() }}</span></div>
  <div class="panel stat"><span class="eyebrow">Organisations</span><span class="v">{{ $orgs->count() }}</span></div>
  <div class="panel stat"><span class="eyebrow">Access removed</span><span class="v">{{ $users->where('status', 'disabled')->count() }}</span></div>
</div>

@if(count($groups) > 1)
<div class="tabs">@foreach($groups as $key => $label)<a class="btn sm {{ $tab === $key ? 'primary' : '' }}" href="{{ route('users.index', ['tab' => $key]) }}">{{ $label }} ({{ $users->filter(fn ($u) => $u->organisation->type === $key)->count() }})</a>@endforeach</div>
@endif

<div class="panel"><div class="tbl"><table>
<thead><tr><th>Name</th><th>Organisation</th><th>Role</th><th>Two-step</th><th>Last login</th><th>Status</th><th></th></tr></thead>
<tbody>
@forelse($list as $u)
  @php($can = UserAccess::canManage($actor, $u) && $actor->id !== $u->id)
  <tr>
    <td><b>{{ $u->name }}</b>@if($u->id === $actor->id) <span class="small muted">(you)</span>@endif<div class="small muted">{{ $u->email }}@if($u->phone) · {{ $u->phone }}@endif</div></td>
    <td class="small">{{ $u->organisation->name }}</td>
    <td>
      @if($can)
      <form method="post" action="{{ route('users.role', $u) }}">@csrf @method('patch')
        <select name="role" aria-label="Role for {{ $u->name }}" onchange="this.form.submit()">@foreach(Roles::forOrgType($u->organisation->type) as $key => [$label])<option value="{{ $key }}" @selected($u->role === $key)>{{ $label }}</option>@endforeach</select>
      </form>
      @else {{ $u->roleLabel() }} @endif
    </td>
    <td class="small">{!! $u->hasTwoFactor() ? '✓ Set up' : '<span class="muted">Not yet</span>' !!}</td>
    <td class="small">{{ $u->last_login_at?->format('j M, H:i') ?? '—' }}</td>
    <td><span class="chip {{ $statusChip[$u->status][1] }}">{{ $statusChip[$u->status][0] }}</span></td>
    <td style="white-space:nowrap">
      @if($can)
        @if($u->status === 'invited')<form method="post" action="{{ route('users.resend', $u) }}">@csrf<button class="btn sm">Resend invite</button></form>@endif
        @if($u->status === 'active' && $u->hasTwoFactor())<form method="post" action="{{ route('users.reset2fa', $u) }}">@csrf<button class="btn sm">Reset two-step</button></form>@endif
        @if($u->status !== 'disabled')<form method="post" action="{{ route('users.deactivate', $u) }}" onsubmit="return confirm('Remove access for {{ $u->name }}? They will be logged out straight away.')">@csrf<button class="btn sm danger">Remove access</button></form>
        @else<form method="post" action="{{ route('users.reactivate', $u) }}">@csrf<button class="btn sm">Restore access</button></form>@endif
      @endif
    </td>
  </tr>
@empty
  <tr><td colspan="7" class="muted">No users here yet. Invite the first person below.</td></tr>
@endforelse
</tbody></table></div></div>

<div class="panel"><div class="panel-h"><h2>Invite someone</h2><span class="small muted">They get an email to set their password and two-step login. The link expires in 7 days.</span></div>
<form method="post" action="{{ route('users.store') }}" class="panel-b">@csrf
  <div class="grid g2">
    <div class="field"><label for="name">Full name</label><input id="name" type="text" name="name" value="{{ old('name') }}" required></div>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required></div>
    <div class="field"><label for="phone">Mobile (optional)</label><input id="phone" type="tel" name="phone" value="{{ old('phone') }}"></div>
    @if($orgs->count() > 1)
    <div class="field"><label for="organisation_id">Organisation</label>
      <select id="organisation_id" name="organisation_id" required onchange="document.querySelectorAll('[data-roles-for]').forEach(s => { const on = s.dataset.rolesFor === this.selectedOptions[0].dataset.type; s.hidden = !on; s.disabled = !on; })">
        @foreach($orgs as $o)<option value="{{ $o->id }}" data-type="{{ $o->type }}" @selected(old('organisation_id', $tabOrgs->first()?->id) == $o->id)>{{ $o->name }} ({{ ['operator' => 'staff', 'client' => 'client', 'partner' => 'partner'][$o->type] }})</option>@endforeach
      </select></div>
    @else
      <input type="hidden" name="organisation_id" value="{{ $orgs->first()->id }}">
    @endif
    @php($firstType = ($orgs->firstWhere('id', old('organisation_id', $tabOrgs->first()?->id)) ?? $orgs->first())->type)
    <div class="field"><label for="role-{{ $firstType }}">Role</label>
      @foreach($orgs->pluck('type')->unique() as $type)
        <select id="role-{{ $type }}" name="role" data-roles-for="{{ $type }}" @if($type !== $firstType) hidden disabled @endif>@foreach(Roles::forOrgType($type) as $key => [$label, $desc])<option value="{{ $key }}">{{ $label }}: {{ $desc }}</option>@endforeach</select>
      @endforeach
    </div>
  </div>
  <div class="row end"><button class="btn primary">Send invite</button></div>
</form></div>

@if($actor->isOperatorAdmin())
<div class="panel"><div class="panel-h"><h2>Add a client or partner company</h2><span class="small muted">So you can invite their people. Full partner details come with the contractors section.</span></div>
<form method="post" action="{{ route('organisations.store') }}" class="panel-b">@csrf
  <div class="grid g2">
    <div class="field"><label for="org-name">Company name</label><input id="org-name" type="text" name="name" required></div>
    <div class="field"><label for="org-type">Type</label><select id="org-type" name="type"><option value="partner">Supply chain partner</option><option value="client">Client</option></select></div>
    <div class="field"><label for="org-cn">Contact name</label><input id="org-cn" type="text" name="contact_name"></div>
    <div class="field"><label for="org-cp">Contact phone</label><input id="org-cp" type="tel" name="contact_phone"></div>
    <div class="field"><label for="org-ce">Contact email</label><input id="org-ce" type="email" name="contact_email"></div>
  </div>
  <div class="row end"><button class="btn">Add company</button></div>
</form></div>
@endif
@endsection
