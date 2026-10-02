<?php $__env->startSection('title', 'Inicio — ERP Distribuidora'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-wrap">

    <div class="actions-grid">

        
        <div class="clay-card clay-card-hover action-card">
            <div class="action-icon">🏷️</div>
            <h2 class="action-title">Ver Promociones</h2>
            <p class="action-desc">
                Consultá las promociones vigentes, descuentos por producto y ofertas por temporada.
            </p>
            <a href="<?php echo e(route('promociones')); ?>" class="clay-btn-secondary">
                Ver promociones
            </a>
        </div>

        
        <div class="clay-card clay-card-hover action-card">
            <div class="action-icon">🛒</div>
            <h2 class="action-title">Realizar Venta</h2>
            <p class="action-desc">
                Accedé al punto de venta para registrar una nueva operación.
            </p>
            <a href="<?php echo e(route('ventas')); ?>" class="clay-btn-primary">
                Iniciar venta
            </a>
        </div>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\nicol\Desktop\PPS3Proyecto\resources\views/home.blade.php ENDPATH**/ ?>