<aside class="clay-sidebar">
    <div class="sidebar-brand">
        DISTRIBUIDORA PIGÜÉ
        <small>Localidad · PV</small>
    </div>

    <nav class="sidebar-nav">
        <a href="{{ route('ventas') }}"
           class="sidebar-link {{ request()->routeIs('ventas') ? 'active' : '' }}">
            <span class="ico">🛒</span> Punto de Venta
        </a>
        <a href="{{ route('promociones') }}"
           class="sidebar-link {{ request()->routeIs('promociones') ? 'active' : '' }}">
            <span class="ico">🏷️</span> Promos
        </a>
        <a href="#" class="sidebar-link">
            <span class="ico">👥</span> Cuentas Corrientes
        </a>
        <a href="#" class="sidebar-link">
            <span class="ico">🕓</span> Historial de Ventas
        </a>
    </nav>
</aside>
