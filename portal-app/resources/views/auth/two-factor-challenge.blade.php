@extends('layouts.guest')
@section('title', 'Enter your code')
@section('content')
<h1>Enter your code</h1>
<p class="muted">Open your authenticator app and enter the 6-digit code for the Compass MEP portal.</p>
<form method="post" action="{{ route('two-factor.verify') }}" class="grid">
  @csrf
  <div class="field"><label for="code">Code</label><input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus></div>
  @error('code')<p class="errors">{{ $message }}</p>@enderror
  <button class="btn primary block">Log in</button>
</form>
<p class="small muted">Lost your phone? Enter one of your recovery codes instead, or ask your admin to reset two-step login.</p>
<form method="post" action="{{ route('logout') }}">@csrf<button class="btn sm">Cancel</button></form>
@endsection
