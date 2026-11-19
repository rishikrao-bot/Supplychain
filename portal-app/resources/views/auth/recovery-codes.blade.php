@extends('layouts.guest')
@section('title', 'Recovery codes')
@section('content')
<h1>Save your recovery codes</h1>
<p class="muted">If you lose your phone, each of these codes lets you log in once instead of an app code. Store them somewhere safe, such as your password manager. They won't be shown again.</p>
<div class="codes">@foreach($codes as $c)<span>{{ $c }}</span>@endforeach</div>
<a class="btn primary block" href="{{ route('home') }}">I've saved them. Continue</a>
@endsection
