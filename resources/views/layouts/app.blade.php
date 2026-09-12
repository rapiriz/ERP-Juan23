<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'Conciliación Bancaria') — ERP Distribuidora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/clay-conciliacion.css') }}">
</head>
<body>
    <nav class="clay-sidebar">
        <h1>ERP Pigüé</h1>
        <a href="{{ route('conciliacion.index') }}" class="{{ request()->routeIs('conciliacion.*') ? 'activo' : '' }}">
            Conciliación Bancaria
        </a>
    </nav>
    <main>
        @if (session('mensaje'))
            <div class="alerta alerta-exito">{{ session('mensaje') }}</div>
        @endif
        @if (session('error'))
            <div class="alerta alerta-error">{{ session('error') }}</div>
        @endif

        @yield('contenido')
    </main>
</body>
</html>