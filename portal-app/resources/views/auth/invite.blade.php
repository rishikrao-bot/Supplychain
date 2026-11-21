@extends('layouts.guest')
@section('title', 'Set up your account')
@section('content')
<h1>Welcome, {{ $user->name }}</h1>
<p class="muted">You've been invited to the {{ config('portal.name') }} as <b>{{ $user->roleLabel() }}</b> at {{ $user->organisation->name }}. Choose a password to finish setting up.</p>
<form method="post" action="{{ route('invite.accept', $token) }}" class="grid">
  @csrf
  <div class="field"><label>Email</label><input type="email" value="{{ $user->email }}" disabled></div>
  <div class="field"><label for="password">New password</label><input id="password" type="password" name="password" required autocomplete="new-password"></div>
  <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
  <p class="small muted">At least 12 characters, with upper and lower case letters and a number.</p>
  @error('password')<p class="errors">{{ $message }}</p>@enderror
  <button class="btn primary block">Set password</button>
</form>
@endsection
