{{-- Activity history plus the "add a note / call / meeting" form. Needs $activities and $links (organisation_id / contact_id / opportunity_id). --}}
<div class="panel"><div class="panel-h"><h2>History</h2><span class="small muted">{{ $activities->count() }} item{{ $activities->count() === 1 ? '' : 's' }}</span></div>
<form method="post" action="{{ route('activities.store') }}" class="panel-b" style="border-bottom:1px solid var(--line)">@csrf
  @foreach($links as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
  <div class="row">
    <select name="type" aria-label="Type" style="width:auto">@foreach(['note' => 'Note', 'call' => 'Call', 'meeting' => 'Meeting'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
    <input type="text" name="subject" placeholder="Subject (optional)" aria-label="Subject" style="flex:1;min-width:160px">
    <input type="datetime-local" name="occurred_at" aria-label="When" value="{{ now()->format('Y-m-d\TH:i') }}" style="width:auto;border:1px solid var(--line);border-radius:6px;padding:8px;background:var(--surface);color:var(--fg)">
  </div>
  <textarea name="body" required placeholder="What was said or agreed" aria-label="Details"></textarea>
  <div class="row end"><button class="btn primary sm">Save to history</button></div>
</form>
@if($activities->isEmpty())<div class="panel-b small muted">Nothing yet.</div>@else
<ul class="timeline">
@foreach($activities as $a)
  <li><div class="meta"><span class="chip {{ ['note' => '', 'call' => 'info', 'meeting' => 'ok', 'email' => 'warn'][$a->type] ?? '' }}">{{ \App\Models\CrmActivity::TYPES[$a->type] ?? $a->type }}</span>
    <span>{{ $a->occurred_at->format('j M Y H:i') }}</span>@if($a->user)<span>· {{ $a->user->name }}</span>@endif
    @if($a->contact && empty($links['contact_id']))<span>· <a href="{{ route('contacts.show', $a->contact) }}">{{ $a->contact->name() }}</a></span>@endif
    @if($a->opportunity && empty($links['opportunity_id']))<span>· <a href="{{ route('pipeline.show', $a->opportunity) }}">{{ $a->opportunity->title }}</a></span>@endif
    @if($a->outlook_link)<a href="{{ $a->outlook_link }}" target="_blank" rel="noopener">Open in Outlook</a>@endif
    @if($a->user_id === auth()->id() || auth()->user()->isOperatorAdmin())
    <form method="post" action="{{ route('activities.destroy', $a) }}" style="margin-left:auto" onsubmit="return confirm('Remove this from the history?')">@csrf @method('delete')<button class="btn sm" aria-label="Remove">Remove</button></form>@endif
  </div>
  @if($a->subject)<b>{{ $a->subject }}</b>@endif
  @if($a->body)<div class="body small">{{ $a->body }}</div>@endif</li>
@endforeach
</ul>@endif
</div>
