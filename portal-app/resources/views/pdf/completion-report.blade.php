@php
  $pri = ['emergency' => 'Emergency', 'urgent' => 'Urgent', 'routine' => 'Routine', 'planned' => 'Planned'];
  $band = ['normal' => 'Normal hours', 'ooh' => 'Out of hours', 'sun' => 'Sunday / bank holiday'];
  $outcome = ['complete' => 'Work completed', 'further' => 'Further works required', 'parts' => 'Parts required: return visit booked', 'noaccess' => 'No access', 'asbestos' => 'Suspected asbestos: work stopped'];
  $checks = ['signin' => 'Signed in with site contact / reception', 'asbestos' => 'Asbestos register checked for the work area', 'rams' => 'RAMS reviewed and briefed', 'permit' => 'Permits and isolations in place where needed', 'safe' => 'Work area made safe for the public'];
  $tradeNames = ['building' => 'Building services', 'specialist' => 'Specialist systems', 'lift' => 'Lift', 'service' => 'Specialist service'];
  $hrs = fn ($h) => intdiv((int) round($h * 60), 60).'h '.str_pad((int) round($h * 60) % 60, 2, '0', STR_PAD_LEFT).'m';
@endphp
<!doctype html>
<html lang="en-GB"><head><meta charset="utf-8">
<style>
  @page { margin: 22mm 16mm 20mm 16mm; }
  * { font-family: "DejaVu Sans", sans-serif; }
  body { font-size: 9.5pt; color: #1E3440; line-height: 1.45; }
  header { position: fixed; top: -16mm; left: 0; right: 0; height: 12mm; border-bottom: 1.5pt solid #25C4A9; }
  header img { height: 10mm; }
  header .t { position: absolute; right: 0; top: 2mm; text-align: right; font-size: 8pt; color: #587078; }
  footer { position: fixed; bottom: -14mm; left: 0; right: 0; height: 10mm; font-size: 7.5pt; color: #587078; border-top: 0.5pt solid #D5E2E1; padding-top: 2mm; }
  h1 { font-size: 17pt; margin: 0 0 1mm; color: #294653; }
  h2 { font-size: 10.5pt; margin: 6mm 0 2mm; color: #294653; text-transform: uppercase; letter-spacing: 0.08em; border-bottom: 0.5pt solid #D5E2E1; padding-bottom: 1mm; }
  .ref { font-size: 11pt; color: #13806D; font-weight: bold; }
  .muted { color: #587078; }
  table { width: 100%; border-collapse: collapse; }
  .kv td { padding: 1.4mm 2mm; vertical-align: top; border-bottom: 0.4pt solid #E9F1F0; }
  .kv td.k { width: 30%; color: #587078; }
  .badge { display: inline-block; padding: 0.6mm 2.5mm; border-radius: 2mm; font-size: 8pt; font-weight: bold; }
  .ok { background: #DDF1E5; color: #23784A; } .warn { background: #FBEFD9; color: #9A6212; } .crit { background: #F9E1DE; color: #B23A2E; }
  .box { border: 0.5pt solid #D5E2E1; border-radius: 2mm; padding: 3mm; }
  .photos td { width: 50%; padding: 1.5mm; vertical-align: top; }
  .photos img { width: 100%; max-height: 62mm; border: 0.5pt solid #D5E2E1; }
  .cap { font-size: 7.5pt; color: #587078; margin-top: 0.8mm; }
  .list td { padding: 1.2mm 2mm; border-bottom: 0.4pt solid #E9F1F0; }
  .tick { color: #23784A; font-weight: bold; } .cross { color: #B23A2E; font-weight: bold; }
  .sig img { max-height: 22mm; }
  .avoid { page-break-inside: avoid; }
</style></head>
<body>
<header>@if($logo)<img src="{{ $logo }}" alt="{{ config('portal.company') }}">@endif<div class="t">Job completion report<br><b>{{ $wo->ref() }}</b></div></header>
<footer>{{ $operator?->name ?? config('portal.company') }}@if($operator?->address) · {{ $operator->address }}@endif · Report created {{ $generatedAt->format('j M Y, H:i') }}</footer>

<h1>Job completion report</h1>
<div><span class="ref">{{ $wo->ref() }}</span> <span class="muted">· {{ $wo->client->name }} job no. {{ $wo->client_ref ?: '—' }}@if($wo->client_order_no) · Order no. {{ $wo->client_order_no }}@endif</span></div>

<h2>Job</h2>
<table class="kv">
  <tr><td class="k">Client / contract</td><td>{{ $wo->client->name }} · {{ $wo->contract->name }}</td></tr>
  <tr><td class="k">Site</td><td>{{ $wo->site?->name ?? '—' }}@if($wo->site?->address), {{ $wo->site->address }}@endif @if($wo->site?->postcode) {{ $wo->site->postcode }}@endif</td></tr>
  @if($wo->location)<tr><td class="k">Location on site</td><td>{{ $wo->location }}</td></tr>@endif
  <tr><td class="k">Reported problem</td><td>{{ $wo->description }}</td></tr>
  <tr><td class="k">Priority</td><td>{{ $pri[$wo->priority] ?? ucfirst($wo->priority) }} · {{ $band[$wo->rate_band] ?? $wo->rate_band }}</td></tr>
  <tr><td class="k">Trades</td><td>{{ $wo->trades->map(fn ($t) => $t->competency?->name ?? ($tradeNames[$t->trade] ?? $t->trade))->join(', ') ?: '—' }}</td></tr>
  <tr><td class="k">Received</td><td>{{ $wo->received_at?->format('j M Y, H:i') }}</td></tr>
  @if($wo->parent)<tr><td class="k">Follows on from</td><td>{{ $wo->parent->ref() }}</td></tr>@endif
</table>

@forelse($wo->visits->sortBy('arrived_at') as $i => $v)
  @php
    $before = $v->attachments->where('kind', 'photo_before')->values();
    $after = $v->attachments->where('kind', 'photo_after')->values();
    $evidence = $v->attachments->where('kind', 'evidence')->values();
    $sig = $v->attachments->firstWhere('kind', 'signature');
    $hours = $v->arrived_at && $v->departed_at ? $v->arrived_at->diffInMinutes($v->departed_at) / 60 : null;
    $oc = $v->outcome;
    $cls = $oc === 'complete' ? 'ok' : (in_array($oc, ['noaccess', 'asbestos']) ? 'crit' : 'warn');
  @endphp
  <h2>Visit{{ $wo->visits->count() > 1 ? ' '.($loop->iteration) : '' }} · {{ $v->arrived_at?->format('l j F Y') }}</h2>
  <table class="kv">
    <tr><td class="k">Outcome</td><td><span class="badge {{ $cls }}">{{ $outcome[$oc] ?? ucfirst((string) $oc) }}</span>@if($v->outcome_detail)<br>{{ $v->outcome_detail }}@endif</td></tr>
    <tr><td class="k">Engineer</td><td>{{ $v->user?->name ?? '—' }}, on behalf of {{ $operator?->name ?? config('portal.company') }}</td></tr>
    <tr><td class="k">Arrived / left</td><td>{{ $v->arrived_at?->format('H:i') ?? '—' }} – {{ $v->departed_at?->format('H:i') ?? '—' }}@if($hours) ({{ $hrs($hours) }} on site)@endif</td></tr>
    @if($v->arrival_lat)<tr><td class="k">Location check</td><td>GPS {{ number_format($v->arrival_lat, 4) }}, {{ number_format($v->arrival_lng, 4) }} recorded on arrival</td></tr>@endif
    @if($oc === 'noaccess')<tr><td class="k">No access</td><td>{{ $v->calls_to_site ?? 0 }} call(s) to the site contact · arrived {{ $v->within_window ? 'within' : 'outside' }} the agreed window</td></tr>@endif
  </table>

  @if($v->safety_checklist)
  <div class="avoid"><h2>Safety checks on arrival</h2>
  <table class="list">@foreach($checks as $key => $label)<tr><td style="width:6mm" class="{{ !empty($v->safety_checklist[$key]) ? 'tick' : 'cross' }}">{{ !empty($v->safety_checklist[$key]) ? '✓' : '✗' }}</td><td>{{ $label }}</td></tr>@endforeach</table></div>
  @endif

  @if($v->notes)<div class="avoid"><h2>Work carried out</h2><div class="box">{!! nl2br(e($v->notes)) !!}</div></div>@endif

  @if($v->materials->isNotEmpty())
  <h2>Materials used</h2>
  <table class="list">@foreach($v->materials as $m)<tr><td>{{ $m->description }}</td></tr>@endforeach</table>
  @endif

  @php($photoCell = function ($p) use ($img) { if (! $p) return '<td></td>'; $src = $img($p->path); $when = isset($p->meta['taken_at']) ? \Illuminate\Support\Carbon::parse($p->meta['taken_at'])->format('j M Y, H:i') : $p->created_at->format('j M Y, H:i'); $gps = isset($p->meta['lat']) ? ' · GPS '.number_format($p->meta['lat'], 4).', '.number_format($p->meta['lng'], 4) : ''; return '<td>'.($src ? '<img src="'.$src.'" alt="">' : '').'<div class="cap">'.e($p->meta['caption'] ?? $p->original_name).'<br>'.$when.$gps.'</div></td>'; })
  @if($before->isNotEmpty() || $after->isNotEmpty())
  <h2>Photos</h2>
  <table class="photos"><tr><td class="muted" style="padding-bottom:0"><b>Before</b></td><td class="muted" style="padding-bottom:0"><b>After</b></td></tr>
    @for($r = 0; $r < max($before->count(), $after->count()); $r++)<tr class="avoid">{!! $photoCell($before[$r] ?? null) !!}{!! $photoCell($after[$r] ?? null) !!}</tr>@endfor
  </table>
  @endif
  @if($evidence->isNotEmpty())
  <h2>Evidence photos</h2>
  <table class="photos">@foreach($evidence->chunk(2) as $pair)<tr class="avoid">{!! $photoCell($pair->values()[0] ?? null) !!}{!! $photoCell($pair->values()[1] ?? null) !!}</tr>@endforeach</table>
  @endif

  @if($v->signed_by || $sig)
  <div class="avoid"><h2>Site sign-off</h2>
  <table class="kv sig"><tr><td class="k">Signed by</td><td>{{ $v->signed_by ?: '—' }}@if($sig && ($s = $img($sig->path)))<br><img src="{{ $s }}" alt="Signature">@endif<br><span class="muted">{{ $v->departed_at?->format('j M Y, H:i') }}</span></td></tr></table></div>
  @endif
@empty
  <h2>Visit</h2><p class="muted">No visit recorded.</p>
@endforelse

@php($follow = $wo->children->map(fn ($c) => $c->ref().' (return visit)')->merge($wo->quotes->map(fn ($q) => 'Quote '.$q->number.': '.$q->description))->merge($wo->exceptions->map(fn ($e) => $e->title.($e->decision ? ' — '.$e->decision : ''))))
@if($follow->isNotEmpty())
<h2>Follow-up</h2>
<table class="list">@foreach($follow as $f)<tr><td>{{ $f }}</td></tr>@endforeach</table>
@endif

<p class="muted" style="margin-top:6mm;font-size:8pt">Arrival and departure times are recorded by the engineer's device and are final unless queried within 14 days of this report. This report shows the work carried out; charges are set out separately in the valuation.</p>
</body></html>
