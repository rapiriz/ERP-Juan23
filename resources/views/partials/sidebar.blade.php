<aside class="clay-sidebar">
    <div class="sidebar-header">
        <button
            class="sidebar-toggle"
            type="button"
            data-sidebar-toggle
            aria-label="Contraer menú lateral"
            aria-expanded="true"
            aria-controls="sidebar-navigation"
            title="Contraer menú lateral"
        >
            <span class="sidebar-toggle-icon" aria-hidden="true">‹</span>
        </button>
        <div class="sidebar-brand">
            DISTRIBUIDORA PIGÜÉ
            <small>Localidad · PV</small>
        </div>
    </div>

    <nav class="sidebar-nav" id="sidebar-navigation" aria-label="Navegación principal">
        <a href="{{ route('ventas') }}"
           class="sidebar-link {{ request()->routeIs('ventas') ? 'active' : '' }}"
           aria-label="Punto de Venta"
           title="Punto de Venta">
            <span class="ico" aria-hidden="true">&#x1F6D2;</span>
            <span class="sidebar-label">Punto de Venta</span>
        </a>
        <a href="{{ route('promociones') }}"
           class="sidebar-link {{ request()->routeIs('promociones') ? 'active' : '' }}"
           aria-label="Promos"
           title="Promos">
            <span class="ico" aria-hidden="true">&#x1F3F7;&#xFE0F;</span>
            <span class="sidebar-label">Promos</span>
        </a>
        <a href="{{ route('saldo') }}"
           class="sidebar-link {{ request()->routeIs('saldo') ? 'active' : '' }}"
           aria-label="Cuentas Corrientes"
           title="Cuentas Corrientes">
            <span class="ico" aria-hidden="true">&#x1F465;</span>
            <span class="sidebar-label">Cuentas Corrientes</span>
        </a>
        <a href="{{ route('ventas.historial') }}"
           class="sidebar-link {{ request()->routeIs('ventas.historial') ? 'active' : '' }}"
           aria-label="Historial de Ventas"
           title="Historial de Ventas">
            <span class="ico" aria-hidden="true">&#x1F4C2;</span>
            <span class="sidebar-label">Historial de Ventas</span>
        </a>
    </nav>
</aside>
