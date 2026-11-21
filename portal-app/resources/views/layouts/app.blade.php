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
    @if($me->canUseCrm())
    <span class="nav-h">CRM</span>
    <a href="{{ route('companies.index') }}" @if(request()->routeIs('companies.*')) aria-current="page" @endif>Companies</a>
    <a href="{{ route('contacts.index') }}" @if(request()->routeIs('contacts.*')) aria-current="page" @endif>Contacts</a>
    <a href="{{ route('pipeline.index') }}" @if(request()->routeIs('pipeline.*')) aria-current="page" @endif>Pipeline &amp; tenders</a>
    <a href="{{ route('tasks.index') }}" @if(request()->routeIs('tasks.*')) aria-current="page" @endif>Tasks</a>
    <span class="nav-h">Resources</span>
    <a href="{{ route('resources.index') }}" @if(request()->routeIs('resources.index', 'resources.person', 'resources.staff*')) aria-current="page" @endif>Resource planner</a>
    <a href="{{ route('resources.projects') }}" @if(request()->routeIs('resources.project*')) aria-current="page" @endif>Projects</a>
    @if(\App\Models\Dashboard::get()->contains(fn ($d) => $d->visibleTo($me)) || $me->isCompassAdmin())<a href="{{ route('reports.index') }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif>Management reports</a>@endif
    <span class="nav-h">Set-up</span>
    @endif
    @if($me->isCompassStaff())
    <a href="{{ route('sites.index') }}" @if(request()->routeIs('sites.*')) aria-current="page" @endif>Sites</a>
    <a href="{{ route('contractors.index') }}" @if(request()->routeIs('contractors.*')) aria-current="page" @endif>Contractors</a>
    <a href="{{ route('competencies.index') }}" @if(request()->routeIs('competencies.*')) aria-current="page" @endif>Competencies</a>
    <a href="{{ route('rules.index') }}" @if(request()->routeIs('rules.*')) aria-current="page" @endif>Rates &amp; rules</a>
    @endif
    @if($me->isCompassAdmin())<a href="{{ route('integrations.index') }}" @if(request()->routeIs('integrations.*')) aria-current="page" @endif>Microsoft 365</a>@endif
    <span class="soon small">Jobs, orders and the engineer app are being built</span>
  </nav>
  <main><div class="wrap">
    @if(session('status'))<div class="note ok" role="status">{{ session('status') }}</div>@endif
    @if(session('invite_link'))<div class="note warn" role="status"><b>Invite link</b> (works once, for 7 days): <input type="text" readonly value="{{ session('invite_link') }}" onclick="this.select()" style="margin-top:6px" aria-label="Invite link"></div>@endif
    @if($errors->any())<div class="note crit" role="alert">{{ $errors->first() }}</div>@endif
    @yield('content')
  </div></main>
</div>
</body>
</html>
