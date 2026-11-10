@if($ready ?? true)
<div class="toolbar-actions" aria-label="Exportar reporte completo">
    <a class="button button-secondary" href="{{ route('reports.export', ['report' => $report, 'format' => 'pdf', ...request()->except(['page', 'report', 'format'])]) }}">Descargar PDF</a>
    <a class="button button-secondary" href="{{ route('reports.export', ['report' => $report, 'format' => 'xlsx', ...request()->except(['page', 'report', 'format'])]) }}">Descargar Excel</a>
</div>
@endif
