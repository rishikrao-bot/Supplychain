<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>@yield('title') · Compass MEP Portal</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono&display=swap">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body>
@php($me = auth()->user())
<header class="top">
  <a href="{{ route('home') }}"><img src="{{ asset('img/compass-mep-logo.png') }}" alt="Compass MEP: home"></a>
  <div class="who"><span><b>{{ $me->name }}</b> · {{ $me->roleLabel() }} · {{ $me->organisation->name }}</span>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="btn sm">Log out</button></form></div>
</header>
<div class="shell">
  <nav class="nav" aria-label="Sections">
    <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Dashboard</a>
    @if($me->canManageUsers())<a href="{{ route('users.index') }}" @if(request()->routeIs('users.*')) aria-current="page" @endif>{{ $me->isCompassAdmin() ? 'Users & access' : 'Users' }}</a>@endif
    <span class="soon small">Jobs, orders, contractors, sites and the engineer app are being built</span>
  </nav>
  <main><div class="wrap">
    @if(session('status'))<div class="note ok" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="note crit" role="alert">{{ $errors->first() }}</div>@endif
    @yield('content')
  </div></main>
</div>
</body>
</html>
