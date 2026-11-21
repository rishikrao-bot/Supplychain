@extends('layouts.app')
@section('title', 'Microsoft 365')
@section('content')
@php($on = fn ($b) => $b ? '<span class="chip ok">Set up</span>' : '<span class="chip">Not set up</span>')
<div><h1>Microsoft 365</h1>
<p class="muted">The portal's connections to Compass MEP's Microsoft 365: staff sign-in, SharePoint and the Dataverse CRM. The settings and secret are kept in the server's <span class="mono">.env</span> file (see MICROSOFT.md), not in the portal.</p></div>

<div class="panel"><div class="panel-h"><h2>Connections</h2>
  <form method="post" action="{{ route('integrations.test') }}">@csrf<button class="btn primary" @disabled(! $app)>Test connections</button></form></div>
<div class="tbl"><table><tbody>
  <tr><th>App registration</th><td>{!! $on($app) !!}</td><td class="small muted">One app in Microsoft Entra ID that the portal uses for everything below.</td></tr>
  <tr><th>Sign in with Microsoft</th><td>{!! $on($signIn) !!}</td><td class="small muted">Compass staff log in with their Microsoft 365 account. Clients and partners keep email, password and authenticator app.</td></tr>
  <tr><th>SharePoint</th><td>{!! $on($sharePoint) !!}</td><td class="small muted">@if($sharePoint)Completion reports are copied to <span class="mono">{{ $site }}</span>, folder <span class="mono">{{ $folder }}</span>/client/contract.@else Completion reports will be copied here automatically once a site is set.@endif</td></tr>
  <tr><th>Dataverse CRM</th><td>{!! $on($dataverse) !!}</td><td class="small muted">@if($dataverse)<span class="mono">{{ $dataverseUrl }}</span>@else Connection to the CRM for clients, contacts and jobs.@endif</td></tr>
</tbody></table></div></div>

@if($results)
<div class="panel"><div class="panel-h"><h2>Test results</h2></div><div class="tbl"><table><tbody>
@foreach($results as $r)<tr><th>{{ $r['name'] }}</th><td><span class="chip {{ ['ok' => 'ok', 'off' => '', 'fail' => 'crit'][$r['state']] }}">{{ ['ok' => 'Working', 'off' => 'Off', 'fail' => 'Problem'][$r['state']] }}</span></td><td class="small">{{ $r['message'] }}</td></tr>@endforeach
</tbody></table></div></div>
@endif

@if($sharePoint)
<div class="panel"><div class="panel-h"><h2>Completion reports in SharePoint</h2>
  @if($pending)<form method="post" action="{{ route('integrations.sync') }}">@csrf<button class="btn">Copy {{ $pending }} waiting report(s) now</button></form>@endif</div>
@if($recent->isEmpty())<div class="panel-b small muted">No completion reports yet.</div>@else
<div class="tbl"><table><thead><tr><th>Report</th><th>SharePoint</th></tr></thead><tbody>
@foreach($recent as $a)<tr><td class="mono small">{{ $a->original_name }}</td><td class="small">
  @if(! empty($a->meta['sharepoint']['webUrl']))<a href="{{ $a->meta['sharepoint']['webUrl'] }}" target="_blank" rel="noopener">Open in SharePoint</a>
  @elseif(! empty($a->meta['sharepoint_error']))<span class="chip crit">Failed</span> {{ \Illuminate\Support\Str::limit($a->meta['sharepoint_error']['message'], 160) }}
  @else<span class="chip">Waiting</span>@endif</td></tr>@endforeach
</tbody></table></div>@endif</div>
@endif
@endsection
