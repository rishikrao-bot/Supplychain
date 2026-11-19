@extends('layouts.guest')
@section('title', 'Log in')
@section('content')
<h1>Log in</h1>
@if(session('status'))<div class="note ok small">{{ session('status') }}</div>@endif
<form method="post" action="{{ route('login') }}" class="grid">
  @csrf
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
  <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
  @error('email')<p class="errors">{{ $message }}</p>@enderror
  <button class="btn primary block">Continue</button>
</form>
<p class="small muted">Next you'll enter a code from your authenticator app. No account? Your access is set up by Compass MEP or your company's admin, who will email you an invite.</p>
@endsection
