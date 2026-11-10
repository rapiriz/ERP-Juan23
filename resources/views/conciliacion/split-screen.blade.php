@extends('layouts.app')
@section('title','Conciliación bancaria | Finanzas')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/g2-operations.css') }}">@endpush
@section('content')
<section id="bankApp" data-admin="{{ auth()->user()->rol->value==='administrativo' ? '1' : '0' }}">
<section class="page-heading"><p class="eyebrow">Finanzas</p><h1>Conciliación bancaria</h1><p>Importá el extracto, revisá coincidencias y conciliá uno o varios movimientos con sus operaciones reales.</p></section>
<p id="bankStatus" role="status" aria-live="polite"></p>
<section class="section-block"><form id="bankFilters" class="toolbar-form"><div class="field"><label for="bankPeriod">Período</label><select id="bankPeriod"><option value="">Todos</option></select></div><div class="field"><label for="bankSearch">Buscar en el extracto</label><input id="bankSearch" type="search" placeholder="Descripción o referencia"></div><button class="button button-primary">Actualizar</button><button type="button" id="bankAuto" class="button button-secondary">Conciliar alta confianza</button><button type="button" id="bankClose" class="button button-secondary">Cerrar período</button></form>
<details><summary>Crear un período</summary><form id="bankPeriodForm" class="toolbar-form"><label>Desde<input name="fecha_desde" type="date" required></label><label>Hasta<input name="fecha_hasta" type="date" required></label><button class="button button-primary">Crear período</button></form></details>
<details><summary>Importar extracto XLS o TXT</summary><form id="bankImport" class="toolbar-form" enctype="multipart/form-data"><label>Extracto bancario · hasta 20 MiB<input type="file" name="archivo" accept=".xls,.txt" required></label><button class="button button-primary">Importar y validar</button></form><p>Los errores de formato rechazan el archivo completo. Se conserva una copia privada y no se duplican archivos ya importados.</p></details></section>
<div class="toolbar-actions" role="group" aria-label="Secciones de conciliación">@foreach(['manual'=>'Manual','alta_confianza'=>'Alta confianza','para_revisar'=>'Para revisar','historial'=>'Historial','cheques'=>'Cheques'] as $key=>$label)<button class="button button-secondary" type="button" data-bank-tab="{{ $key }}" aria-pressed="{{ $key==='manual'?'true':'false' }}">{{ $label }}</button>@endforeach</div>
<section id="bankManual"><div class="form-grid"><section class="section-block"><h2>Movimientos del banco</h2><div class="table-scroll"><table><thead><tr><th>Seleccionar</th><th>Fecha y descripción</th><th>Importe</th><th>Estado / acción</th></tr></thead><tbody id="bankRows"></tbody></table></div></section><section class="section-block"><h2>Operaciones disponibles</h2><div class="table-scroll"><table><thead><tr><th>Seleccionar</th><th>Origen y referencia</th><th>Fecha</th><th>Importe</th></tr></thead><tbody id="bankSources"></tbody></table></div></section></div>
<section class="section-block"><p id="bankTotals">Seleccioná movimientos y operaciones para comparar sus importes.</p><button class="button button-secondary" type="button" id="bankOrganize">Revisar selección</button><button class="button button-secondary" type="button" id="bankClear">Limpiar selección</button><div id="bankConfirm" hidden><h2>Confirmar conciliación</h2><ul id="bankSelection"></ul><label for="bankNote">Nota de conciliación</label><input id="bankNote" maxlength="1000"><button type="button" class="button button-primary" id="bankReconcile">Conciliar selección</button></div></section></section>
<section class="section-block" id="bankOther" hidden></section>
<section id="bankReasonPanel" class="section-block" hidden><form id="bankReasonForm"><h2 id="bankReasonTitle">Confirmar operación</h2><label for="bankReason">Motivo o nota</label><input id="bankReason" minlength="3" maxlength="1000" required><button class="button button-primary">Confirmar</button><button type="button" class="button button-secondary" id="bankReasonCancel">Cancelar</button></form></section>
</section>
@endsection
@push('scripts')
<script src="{{ asset('js/g2-http.js') }}" defer></script>
<script src="{{ asset('js/g2-banking.js') }}" defer></script>
@endpush
