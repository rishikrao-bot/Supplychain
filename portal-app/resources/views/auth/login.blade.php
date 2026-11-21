@extends('layouts.guest')
@section('title', 'Log in')
@section('content')
<h1>Log in</h1>
@if(session('status'))<div class="note ok small">{{ session('status') }}</div>@endif
@if(\App\Http\Controllers\Auth\MicrosoftLoginController::enabled())
<a class="btn block" href="{{ route('login.microsoft') }}"><svg width="16" height="16" viewBox="0 0 21 21" aria-hidden="true"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>{{ config('portal.company') }} staff: sign in with Microsoft</a>
@if(! $errors->any())<p class="small muted" style="text-align:center;margin:0">or use your email and password</p>@endif
@endif
<form method="post" action="{{ route('login') }}" class="grid">
  @csrf
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
  <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
  @error('email')<p class="errors">{{ $message }}</p>@enderror
  <button class="btn primary block">Continue</button>
</form>
<p class="small muted">Next you'll enter a code from your authenticator app. No account? Your access is set up by {{ config('portal.company') }} or your company's admin, who will email you an invite.</p>
@endsection
