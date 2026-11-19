@extends('layouts.app')
@section('title', 'Contractors')
@section('content')
<div class="page-head"><div><h1>Contractors</h1><p class="muted">Supply chain partners, their coverage, competencies and compliance. Only documents expiring within 30 days, or already expired, are listed unless you show all.</p></div>
<div class="row"><a class="btn" href="{{ route('contractors.index', $showAll ? [] : ['all' => 1]) }}">{{ $showAll ? 'Show only expiring' : 'Show all documents' }}</a><a class="btn primary" href="{{ route('contractors.create') }}">+ Add contractor</a></div></div>
@forelse($partners as $p)
  @php
    $docs = $p->documents->sortBy('expires_on');
    $shown = $showAll ? $docs : $docs->filter(fn ($d) => $d->state() !== 'ok');
    $blocked = $docs->filter(fn ($d) => $d->blocks_offers && $d->state() === 'expired');
    $held = $p->documents->filter(fn ($d) => $d->state() !== 'expired')->pluck('name')->implode(' | ');
  @endphp
  <div class="panel"><div class="panel-h"><div><h2>{{ $p->name }}</h2>
    <div class="small muted">{{ $p->partner_type === 'specialist' ? 'Specialist · quoted per job' : 'Subcontractor · fixed rates' }} · Zone{{ count($p->zones ?? []) > 1 ? 's' : '' }} {{ implode(', ', $p->zones ?? []) }}@if($p->job_limit) · job limit £{{ number_format($p->job_limit, 2) }}@endif</div>
    @if($p->contact_name || $p->contact_email)<div class="small" style="margin-top:4px">{{ $p->contact_name }}@if($p->contact_phone) · <a href="tel:{{ preg_replace('/\s/', '', $p->contact_phone) }}">{{ $p->contact_phone }}</a>@endif @if($p->contact_email) · <a href="mailto:{{ $p->contact_email }}">{{ $p->contact_email }}</a>@endif</div>@endif
  </div>
  <div class="row">
    @if($p->suspended_at)<span class="chip crit">Suspended</span>@elseif($blocked->isNotEmpty())<span class="chip crit">Blocked: {{ $blocked->first()->name }} expired</span>@elseif($docs->contains(fn ($d) => $d->state() !== 'ok'))<span class="chip warn">Action needed</span>@elseif($docs->isEmpty())<span class="chip warn">No documents yet</span>@else<span class="chip ok">Compliant</span>@endif
    <a class="btn sm" href="{{ route('contractors.edit', $p) }}">Edit</a>
    <form method="post" action="{{ route('contractors.suspend', $p) }}">@csrf<button class="btn sm {{ $p->suspended_at ? '' : 'danger' }}">{{ $p->suspended_at ? 'Lift suspension' : 'Suspend' }}</button></form>
  </div></div>
  <div class="panel-b">
    <div><span class="eyebrow">Competencies ({{ $p->competencies->count() }})</span><div style="margin-top:6px">
      @forelse($p->competencies->sortBy('name') as $c)
        @php($ok = ! $c->certificate_required || str_contains(strtolower($held), strtolower($c->certificate_required)))
        <span class="ctag {{ $c->certificate_required ? ($ok ? 'cert' : 'bad') : '' }}" title="{{ $c->certificate_required ? ($ok ? $c->certificate_required.' certificate held' : $c->certificate_required.' certificate missing or expired: not offered this work') : '' }}">{{ $c->name }}@if($c->certificate_required) · {{ $ok ? '✓' : '✗' }} {{ $c->certificate_required }}@endif</span>
      @empty<span class="small muted">None set: this contractor won't be offered work.</span>@endforelse
    </div></div>
    <div class="grid g2">
      <div><span class="eyebrow">Documents</span>
        <div class="small" style="margin-top:6px;display:flex;flex-direction:column;gap:4px">
        @forelse($shown as $d)
          <div class="row" style="justify-content:space-between;flex-wrap:nowrap"><span>{{ $d->user ? $d->user->name.': ' : '' }}{{ $d->name }}@if($d->blocks_offers) <span class="muted">(blocks offers)</span>@endif @if($d->file_path)<a href="{{ route('documents.download', $d) }}">file</a>@endif</span>
          <span class="chip {{ ['ok' => 'ok', 'expiring' => 'warn', 'expired' => 'crit'][$d->state()] }}">{{ $d->state() === 'expired' ? 'Expired' : 'Expires' }} {{ $d->expires_on?->format('j M Y') }}</span></div>
        @empty
          <span class="muted">{{ $docs->isEmpty() ? 'No documents yet.' : '✓ All '.$docs->count().' documents in date for the next month' }}</span>
        @endforelse
        </div>
      </div>
      <details><summary>Add a document</summary>
        <form method="post" action="{{ route('contractors.documents.store', $p) }}" enctype="multipart/form-data" class="grid" style="margin-top:8px">@csrf
          <div class="field"><label for="dn-{{ $p->id }}">Document</label><input id="dn-{{ $p->id }}" type="text" name="name" required list="doc-names" placeholder="e.g. Public liability insurance"></div>
          <div class="field"><label for="du-{{ $p->id }}">For a person (CSCS, DBS, asbestos awareness)</label><select id="du-{{ $p->id }}" name="user_id"><option value="">The company</option>@foreach($p->users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
          <div class="field"><label for="de-{{ $p->id }}">Expires on</label><input id="de-{{ $p->id }}" type="date" name="expires_on" required></div>
          <div class="field"><label for="df-{{ $p->id }}">File (PDF or photo, optional)</label><input id="df-{{ $p->id }}" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.heic,.doc,.docx"></div>
          <label class="opt" style="align-self:flex-start"><input type="checkbox" name="blocks_offers" value="1"> Blocks offers if expired (insurance, key accreditations)</label>
          <div class="row end"><button class="btn primary sm">Save document</button></div>
        </form>
      </details>
    </div>
  </div></div>
@empty
  <div class="panel"><div class="panel-b muted">No contractors yet. Add your first supply chain partner.</div></div>
@endforelse
<datalist id="doc-names"><option>Public liability insurance</option><option>Employer's liability insurance</option><option>SSIP accreditation (CHAS / SafeContractor / Constructionline)</option><option>RAMS</option><option>Self-billing agreement</option><option>Gas Safe registration</option><option>F-Gas certificate</option><option>NICEIC registration</option><option>BAFE SP203-1</option><option>NSI certificate</option><option>LEIA membership</option><option>UKAS accreditation</option><option>HSE asbestos licence</option><option>Legionella Control Association</option><option>CSCS card</option><option>DBS check</option><option>Asbestos awareness</option></datalist>
@endsection
