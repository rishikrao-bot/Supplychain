@extends('layouts.app')
@section('title', 'Rates & rules')
@section('content')
@php($chip = ['agreed' => ['Agreed', 'ok'], 'proposed' => ['Proposed', 'info'], 'on_hold' => ['On hold', 'warn']])
<div><h1>Rates &amp; rules</h1><p class="muted">The client's schedule of rates used to price every estimate, and the rules still being agreed. Rules are settings, so nothing is fixed until it's agreed.</p></div>
@foreach($cards as $card)
<div class="panel"><div class="panel-h"><div><h2>{{ $card->name }}</h2><div class="small muted">{{ $card->client->name }} · from {{ $card->valid_from?->format('j M Y') }} · {{ ucfirst($card->status) }}</div></div><span class="small muted">All rates exclude VAT</span></div>
<div class="tbl"><table><thead><tr><th>Item</th><th style="text-align:right">Rate</th></tr></thead><tbody>
@foreach($card->items as $i)<tr><td>{{ $i->label }}</td><td class="mono" style="text-align:right">@if($i->amount === null)<span class="chip warn">TBC</span>@elseif($i->unit === 'percent'){{ rtrim(rtrim(number_format($i->amount, 2), '0'), '.') }}%@else £{{ number_format($i->amount, 2) }}{{ ['per_hour' => '/h', 'per_day' => '/day', 'per_visit' => '/visit'][$i->unit] ?? '' }}@endif</td></tr>@endforeach
</tbody></table></div></div>
@endforeach
<div class="panel"><div class="panel-h"><h2>Rules</h2>@unless($canEdit)<span class="small muted">Only admins can change these</span>@endunless</div>
<div class="tbl"><table><thead><tr><th>Rule</th><th>Setting</th><th>Status</th>@if($canEdit)<th></th>@endif</tr></thead><tbody>
@foreach($settings as $s)
<tr>
  <td><b class="mono small">{{ $s->key }}</b><div class="small muted">{{ $s->note }}</div></td>
  @if($canEdit)
  <td colspan="3"><form method="post" action="{{ route('rules.update', $s) }}" class="row" style="flex-wrap:nowrap">@csrf @method('patch')
    <input type="text" name="value" value="{{ is_bool($s->value) ? ($s->value ? 'yes' : 'no') : (is_array($s->value) ? json_encode($s->value) : $s->value) }}" aria-label="Value for {{ $s->key }}" style="max-width:180px">
    <select name="status" aria-label="Status for {{ $s->key }}">@foreach($chip as $k => [$l])<option value="{{ $k }}" @selected($s->status === $k)>{{ $l }}</option>@endforeach</select>
    <button class="btn sm">Save</button></form></td>
  @else
  <td class="mono small">{{ is_bool($s->value) ? ($s->value ? 'yes' : 'no') : json_encode($s->value) }}</td><td><span class="chip {{ $chip[$s->status][1] }}">{{ $chip[$s->status][0] }}</span></td>
  @endif
</tr>
@endforeach
</tbody></table></div></div>
@endsection
