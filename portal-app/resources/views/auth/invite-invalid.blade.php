@extends('layouts.guest')
@section('title', 'Invite link not valid')
@section('content')
<h1>This invite link isn't valid</h1>
<p class="muted">It may have been used already, or it's more than 7 days old. Ask the person who invited you to send a new invite.</p>
<a class="btn block" href="{{ route('login') }}">Go to log in</a>
@endsection
