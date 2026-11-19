<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>@yield('title', 'Log in') · Compass MEP Portal</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono&display=swap">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body class="guest">
<header class="top"><img src="{{ asset('img/compass-mep-logo.png') }}" alt="Compass MEP"><span class="small muted">Reactive maintenance portal</span></header>
<main><div class="card">@yield('content')</div></main>
</body>
</html>
