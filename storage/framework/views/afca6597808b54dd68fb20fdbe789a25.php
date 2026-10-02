<aside class="clay-sidebar">
    <div class="sidebar-brand">
        DISTRIBUIDORA PIGÜÉ
        <small>Localidad · PV</small>
    </div>

    <nav class="sidebar-nav">
        <a href="<?php echo e(route('ventas')); ?>"
           class="sidebar-link <?php echo e(request()->routeIs('ventas') ? 'active' : ''); ?>">
            <span class="ico">🛒</span> Punto de Venta
        </a>
        <a href="<?php echo e(route('promociones')); ?>"
           class="sidebar-link <?php echo e(request()->routeIs('promociones') ? 'active' : ''); ?>">
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
<?php /**PATH C:\Users\nicol\Desktop\PPS3Proyecto\resources\views/partials/sidebar.blade.php ENDPATH**/ ?>