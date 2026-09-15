<?php
// ⚠️ Snippet: agrega estas líneas al array 'providers' de tu config/app.php existente.

'providers' => [
    // ... otros providers

    App\Providers\RepositoryServiceProvider::class,
    App\Providers\VencimientosServiceProvider::class,
],
