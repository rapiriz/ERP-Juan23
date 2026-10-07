<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ERP Distribuidora')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --bg: #EBF4FC;
            --sidebar-1: #1E40AF;
            --sidebar-2: #1D4ED8;
            --sidebar-3: #1E3A8A;
            --primary: #0D6EFD;
            --primary-hover: #0b5ed7;
            --danger: #DC2626;
            --text: #1E293B;
            --muted: #64748B;
            --card: rgba(255, 255, 255, 0.85);
            --border: #E2E8F0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 1rem;
            color: var(--text);
            background: var(--bg);
            height: 100%;
        }

        /* ---------- Layout general ---------- */
        .app-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            min-height: 100vh;
        }

        /* ---------- Sidebar ---------- */
        .clay-sidebar {
            background: linear-gradient(135deg, var(--sidebar-1), var(--sidebar-2), var(--sidebar-3));
            color: #fff;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar-brand {
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: .3px;
            line-height: 1.2;
        }

        .sidebar-brand small {
            display: block;
            font-weight: 400;
            font-size: .8rem;
            opacity: .7;
            margin-top: .15rem;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: .35rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .85rem 1rem;
            min-height: 48px;
            border-radius: 14px;
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            font-size: .95rem;
            transition: background .15s ease, transform .15s ease;
        }

        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateX(2px);
        }

        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.22);
            font-weight: 600;
        }

        .sidebar-link .ico {
            width: 22px;
            text-align: center;
            font-size: 1.05rem;
        }

        /* ---------- Área principal ---------- */
        .main-area {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-width: 0;
        }

        /* ---------- Claymorfismo ---------- */
        .clay-card {
            background: var(--card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 24px;
            padding: 1.25rem;
            box-shadow:
                6px 6px 16px rgba(30, 58, 138, 0.08),
                -4px -4px 12px rgba(255, 255, 255, 0.85);
        }

        /* ---------- Botones ---------- */
        .clay-btn-primary,
        .clay-btn-secondary,
        .clay-btn-danger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            min-height: 48px;
            padding: 0 1.5rem;
            border-radius: 16px;
            font-family: inherit;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: transform .15s ease, background .2s ease, box-shadow .2s ease;
        }

        .clay-btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 5px 5px 12px rgba(13, 110, 253, 0.3);
        }

        .clay-btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .clay-btn-secondary {
            background: #fff;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .clay-btn-secondary:hover {
            background: #f8fafc;
            transform: translateY(-2px);
        }

        .clay-btn-danger {
            background: var(--danger);
            color: #fff;
            box-shadow: 5px 5px 12px rgba(220, 38, 38, 0.3);
        }

        .clay-btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

        .clay-btn-primary:disabled,
        .clay-btn-secondary:disabled,
        .clay-btn-danger:disabled {
            opacity: .45;
            cursor: not-allowed;
            transform: none;
        }

        /* ---------- Inputs ---------- */
        .clay-input {
            width: 100%;
            min-height: 48px;
            padding: 0 1rem;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #fff;
            font-family: inherit;
            font-size: 1rem;
            color: var(--text);
        }

        .clay-input:focus {
            outline: 4px solid var(--primary);
            outline-offset: 2px;
            border-color: var(--primary);
        }

        /* ---------- Accesibilidad ---------- */
        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 4px solid var(--primary);
            outline-offset: 2px;
        }

        /* ---------- Utilidades ---------- */
        .muted {
            color: var(--muted);
        }
    </style>
    @stack('styles')
</head>

<body>
    <div class="app-layout">
        @include('partials.sidebar')
        <main class="main-area">
            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>

</html>