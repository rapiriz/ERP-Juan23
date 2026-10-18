<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. PRODUCTO: agregar precio_unitario si no existe y ajustar precisión a decimal(12,2)
        if (Schema::hasTable('PRODUCTO')) {
            if (!Schema::hasColumn('PRODUCTO', 'precio_unitario')) {
                DB::statement("ALTER TABLE `PRODUCTO` ADD COLUMN `precio_unitario` DECIMAL(12,2) NULL AFTER `descripcion`");
                DB::statement("UPDATE `PRODUCTO` SET `precio_unitario` = COALESCE(`precioMay`, 0.00)");
            } else {
                DB::statement("ALTER TABLE `PRODUCTO` MODIFY COLUMN `precio_unitario` DECIMAL(12,2) NULL");
            }
            if (Schema::hasColumn('PRODUCTO', 'precioMay')) {
                DB::statement("ALTER TABLE `PRODUCTO` MODIFY COLUMN `precioMay` DECIMAL(12,2) NULL");
            }
            if (Schema::hasColumn('PRODUCTO', 'precioMin')) {
                DB::statement("ALTER TABLE `PRODUCTO` MODIFY COLUMN `precioMin` DECIMAL(12,2) NULL");
            }
        }

        // 2. COMPRA y DETALLE_COMPRA
        if (Schema::hasTable('COMPRA')) {
            DB::statement("ALTER TABLE `COMPRA` MODIFY COLUMN `importe_total` DECIMAL(12,2) NULL");
            DB::statement("ALTER TABLE `COMPRA` MODIFY COLUMN `saldo_pendiente` DECIMAL(12,2) NULL");
        }

        if (Schema::hasTable('DETALLE_COMPRA')) {
            DB::statement("ALTER TABLE `DETALLE_COMPRA` MODIFY COLUMN `precio_unitario` DECIMAL(12,2) NULL");
            DB::statement("ALTER TABLE `DETALLE_COMPRA` MODIFY COLUMN `subtotal` DECIMAL(12,2) NULL");
        }

        // 3. ORDEN_COMPRA y DETALLE_ORDEN
        if (Schema::hasTable('ORDEN_COMPRA')) {
            DB::statement("ALTER TABLE `ORDEN_COMPRA` MODIFY COLUMN `total_estimado` DECIMAL(12,2) NULL");
        }

        if (Schema::hasTable('DETALLE_ORDEN')) {
            DB::statement("ALTER TABLE `DETALLE_ORDEN` MODIFY COLUMN `precio_estimado` DECIMAL(12,2) NULL");
            DB::statement("ALTER TABLE `DETALLE_ORDEN` MODIFY COLUMN `subtotal` DECIMAL(12,2) NULL");
        }

        // 4. PRODUCTO_PROVEEDOR
        if (Schema::hasTable('PRODUCTO_PROVEEDOR')) {
            DB::statement("ALTER TABLE `PRODUCTO_PROVEEDOR` MODIFY COLUMN `precio_acordado` DECIMAL(12,2) NULL");
        }

        // 5. HISTORIAL_PRECIO y HISTORIAL_PRECIO_PROVEEDOR
        if (Schema::hasTable('HISTORIAL_PRECIO')) {
            DB::statement("ALTER TABLE `HISTORIAL_PRECIO` MODIFY COLUMN `precio` DECIMAL(12,2) NOT NULL");
            DB::statement("ALTER TABLE `HISTORIAL_PRECIO` MODIFY COLUMN `porcentaje_aumento` DECIMAL(10,2) NULL");
        }

        if (Schema::hasTable('HISTORIAL_PRECIO_PROVEEDOR')) {
            DB::statement("ALTER TABLE `HISTORIAL_PRECIO_PROVEEDOR` MODIFY COLUMN `precio_acordado` DECIMAL(12,2) NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('PRODUCTO') && Schema::hasColumn('PRODUCTO', 'precio_unitario')) {
            DB::statement("ALTER TABLE `PRODUCTO` DROP COLUMN `precio_unitario`");
        }
    }
};
