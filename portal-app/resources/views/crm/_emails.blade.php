{{-- The viewer's own Outlook emails with these people. $emails = [list|null, error|null], $links as in _timeline. --}}
@php([$list, $error] = $emails)
<div class="panel"><div class="panel-h"><h2>Your Outlook emails</h2><span class="small muted">Only you see these until you save one</span></div>
@if($list === null)
  <div class="panel-b small">Connect Outlook to see your emails with them here. <a href="{{ route('tasks.index') }}">Connect Outlook &amp; To Do</a></div>
@elseif($error)
  <div class="panel-b"><div class="note crit small">Outlook couldn't be read: {{ $error }}</div></div>
@elseif(empty($list))
  <div class="panel-b small muted">No emails found with {{ ($single ?? false) ? 'this address' : 'these people' }}@if(! ($hasAddresses ?? true)) (add an email address first)@endif.</div>
@else
<ul class="timeline">
@foreach($list as $m)
  <li><div class="meta"><span>{{ $m['at']?->format('j M Y H:i') }}</span><span>· {{ $m['from'] }}</span>
    @if($m['link'])<a href="{{ $m['link'] }}" target="_blank" rel="noopener">Open</a>@endif
    <form method="post" action="{{ route('activities.email') }}" style="margin-left:auto">@csrf
      @foreach($links as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
      <input type="hidden" name="message_id" value="{{ $m['id'] }}"><button class="btn sm">Save to record</button></form></div>
  <b>{{ $m['subject'] }}</b><div class="small muted">{{ \Illuminate\Support\Str::limit($m['preview'], 220) }}</div></li>
@endforeach
</ul>@endif
</div>
