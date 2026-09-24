<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ERP Distribuidora')</title>

    {{-- Inter (Google Fonts) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg:           #EBF4FC;
            --sidebar-1:    #1E40AF;
            --sidebar-2:    #1D4ED8;
            --sidebar-3:    #1E3A8A;
            --primary:      #0D6EFD;
            --primary-hover:#0b5ed7;
            --text:         #1E293B;
            --card:         rgba(255, 255, 255, 0.85);
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 1.125rem; /* 18px — regla del prototipo */
            color: var(--text);
            background: var(--bg);
        }

        /* ---------- Claymorfismo ---------- */
        .clay-card {
            background: var(--card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 28px;
            padding: 2rem;
            box-shadow:
                8px 8px 20px rgba(30, 58, 138, 0.10),
                -6px -6px 16px rgba(255, 255, 255, 0.85);
        }

        .clay-card-hover {
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .clay-card-hover:hover {
            transform: translateY(-4px);
            box-shadow:
                12px 12px 28px rgba(30, 58, 138, 0.16),
                -8px -8px 20px rgba(255, 255, 255, 0.9);
        }

        /* ---------- Botones ---------- */
        .clay-btn-primary,
        .clay-btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            min-height: 48px;       /* target accesible */
            padding: 0 1.75rem;
            border-radius: 18px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: transform .15s ease, background .2s ease, box-shadow .2s ease;
        }

        .clay-btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow:
                6px 6px 14px rgba(13, 110, 253, 0.35),
                -4px -4px 12px rgba(255, 255, 255, 0.7);
        }
        .clay-btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .clay-btn-secondary {
            background: #ffffff;
            color: var(--primary);
            box-shadow:
                6px 6px 14px rgba(30, 58, 138, 0.12),
                -4px -4px 12px rgba(255, 255, 255, 0.9);
        }
        .clay-btn-secondary:hover {
            transform: translateY(-2px);
            background: #f5f9ff;
        }

        /* ---------- Accesibilidad ---------- */
        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 4px solid var(--primary);
            outline-offset: 2px;
            border-radius: 18px;
        }

        /* ---------- Layout ---------- */
        .page-wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 .5rem;
        }
        .page-subtitle {
            font-size: 1.05rem;
            color: #475569;
            margin: 0 0 2.5rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            align-items: flex-start;
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
            color: var(--primary);
            font-size: 1.5rem;
        }

        .action-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }
        .action-desc {
            font-size: .98rem;
            color: #475569;
            margin: 0;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
