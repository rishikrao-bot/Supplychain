@extends('layouts.guest')
@section('title', 'Set up two-step login')
@section('content')
<h1>Set up two-step login</h1>
<p class="muted">Every login needs a code from an app on your phone, so a stolen password alone can't get in.</p>
<ol class="small" style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:4px">
  <li>Install an authenticator app, such as Microsoft Authenticator or Google Authenticator.</li>
  <li>In the app, add an account and scan this code.</li>
  <li>Enter the 6-digit code the app shows.</li>
</ol>
<div class="qr">{!! $qr !!}</div>
<p class="small muted">Can't scan? Enter this key in the app instead: <span class="mono">{{ $secret }}</span></p>
<form method="post" action="{{ route('two-factor.confirm') }}" class="grid">
  @csrf
  <div class="field"><label for="code">6-digit code</label><input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus></div>
  @error('code')<p class="errors">{{ $message }}</p>@enderror
  <button class="btn primary block">Turn on two-step login</button>
</form>
@endsection
