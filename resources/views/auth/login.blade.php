<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | Sistema de gestión</title>
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/erp-theme.css') }}">
</head>
<body class="auth-page erp-public-theme">
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="login-title">
            <div class="brand-block">
                <span class="brand-mark">PyB</span>
                <div>
                    <h1 id="login-title">Iniciar sesión</h1>
                    <p>Sistema de gestión comercial</p>
                </div>
            </div>

            @php
                $reasonMessage = match ($reason) {
                    'session_expired' => 'La sesión expiró por inactividad. Inicie sesión nuevamente.',
                    'session_replaced' => 'La cuenta inició sesión en otro dispositivo. Inicie sesión nuevamente.',
                    default => null,
                };
            @endphp

            @if ($errors->any() || $reasonMessage)
                <div class="alert alert-error" role="alert">
                    @foreach (collect($errors->all())->unique() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                    @if ($reasonMessage)
                        <p>{{ $reasonMessage }}</p>
                    @endif
                </div>
            @endif

            @if (session('success'))
                <div class="alert alert-success" role="status">
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            <form action="{{ route('login.store') }}" method="post" class="form" data-validate="login" novalidate>
                @csrf

                <div class="field">
                    <label for="usuario">Usuario (obligatorio) <span aria-hidden="true">*</span></label>
                    <input type="text" id="usuario" name="usuario" aria-describedby="login-usuario-error" @if($errors->has('usuario')) aria-invalid="true" @endif autocomplete="username" maxlength="50" required autofocus value="{{ old('usuario') }}">
                    <small class="field-error" id="login-usuario-error" data-error-for="usuario" aria-live="polite">{{ $errors->first('usuario') }}</small>
                </div>

                <div class="field">
                    <label for="password">Contraseña (obligatoria) <span aria-hidden="true">*</span></label>
                    <input type="password" id="password" name="password" aria-describedby="login-password-error" @if($errors->has('password')) aria-invalid="true" @endif autocomplete="current-password" maxlength="255" required>
                    <small class="field-error" id="login-password-error" data-error-for="password" aria-live="polite">{{ $errors->first('password') }}</small>
                    <a class="auth-link" href="{{ route('password.request') }}">¿Olvidó su contraseña?</a>
                </div>

                <button type="submit" class="button button-primary">Iniciar sesión</button>
            </form>
            <p><a class="auth-link" href="{{ route('catalogo.index') }}">Consultar el catálogo sin iniciar sesión →</a></p>
        </section>
    </main>
    <script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>
