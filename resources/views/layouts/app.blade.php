<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dev Orchestrator')</title>
    <link rel="stylesheet" href="{{ asset('orchestrator.css') }}">
    @stack('head')
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">◇</span><span>DEV<br><b>ORCHESTRATOR</b></span></a>
        <p class="nav-label">OPERACIONES</p>
        <nav>
            <a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}">◈ &nbsp; Centro de operaciones</a>
            <a @class(['active' => request()->routeIs('requirements.*', 'tasks.*', 'executions.*')]) href="{{ route('requirements.index') }}">▤ &nbsp; Requerimientos</a>
            <a @class(['active' => request()->routeIs('projects.*')]) href="{{ route('projects.index') }}">▦ &nbsp; Proyectos</a>
            <a @class(['active' => request()->routeIs('workers.*')]) href="{{ route('workers.index') }}">◉ &nbsp; Workers</a>
            <a @class(['active' => request()->routeIs('integrations.*')]) href="{{ route('integrations.index') }}">⇄ &nbsp; Integraciones</a>
        </nav>
        <div class="sidebar-bottom"><small>{{ auth()->user()->email }}</small><form action="{{ route('logout') }}" method="post">@csrf<button class="text-button">Cerrar sesión</button></form></div>
    </aside>
    <main class="main">
        <header class="topbar"><span>DEV ORCHESTRATOR <span class="slash">/</span> @yield('breadcrumb', 'Centro de operaciones')</span><span class="top-status"><i></i> Sistema operativo</span></header>
        <div class="content">
            @if(session('ok'))<div class="alert success">{{ session('ok') }}</div>@endif
            @if($errors->any())<div class="alert error"><b>Revisa los datos:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
