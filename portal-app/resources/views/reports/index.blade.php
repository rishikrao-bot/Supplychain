@extends('layouts.app')
@section('title', 'Management reports')
@section('content')
<div><h1>Management reports</h1><p class="muted">Power BI reports, inside the portal. You'll be asked to sign in to Power BI with your work Microsoft account the first time.</p></div>
@if($dashboards->isEmpty())<div class="note">No reports yet.@if($canManage) Add the first one below.@endif</div>@endif
<div class="grid g2">
@foreach($dashboards as $d)
  <div class="panel"><div class="panel-b"><div class="row" style="justify-content:space-between"><h2><a href="{{ route('reports.show', $d) }}">{{ $d->title }}</a></h2>
    @if($canManage)<span class="small muted">{{ collect($d->roles)->map(fn ($r) => $roles[$r] ?? $r)->implode(', ') }}</span>@endif</div>
    @if($d->description)<p class="small muted">{{ $d->description }}</p>@endif
    <div class="row"><a class="btn primary sm" href="{{ route('reports.show', $d) }}">Open</a>
    @if($canManage)
      <details class="add"><summary class="btn sm">Edit</summary>
      <form method="post" action="{{ route('reports.update', $d) }}" class="grid" style="margin-top:10px">@csrf @method('put')
        @include('reports._fields', ['d' => $d])<div class="row end"><button class="btn primary sm">Save</button></div></form></details>
      <form method="post" action="{{ route('reports.destroy', $d) }}" onsubmit="return confirm('Remove {{ $d->title }} from the portal?')">@csrf @method('delete')<button class="btn sm danger">Remove</button></form>
    @endif</div>
  </div></div>
@endforeach
</div>
@if($canManage)
<div class="panel"><div class="panel-h"><h2>Add a Power BI report</h2></div>
<form method="post" action="{{ route('reports.store') }}" class="panel-b">@csrf
  <p class="small muted">In Power BI, open the report → <b>File → Embed report → Website or portal</b> and copy the link. (Copying the report's address from the browser also works.) Don't use "Publish to web": that makes the report public.</p>
  @include('reports._fields', ['d' => new \App\Models\Dashboard(['roles' => ['admin']])])
  <div class="row end"><button class="btn primary">Add report</button></div>
</form></div>
@endif
@endsection
