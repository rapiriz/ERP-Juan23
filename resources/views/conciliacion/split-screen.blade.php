@extends('layouts.app')

@section('titulo', 'Conciliación Bancaria')
@section('layout-class', 'layout-conciliacion')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/g2-finanzas.css') }}">

<style>

/* Layout y Contenedor a ancho completo */
.layout.layout-conciliacion,
.layout:has(.conciliacion-container) {
    width: 100% !important;
    max-width: 100% !important;
    padding: 1.25rem 2rem !important;
    margin: 0 !important;
    box-sizing: border-box !important;
}
.conciliacion-container {
    padding: 0 !important;
    max-width: 100% !important;
    margin: 0 !important;
    width: 100% !important;
    font-family: 'Inter', system-ui, sans-serif;
    box-sizing: border-box;
}
.conciliacion-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
    width: 100%;
}
.conciliacion-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1E293B;
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.btn-action {
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.9rem;
    border: none;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.btn-primary { background: #2563EB; color: #FFFFFF; }
.btn-primary:hover { background: #1D4ED8; }
.btn-secondary { background: #E2E8F0; color: #334155; }
.btn-secondary:hover { background: #CBD5E1; }
.btn-success { background: #16A34A; color: #FFFFFF; }
.btn-success:hover { background: #15803D; }
.btn-gear {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 0.6rem 0.85rem;
    cursor: pointer;
    font-size: 1.15rem;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    transition: all 0.15s;
}
.btn-gear:hover {
    background: #F1F5F9;
    color: #1E293B;
    border-color: #94A3B8;
}

/* Paneles de Filtros */
.filter-panel {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.filter-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #334155;
}
.filter-chips-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
}
.filter-chip {
    background: #EFF6FF;
    color: #1E40AF;
    border: 1px solid #BFDBFE;
    padding: 0.2rem 0.55rem;
    border-radius: 14px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    align-items: end;
}
.filter-advanced-toggle {
    background: none;
    border: none;
    color: #2563EB;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    margin-top: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.filter-advanced-section {
    display: none;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px dashed #CBD5E1;
}
.filter-advanced-section.expanded { display: grid; }

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.filter-group label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748B;
}
.filter-input {
    padding: 0.45rem 0.65rem;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    font-size: 0.9rem;
    outline: none;
}
.filter-input:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.15);
}
/* Multi-select Dropdown y Toggles de Sentido */
.multiselect-wrapper {
    position: relative;
    width: 100%;
}
.multiselect-trigger {
    width: 100%;
    padding: 0.45rem 0.65rem;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    font-size: 0.9rem;
    background: #FFFFFF;
    color: #1E293B;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    text-align: left;
    min-height: 36px;
    box-sizing: border-box;
}
.multiselect-trigger:hover {
    border-color: #94A3B8;
}
.multiselect-trigger.active {
    border-color: #2563EB;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.15);
}
.multiselect-trigger-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding-right: 0.4rem;
}
.multiselect-menu {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    min-width: 245px;
    width: 100%;
    max-height: 310px;
    overflow-y: auto;
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.12);
    z-index: 50;
    padding: 0.35rem 0;
}
.multiselect-menu.open {
    display: block;
}
.multiselect-actions-bar {
    display: flex;
    justify-content: flex-start;
    gap: 0.45rem;
    padding: 0.4rem 0.75rem 0.5rem 0.75rem;
    border-bottom: 1px solid #E2E8F0;
    margin-bottom: 0.25rem;
    background: #F8FAFC;
}
.btn-ms-action {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 5px;
    color: #334155;
    font-size: 0.76rem;
    font-weight: 600;
    padding: 0.22rem 0.6rem;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-ms-action:hover {
    background: #EFF6FF;
    border-color: #2563EB;
    color: #1D4ED8;
}
.multiselect-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.45rem 0.85rem;
    font-size: 0.85rem;
    color: #1E293B;
    cursor: pointer;
    user-select: none;
    line-height: 1.3;
}
.multiselect-option:hover {
    background: #F1F5F9;
}
.multiselect-option input[type="checkbox"] {
    width: 15px !important;
    height: 15px !important;
    min-width: 15px !important;
    max-width: 15px !important;
    margin: 0 !important;
    padding: 0 !important;
    cursor: pointer;
    flex-shrink: 0;
    accent-color: #2563EB;
}
.signo-toggle-group {
    display: flex;
    gap: 0.4rem;
    width: 100%;
}
.btn-signo-toggle {
    flex: 1;
    padding: 0.45rem 0.5rem;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    background: #FFFFFF;
    color: #475569;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
}
.btn-signo-toggle:hover {
    background: #F8FAFC;
    border-color: #94A3B8;
}
.btn-signo-toggle.active-ingreso {
    background: #DCFCE7;
    border-color: #16A34A;
    color: #166534;
}
.btn-signo-toggle.active-egreso {
    background: #FEE2E2;
    border-color: #DC2626;
    color: #991B1B;
}
.filter-chip-close {
    background: none;
    border: none;
    color: #1E40AF;
    font-weight: 700;
    cursor: pointer;
    padding: 0 0 0 2px;
    font-size: 0.95rem;
    line-height: 1;
}
.filter-chip-close:hover {
    color: #DC2626;
}
/* Split-screen tables */
.split-screen-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 992px) {
    .split-screen-grid { grid-template-columns: 1fr; }
}
.table-panel {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    height: 540px; /* Misma altura fija */
}
.table-header-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    font-size: 1rem;
    font-weight: 700;
    color: #1E293B;
}
.table-controls-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.82rem;
    color: #64748B;
    margin-bottom: 0.5rem;
}
.table-responsive {
    overflow-y: auto;
    overflow-x: auto;
    flex-grow: 1;
    position: relative;
    border: 1px solid #F1F5F9;
    border-radius: 6px;
}
.table-erp {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    .table-erp th {
        background: #F8FAFC;
        color: #475569;
        font-weight: 600;
        text-align: left;
        padding: 0.55rem 0.5rem;
        border-bottom: 2px solid #CBD5E1;
        border-right: 1px solid #E2E8F0;
        position: sticky;
        top: 0;
        z-index: 5;
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: background 0.15s;
    }
    .table-erp th:last-child,
    .table-erp td:last-child {
        border-right: none;
    }
    .table-erp th:hover:not(.th-fixed) {
        background: #EEF2FF;
        color: #1D4ED8;
    }
    .table-erp th .th-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 3px;
        overflow: hidden;
    }
    .table-erp th .th-title {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .table-erp th .th-short {
        display: none;
        font-weight: 700;
        font-size: 0.78rem;
    }
    .table-erp th .th-toggle-icon {
        font-size: 0.72rem;
        color: #94A3B8;
        flex-shrink: 0;
        padding: 0 2px;
        border-radius: 3px;
    }
    .table-erp th:hover .th-toggle-icon {
        color: #2563EB;
        background: #DBEAFE;
    }
    .table-erp td {
        padding: 0.52rem 0.5rem;
        border-bottom: 1px solid #E2E8F0;
        border-right: 1px solid #F1F5F9;
        color: #1E293B;
        vertical-align: middle;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .table-erp tbody tr:hover td {
        filter: brightness(0.975);
    }

    /* Celda y checkbox fijos sin contorno azul al hacer clic */
    .table-erp col.col-fixed-chk,
    .table-erp th.th-fixed,
    .table-erp td.td-fixed-chk {
        width: 34px !important;
        min-width: 34px !important;
        max-width: 34px !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        text-align: center !important;
    }
    .table-erp input[type="checkbox"] {
        width: 15px !important;
        height: 15px !important;
        margin: 0 auto !important;
        vertical-align: middle;
        cursor: pointer;
        accent-color: #2563EB;
        outline: none !important;
        box-shadow: none !important;
    }
    .table-erp input[type="checkbox"]:focus,
    .table-erp input[type="checkbox"]:focus-visible {
        outline: none !important;
        box-shadow: none !important;
    }

    /* Fila seleccionada: indicador lateral elegante que respeta el color de fondo del estado */
    .table-erp tr.selected-row td:first-child {
        box-shadow: inset 4px 0 0 #2563EB;
    }
    .table-erp tr.selected-row td {
        font-weight: 600;
        border-bottom-color: #93C5FD;
    }

    /* Badge de estado neutro para convivir armónicamente con cualquier fondo pastel */
    .badge-status {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 5px;
        font-size: 0.74rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.78) !important;
        color: #334155 !important;
        border: 1px solid #CBD5E1 !important;
    }

    /* Columna contraída: muestra la inicial exacta en mayúscula + indicador */
    .table-erp col.col-contracted,
    .table-erp th.col-contracted,
    .table-erp td.col-contracted {
        width: 38px !important;
        min-width: 38px !important;
        max-width: 38px !important;
        padding-left: 2px !important;
        padding-right: 2px !important;
        text-align: center !important;
    }
    .table-erp th.col-contracted .th-inner {
        justify-content: center;
        gap: 1px;
    }
    .table-erp th.col-contracted .th-title {
        display: none;
    }
    .table-erp th.col-contracted .th-short {
        display: inline-block;
        color: #1D4ED8;
    }
    .table-erp th.col-contracted .th-toggle-icon {
        color: #2563EB;
        font-size: 0.72rem;
        padding: 0;
    }
    .col-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        background: #94A3B8;
        border-radius: 50%;
        vertical-align: middle;
    }

    /* Footer de paginación inferior centrado */
    .table-footer-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.65rem 0.9rem;
        background: #F8FAFC;
        border-top: 1px solid #E2E8F0;
        font-size: 0.8rem;
        color: #475569;
    }
    .pagination-spacer {
        flex: 1;
    }
    .pagination-buttons {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        flex-shrink: 0;
    }
    .btn-page {
        min-width: 28px;
        height: 28px;
        padding: 0 0.45rem;
        border: 1px solid #CBD5E1;
        background: #FFFFFF;
        color: #334155;
        border-radius: 6px;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.12s;
    }
    .btn-page:hover:not(:disabled):not(.active) {
        background: #EFF6FF;
        border-color: #93C5FD;
        color: #1D4ED8;
    }
    .btn-page.active {
        background: #2563EB;
        border-color: #2563EB;
        color: #FFFFFF;
    }
    .btn-page:disabled {
        opacity: 0.45;
        cursor: not-allowed;
        background: #F1F5F9;
    }
    .per-page-selector,
    .per-page-selector span {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.4rem;
        font-size: 0.78rem;
        color: #1E293B !important;
        white-space: nowrap;
    }
    .per-page-selector select {
        width: 58px;
        min-width: 58px;
        max-width: 58px;
        border: 1px solid #CBD5E1;
        border-radius: 6px;
        padding: 3px 4px;
        font-size: 0.78rem;
        background: #FFFFFF;
        color: #1E293B;
        cursor: pointer;
    }

    /* Colores configurables de estados (Banco y Rendiciones) */
    .row-state-pendiente { background-color: var(--color-estado-pendiente, #FFFFFF); }
    .row-state-matcheado { background-color: var(--color-estado-matcheado, #ECFDF5); }
    .row-state-en_disputa { background-color: var(--color-estado-en_disputa, #FEF2F2); }
    .row-state-no_aplica { background-color: var(--color-estado-no_aplica, #F8FAFC); }
    .row-state-revision_manual { background-color: var(--color-estado-revision_manual, #FEFCE8); }
    .row-state-cerrado_definitivo { background-color: var(--color-estado-cerrado_definitivo, #F1F5F9); }

    .row-erp-aprobada { background-color: var(--color-erp-aprobada, #ECFDF5); }
    .row-erp-pendiente { background-color: var(--color-erp-pendiente, #FFFFFF); }
    .row-erp-rechazada { background-color: var(--color-erp-rechazada, #FEF2F2); }

    .config-color-section-title {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #64748B;
        margin: 0.9rem 0 0.45rem 0;
    }
    .config-color-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    .config-color-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        padding: 0.48rem 0.75rem;
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
    }
    .config-color-label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #1E293B;
    }
    .palette-swatches {
        display: flex;
        align-items: center;
        gap: 5px;
        flex-wrap: wrap;
    }
    .color-swatch {
        width: 21px;
        height: 21px;
        border-radius: 50%;
        border: 1px solid #CBD5E1;
        cursor: pointer;
        padding: 0;
        transition: transform 0.1s, box-shadow 0.1s;
    }
    .color-swatch:hover {
        transform: scale(1.12);
    }
    .color-swatch.active {
        border: 2px solid #2563EB;
        box-shadow: 0 0 0 2px #DBEAFE;
        transform: scale(1.1);
    }
.config-color-picker {
    width: 38px;
    height: 32px;
    padding: 2px;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    cursor: pointer;
    background: #FFFFFF;
}
.btn-color-reset {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #64748B;
    transition: all 0.15s ease;
    padding: 0;
}
.btn-color-reset:hover {
    background: #F1F5F9;
    color: #1E293B;
    border-color: #94A3B8;
}

.badge-status {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-pendiente { background: #FEF3C7; color: #92400E; }
.badge-matcheado { background: #DCFCE7; color: #166534; }
.badge-en_disputa { background: #FEE2E2; color: #991B1B; }
.badge-no_aplica { background: #F1F5F9; color: #475569; }
.badge-revision_manual { background: #E0E7FF; color: #3730A3; }
.badge-cerrado_definitivo { background: #E2E8F0; color: #1E293B; }

/* Aviso para organizar selecciones */
.selection-notice-banner {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    background: #EFF6FF;
    border: 1px solid #BFDBFE;
    color: #1E40AF;
    padding: 0.85rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.25rem;
    font-size: 0.88rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    width: 100%;
    box-sizing: border-box;
}
.selection-notice-icon {
    flex-shrink: 0;
    color: #2563EB;
    display: flex;
    align-items: center;
}
.selection-notice-text {
    flex-grow: 1;
    line-height: 1.45;
}

/* Barra Contextual */
.contextual-bar {
    background: #1E293B;
    color: #FFFFFF;
    padding: 0.85rem 1.5rem;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.15);
    width: 100%;
    box-sizing: border-box;
}

/* Área de Conciliación */
.reconciliation-area {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 1.25rem;
    width: 100%;
    box-sizing: border-box;
}
.reconciliation-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.85rem;
}
.reconciliation-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1E293B;
    margin: 0;
}
.nav-tabs {
    display: flex;
    width: 100%;
    gap: 0.5rem;
    border-bottom: 2px solid #E2E8F0;
    margin-bottom: 1.25rem;
}
.nav-tab-btn {
    flex: 1 1 0;
    padding: 0.75rem 1rem;
    border: 1px solid #E2E8F0;
    border-bottom: 2px solid transparent;
    background: #F8FAFC;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.92rem;
    color: #64748B;
    border-radius: 8px 8px 0 0;
    text-align: center;
    transition: all 0.2s ease;
    margin-bottom: -2px;
}
.nav-tab-btn:hover {
    background: #F1F5F9;
    color: #1E293B;
}
.nav-tab-btn.active {
    color: #2563EB;
    background: #FFFFFF;
    border-top: 3px solid #2563EB;
    border-left: 1px solid #CBD5E1;
    border-right: 1px solid #CBD5E1;
    border-bottom: 2px solid #FFFFFF;
    font-weight: 700;
    box-shadow: 0 -2px 4px rgba(0,0,0,0.02);
}

/* Tarjetas de Grupos */
.group-card {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.group-card.incomplete {
    border-left: 5px solid #F59E0B;
    background: #FFFBEB;
}
.group-card.complete {
    border-left: 5px solid #16A34A;
}

/* Modal y Scroll Unificado General */
.modal-overlay {
    display: none !important;
    position: fixed !important;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.6) !important;
    z-index: 99999 !important;
    align-items: center !important;
    justify-content: center !important;
}

.modal-overlay.active {
    display: flex !important;
}
.modal-box.maximized {
    width: 96vw !important;
    height: 92vh !important;
    max-width: 96vw !important;
    max-height: 92vh !important;
}
.modal-box {
    background: #FFFFFF;
    border-radius: 12px;
    max-width: 1100px;
    width: 94%;
    padding: 1.5rem;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25);
    max-height: 90vh;
    overflow-y: auto; /* Única barra general del modal */
    transition: all 0.2s ease;
    position: relative;
}
.modal-header {
    position: sticky;
    top: -1.5rem;
    background: #FFFFFF;
    z-index: 10;
    margin: -1.5rem -1.5rem 1rem -1.5rem;
    padding: 1.5rem 1.5rem 0.75rem 1.5rem;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Encabezados y contenedor de selección en el modal */
.organizar-headers-row {
    display: grid;
    grid-template-columns: 1fr 150px 1fr;
    align-items: center;
    margin-bottom: 0.5rem;
    padding: 0 0.35rem;
}
.organizar-col-header {
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #334155;
    text-align: center;
}
.organizar-scroll-viewport {
    border: none;
    background: transparent;
    overflow: visible !important;
    position: relative;
    margin-bottom: 1.25rem;
}
.organizar-container {
    display: grid;
    grid-template-columns: 1fr 150px 1fr;
    gap: 0;
    padding: 0.25rem 0.35rem;
    position: relative;
    align-items: stretch;
}
.organizar-col {
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    gap: 0.6rem;
    z-index: 2;
}
.organizar-item {
    background: #FFFFFF;
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    padding: 0.55rem 0.7rem;
    font-size: 0.82rem;
    cursor: pointer;
    user-select: none;
    position: relative;
    transition: all 0.15s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.organizar-item:hover {
    border-color: #2563EB;
    box-shadow: 0 3px 6px rgba(37,99,235,0.12);
}
.organizar-item.active-connect {
    border-color: #2563EB;
    background: #EFF6FF;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.18);
}
.organizar-item.is-connected {
    border-left: 4px solid #16A34A;
}
.organizar-item-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.2rem;
}
.organizar-item-desc {
    font-size: 0.78rem;
    color: #475569;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.connections-canvas-col {
    position: relative;
    z-index: 1;
}
#connectionsSvg {
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0;
    left: 0;
    pointer-events: none;
}
.conn-line {
    fill: none;
    stroke: #2563EB;
    stroke-width: 2.5;
    cursor: pointer;
    pointer-events: stroke;
    transition: stroke 0.15s, stroke-width 0.15s;
}
.conn-line:hover {
    stroke: #DC2626;
    stroke-width: 4;
}
.organizar-preview-section {
    margin-top: 1rem;
    padding-top: 0.9rem;
    border-top: 2px dashed #CBD5E1;
}
.organizar-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.6rem;
    overflow: visible !important;
    max-height: none !important;
    padding-right: 2px;
}
.preview-group-card {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-left: 4px solid #16A34A;
    border-radius: 7px;
    padding: 0.5rem 0.65rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.preview-group-card.incomplete {
    background: #FFFBEB;
    border-color: #FCD34D;
    border-left: 4px solid #F59E0B;
}

/* Canvas y Conexiones en Ventana Organizar */
.organizar-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}
.organizar-history-btns {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.btn-history-icon {
    background: #F8FAFC;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    padding: 0.35rem 0.65rem;
    font-size: 1rem;
    font-weight: 700;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-history-icon:hover:not(:disabled) {
    background: #EFF6FF;
    border-color: #2563EB;
    color: #1D4ED8;
}
.btn-history-icon:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.organizar-headers-row {
    display: grid;
    grid-template-columns: 1fr 150px 1fr;
    align-items: center;
    margin-bottom: 0.45rem;
    padding: 0 0.35rem;
}
.organizar-col-header {
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #334155;
    text-align: center;
}
.organizar-container {
    display: grid;
    grid-template-columns: 1fr 150px 1fr;
    gap: 0;
    padding: 0.25rem 0.35rem;
    position: relative;
    align-items: stretch;
}
.organizar-col {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.55rem;
    z-index: 2;
}
.organizar-item {
    background: #FFFFFF;
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    padding: 0.55rem 0.7rem;
    font-size: 0.82rem;
    cursor: pointer;
    user-select: none;
    position: relative;
    transition: all 0.15s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.organizar-item:hover {
    border-color: #2563EB;
    box-shadow: 0 3px 6px rgba(37,99,235,0.12);
}
.organizar-item.active-connect {
    border-color: #2563EB;
    background: #EFF6FF;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.18);
}
.organizar-item.is-connected {
    border-left: 4px solid #16A34A;
}
.organizar-item-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.2rem;
}
.organizar-item-desc {
    font-size: 0.78rem;
    color: #475569;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.connections-canvas-col {
    position: relative;
    z-index: 1;
}
#connectionsSvg {
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0;
    left: 0;
    pointer-events: none;
}
.conn-line {
    fill: none;
    stroke: #2563EB;
    stroke-width: 2.5;
    cursor: pointer;
    pointer-events: stroke;
    transition: stroke 0.15s, stroke-width 0.15s;
}
.conn-line:hover {
    stroke: #DC2626;
    stroke-width: 4;
}
.organizar-preview-section {
    margin-top: 1rem;
    padding-top: 0.9rem;
    border-top: 2px dashed #CBD5E1;
}
.organizar-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.6rem;
    padding-right: 2px;
}
.preview-group-card {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-left: 4px solid #16A34A;
    border-radius: 7px;
    padding: 0.5rem 0.65rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.preview-group-card.incomplete {
    background: #FFFBEB;
    border-color: #FCD34D;
    border-left: 4px solid #F59E0B;
}
/* Pestañas de conciliación sin borde azul de foco */
.nav-tab-btn:focus,
.nav-tab-btn:focus-visible,
.nav-tab-btn:active {
    outline: none !important;
    box-shadow: none !important;
}

/* Tarjetas de grupos y propuestas compactas y profesionales */
.group-card {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    padding: 0.65rem 0.85rem;
    margin-bottom: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    font-size: 0.85rem;
}
.group-card.incomplete {
    border-left: 4px solid #F59E0B;
    background: #FFFBEB;
}
.group-card.complete {
    border-left: 4px solid #16A34A;
}
.group-card input[type="checkbox"] {
    width: 15px !important;
    height: 15px !important;
    min-width: 15px !important;
    cursor: pointer;
    margin: 0 !important;
    outline: none !important;
    box-shadow: none !important;
}
/* Estilos para el nuevo formato paralelo en Conciliación manual */
.manual-match-row {
    display: grid;
    grid-template-columns: 1fr 60px 1fr;
    align-items: center;
    gap: 0.5rem;
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 10px;
    padding: 0.75rem;
    margin-bottom: 0.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    position: relative;
}
.manual-match-row.incomplete {
    border-left: 5px solid #F59E0B;
    background: #FFFBEB;
}
.manual-match-row.complete {
    border-left: 5px solid #16A34A;
}
.manual-card-side {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 0.5rem 0.65rem;
    font-size: 0.82rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-height: 55px;
    justify-content: center;
}
.manual-line-connector {
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    height: 100%;
}
.manual-line-connector::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 2px;
    background: #94A3B8;
}

</style>
@endpush

@section('contenido')
<div class="conciliacion-container">
    {{-- 1. Encabezado y acciones generales --}}
    <div class="conciliacion-header">
        <div>
            <h1>Conciliación Bancaria</h1>
        </div>
        <div class="header-actions">
            <button type="button" class="btn-gear" onclick="abrirConfiguracion()" title="Configuración de cuentas, apariencias y estados">⚙</button>
            <button type="button" class="btn-action btn-primary" onclick="abrirModalExtracto()">Subir extracto bancario</button>
        </div>
    </div>

    {{-- 2. Filtros de Movimientos Bancarios (Ancho completo) --}}
    <div class="filter-panel" id="panel_filtros_banco">
        <div class="filter-panel-header">
            <span>Filtros de movimientos bancarios</span>
            <button type="button" onclick="limpiarFiltrosBancarios()" style="background:none; border:none; color:#2563EB; cursor:pointer; font-size:0.85rem; font-weight:600;">Limpiar filtros</button>
        </div>
        <div class="filter-chips-container" id="chips_banco"></div>

        {{-- Fila principal (4 filtros básicos) --}}
        <div class="filter-grid">
            <div class="filter-group">
                <label>Fecha de operación desde</label>
                <input type="date" id="banco_fecha_desde" class="filter-input" onchange="actualizarFiltrosBanco()">
            </div>
            <div class="filter-group">
                <label>Hasta</label>
                <input type="date" id="banco_fecha_hasta" class="filter-input" onchange="actualizarFiltrosBanco()">
            </div>

            {{-- Multi-select: Estado de conciliación --}}
            <div class="filter-group">
                <label>Estado de conciliación</label>
                <div class="multiselect-wrapper" id="ms_banco_estado">
                    <button type="button" class="multiselect-trigger" onclick="toggleMultiselect('ms_banco_estado', event)">
                        <span class="multiselect-trigger-text" id="ms_banco_estado_label">Pendiente</span>
                        <span style="font-size:0.75rem; color:#64748B;">▼</span>
                    </button>
                    <div class="multiselect-menu" onclick="event.stopPropagation()">
                        <div class="multiselect-actions-bar">
                            <button type="button" class="btn-ms-action" onclick="seleccionarTodosMultiselect('estado', true)">Todos</button>
                            <button type="button" class="btn-ms-action" onclick="seleccionarTodosMultiselect('estado', false)">Ninguno</button>
                            <button type="button" class="btn-ms-action" onclick="restablecerPredeterminadoMultiselect('estado')">Predeterminado</button>
                        </div>
                        <label class="multiselect-option">
                            <span class="badge-status badge-pendiente">Pendiente</span>
                            <input type="checkbox" value="pendiente" class="chk-banco-estado" checked onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span class="badge-status badge-revision_manual">Revisión manual</span>
                            <input type="checkbox" value="revision_manual" class="chk-banco-estado" onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span class="badge-status badge-en_disputa">En disputa</span>
                            <input type="checkbox" value="en_disputa" class="chk-banco-estado" onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span class="badge-status badge-matcheado">Matcheado</span>
                            <input type="checkbox" value="matcheado" class="chk-banco-estado" onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span class="badge-status badge-no_aplica">No aplica</span>
                            <input type="checkbox" value="no_aplica" class="chk-banco-estado" onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span class="badge-status badge-cerrado_definitivo">Cerrado definitivo</span>
                            <input type="checkbox" value="cerrado_definitivo" class="chk-banco-estado" onchange="actualizarFiltrosBanco()">
                        </label>
                    </div>
                </div>
            </div>

            {{-- Multi-select: Tipo de movimiento (10 opciones planas, todas marcadas por defecto) --}}
            <div class="filter-group">
                <label>Tipo de movimiento</label>
                <div class="multiselect-wrapper" id="ms_banco_tipo">
                    <button type="button" class="multiselect-trigger" onclick="toggleMultiselect('ms_banco_tipo', event)">
                        <span class="multiselect-trigger-text" id="ms_banco_tipo_label">Todos</span>
                        <span style="font-size:0.75rem; color:#64748B;">▼</span>
                    </button>
                    <div class="multiselect-menu" onclick="event.stopPropagation()">
                        <div class="multiselect-actions-bar">
                            <button type="button" class="btn-ms-action" onclick="seleccionarTodosMultiselect('tipo', true)">Todos</button>
                            <button type="button" class="btn-ms-action" onclick="seleccionarTodosMultiselect('tipo', false)">Ninguno</button>
                            <button type="button" class="btn-ms-action" onclick="restablecerPredeterminadoMultiselect('tipo')">Predeterminado</button>
                        </div>
                        <label class="multiselect-option">
                            <span>Cobranza cliente</span>
                            <input type="checkbox" value="cobranza_cliente" class="chk-banco-tipo" checked onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span>Transferencia propia</span>
                            <input type="checkbox" value="transferencia_propia" class="chk-banco-tipo" checked onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span>Gasto bancario</span>
                            <input type="checkbox" value="gasto_bancario" class="chk-banco-tipo" checked onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span>Impuesto bancario</span>
                            <input type="checkbox" value="impuesto_bancario" class="chk-banco-tipo" checked onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span>Débito / Rechazo</span>
                            <input type="checkbox" value="debito_rechazo" class="chk-banco-tipo" checked onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span>Pago a proveedor</span>
                            <input type="checkbox" value="pago_proveedor" class="chk-banco-tipo" checked onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span>Pago de sueldos</span>
                            <input type="checkbox" value="pago_sueldos" class="chk-banco-tipo" checked onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span>Pago de servicios</span>
                            <input type="checkbox" value="pago_servicios" class="chk-banco-tipo" checked onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span>Desconocido</span>
                            <input type="checkbox" value="desconocido" class="chk-banco-tipo" checked onchange="actualizarFiltrosBanco()">
                        </label>
                        <label class="multiselect-option">
                            <span>Otro</span>
                            <input type="checkbox" value="otro" class="chk-banco-tipo" checked onchange="actualizarFiltrosBanco()">
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="filter-advanced-toggle" id="btn_toggle_adv_banco" onclick="toggleFiltrosAvanzados('adv_banco', this)">
            <span>▼ Filtros avanzados</span>
        </button>

        {{-- Sección Avanzada --}}
        <div class="filter-grid filter-advanced-section" id="adv_banco">
            <div class="filter-group">
                <label>Importe mín.</label>
                <input type="number" step="0.01" id="banco_monto_min" class="filter-input" placeholder="Ej: -15000.00" onchange="actualizarFiltrosBanco()">
            </div>
            <div class="filter-group">
                <label>Importe máx.</label>
                <input type="number" step="0.01" id="banco_monto_max" class="filter-input" placeholder="Ej: 8000.00" onchange="actualizarFiltrosBanco()">
            </div>
            <div class="filter-group">
                <label>Sentido del movimiento</label>
                <div class="signo-toggle-group">
                    <button type="button" id="btn_signo_ingreso" class="btn-signo-toggle" onclick="toggleSignoBanco('ingreso')">+ Solo ingresos</button>
                    <button type="button" id="btn_signo_egreso" class="btn-signo-toggle" onclick="toggleSignoBanco('egreso')">- Solo egresos</button>
                </div>
            </div>
            <div class="filter-group">
                <label>CUIT (11 dígitos)</label>
                <input type="text" id="banco_cuit" class="filter-input" maxlength="11" inputmode="numeric" placeholder="Ej: 30712345678" oninput="filtrarInputCuit(this)">
            </div>
            <div class="filter-group">
                <label>N° Comprobante / Operación</label>
                <input type="text" id="banco_comprobante" class="filter-input" placeholder="Ej: 984512" oninput="debouncedActualizarBanco()">
            </div>
            <div class="filter-group">
                <label>Nombre / Descripción</label>
                <input type="text" id="banco_q" class="filter-input" placeholder="Apellido, empresa o concepto..." oninput="debouncedActualizarBanco()">
            </div>
            <div class="filter-group">
                <label>N° de Movimiento / ID(s)</label>
                <input type="text" id="banco_ids_movimiento" class="filter-input" placeholder="Ej: 12, 45, 108" oninput="filtrarInputIdsBanco(this)">
            </div>
        </div>
    </div>

    {{-- 3. Filtros de Rendiciones (Ancho completo) --}}
    <div class="filter-panel" id="panel_filtros_erp">
        <div class="filter-panel-header">
            <span>Filtros de rendiciones</span>
            <button type="button" onclick="limpiarFiltrosCobranzas()" style="background:none; border:none; color:#2563EB; cursor:pointer; font-size:0.85rem; font-weight:600;">Limpiar filtros</button>
        </div>
        <div class="filter-chips-container" id="chips_erp"></div>

        {{-- Fila principal (4 filtros básicos) --}}
        <div class="filter-grid">
            <div class="filter-group">
                <label>Fecha de cobro desde</label>
                <input type="date" id="erp_fecha_desde" class="filter-input" onchange="actualizarFiltrosErp()">
            </div>
            <div class="filter-group">
                <label>Hasta</label>
                <input type="date" id="erp_fecha_hasta" class="filter-input" onchange="actualizarFiltrosErp()">
            </div>

            {{-- Multi-select: Estado de rendición --}}
            <div class="filter-group">
                <label>Estado de rendición</label>
                <div class="multiselect-wrapper" id="ms_erp_estado">
                    <button type="button" class="multiselect-trigger" onclick="toggleMultiselect('ms_erp_estado', event)">
                        <span class="multiselect-trigger-text" id="ms_erp_estado_label">Aprobada</span>
                        <span style="font-size:0.75rem; color:#64748B;">▼</span>
                    </button>
                    <div class="multiselect-menu" onclick="event.stopPropagation()">
                        <div class="multiselect-actions-bar">
                            <button type="button" class="btn-ms-action" onclick="seleccionarTodosMultiselectErp('estado', true)">Todos</button>
                            <button type="button" class="btn-ms-action" onclick="seleccionarTodosMultiselectErp('estado', false)">Ninguno</button>
                            <button type="button" class="btn-ms-action" onclick="restablecerPredeterminadoMultiselectErp('estado')">Predeterminado</button>
                        </div>
                        <label class="multiselect-option">
                            <span>Aprobada</span>
                            <input type="checkbox" value="aprobada" class="chk-erp-estado" checked onchange="actualizarFiltrosErp()">
                        </label>
                        <label class="multiselect-option">
                            <span>Pendiente</span>
                            <input type="checkbox" value="pendiente" class="chk-erp-estado" onchange="actualizarFiltrosErp()">
                        </label>
                        <label class="multiselect-option">
                            <span>Rechazada</span>
                            <input type="checkbox" value="rechazada" class="chk-erp-estado" onchange="actualizarFiltrosErp()">
                        </label>
                    </div>
                </div>
            </div>

            {{-- Multi-select: Medio de pago (Transferencia y Cheque) --}}
            <div class="filter-group">
                <label>Medio de pago</label>
                <div class="multiselect-wrapper" id="ms_erp_medio">
                    <button type="button" class="multiselect-trigger" onclick="toggleMultiselect('ms_erp_medio', event)">
                        <span class="multiselect-trigger-text" id="ms_erp_medio_label">Todos</span>
                        <span style="font-size:0.75rem; color:#64748B;">▼</span>
                    </button>
                    <div class="multiselect-menu" onclick="event.stopPropagation()">
                        <div class="multiselect-actions-bar">
                            <button type="button" class="btn-ms-action" onclick="seleccionarTodosMultiselectErp('medio', true)">Todos</button>
                            <button type="button" class="btn-ms-action" onclick="seleccionarTodosMultiselectErp('medio', false)">Ninguno</button>
                            <button type="button" class="btn-ms-action" onclick="restablecerPredeterminadoMultiselectErp('medio')">Predeterminado</button>
                        </div>
                        <label class="multiselect-option">
                            <span>Transferencia</span>
                            <input type="checkbox" value="transferencia" class="chk-erp-medio" checked onchange="actualizarFiltrosErp()">
                        </label>
                        <label class="multiselect-option">
                            <span>Cheque</span>
                            <input type="checkbox" value="cheque" class="chk-erp-medio" checked onchange="actualizarFiltrosErp()">
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="filter-advanced-toggle" id="btn_toggle_adv_erp" onclick="toggleFiltrosAvanzados('adv_erp', this)">
            <span>▼ Filtros avanzados</span>
        </button>

        {{-- Sección Avanzada (4 filtros específicos) --}}
        <div class="filter-grid filter-advanced-section" id="adv_erp">
            <div class="filter-group">
                <label>Importe mín.</label>
                <input type="number" step="0.01" min="0" id="erp_monto_min" class="filter-input" placeholder="Ej: 0.00" oninput="validarSoloPositivo(this)" onchange="actualizarFiltrosErp()">
            </div>
            <div class="filter-group">
                <label>Importe máx.</label>
                <input type="number" step="0.01" min="0" id="erp_monto_max" class="filter-input" placeholder="Ej: 50000.00" oninput="validarSoloPositivo(this)" onchange="actualizarFiltrosErp()">
            </div>
            <div class="filter-group">
                <label>Cliente (Nombre o CUIT)</label>
                <input type="text" id="erp_cliente_q" class="filter-input" placeholder="Nombre, razón social o CUIT..." oninput="debouncedActualizarErp()">
            </div>
            <div class="filter-group">
                <label>N° de Cobro / ID(s)</label>
                <input type="text" id="erp_ids_cobro" class="filter-input" placeholder="Ej: 5, 89, 1234, 67" oninput="filtrarInputIdsCobro(this)">
            </div>
        </div>
    </div>

    {{-- 4. Split-Screen: Listados en Paralelo --}}
    <div class="split-screen-grid">
        {{-- Columna Izquierda: Movimientos Bancarios --}}
        <div class="table-panel">
            <div class="table-header-title">
                <span>Movimientos bancarios</span>
                <span id="banco_total_badge" style="font-size:0.85rem; color:#64748B; font-weight:normal;">0 registros</span>
            </div>
            <div class="table-responsive">
                <table class="table-erp" id="tabla_banco">
                    <colgroup>
                        <col class="col-fixed-chk" style="width: 34px;">
                        <col data-col="id" style="width: 58px;">
                        <col data-col="fecha" style="width: 92px;">
                        <col data-col="desc" style="width: auto;">
                        <col data-col="tipo" style="width: 125px;">
                        <col data-col="cuit" style="width: 105px;">
                        <col data-col="comp" style="width: 95px;">
                        <col data-col="monto" style="width: 108px;">
                        <col data-col="estado" style="width: 112px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="th-fixed">
                                <input type="checkbox" id="chk_all_banco" onclick="toggleSelectAllBanco(this)" title="Seleccionar todos los visibles">
                            </th>
                            <th class="col-header" data-col="id" data-label="ID" onclick="toggleColumna('banco', 'id', this)">
                                <div class="th-inner"><span class="th-title">#ID</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="fecha" data-label="Fecha" onclick="toggleColumna('banco', 'fecha', this)">
                                <div class="th-inner"><span class="th-title">Fecha</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="desc" data-label="Descripción" onclick="toggleColumna('banco', 'desc', this)">
                                <div class="th-inner"><span class="th-title">Descripción</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="tipo" data-label="Tipo" onclick="toggleColumna('banco', 'tipo', this)">
                                <div class="th-inner"><span class="th-title">Tipo</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="cuit" data-label="CUIT" onclick="toggleColumna('banco', 'cuit', this)">
                                <div class="th-inner"><span class="th-title">CUIT</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="comp" data-label="Comprobante" onclick="toggleColumna('banco', 'comp', this)">
                                <div class="th-inner"><span class="th-title">Comprobante</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="monto" data-label="Monto" onclick="toggleColumna('banco', 'monto', this)">
                                <div class="th-inner"><span class="th-title">Monto</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="estado" data-label="Estado" onclick="toggleColumna('banco', 'estado', this)">
                                <div class="th-inner"><span class="th-title">Estado</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tbody_banco">
                        <tr><td colspan="9" style="text-align:center; color:#94A3B8; padding:2rem;">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="table-footer-pagination" id="banco_footer_paginacion">
                <div class="pagination-spacer"></div>
                <div class="pagination-buttons" id="banco_paginacion_controles"></div>
                <label class="per-page-selector">
                    <span>Filas por pág.:</span>
                    <select id="banco_per_page" onchange="cambiarPerPageBanco(this.value)">
                        <option value="15">15</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>

        {{-- Columna Derecha: Rendiciones a conciliar --}}
        <div class="table-panel">
            <div class="table-header-title">
                <span>Rendiciones a conciliar</span>
                <span id="erp_total_badge" style="font-size:0.85rem; color:#64748B; font-weight:normal;">0 registros</span>
            </div>
            <div class="table-responsive">
                <table class="table-erp" id="tabla_erp">
                    <colgroup>
                        <col class="col-fixed-chk" style="width: 34px;">
                        <col data-col="id" style="width: 58px;">
                        <col data-col="fecha" style="width: 92px;">
                        <col data-col="cliente" style="width: auto;">
                        <col data-col="cuit" style="width: 105px;">
                        <col data-col="medio" style="width: 110px;">
                        <col data-col="monto" style="width: 108px;">
                        <col data-col="estado" style="width: 105px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="th-fixed">
                                <input type="checkbox" id="chk_all_erp" onclick="toggleSelectAllErp(this)" title="Seleccionar todos los visibles">
                            </th>
                            <th class="col-header" data-col="id" data-label="ID" onclick="toggleColumna('erp', 'id', this)">
                                <div class="th-inner"><span class="th-title">#ID</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="fecha" data-label="Fecha" onclick="toggleColumna('erp', 'fecha', this)">
                                <div class="th-inner"><span class="th-title">Fecha</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="cliente" data-label="Cliente" onclick="toggleColumna('erp', 'cliente', this)">
                                <div class="th-inner"><span class="th-title">Cliente</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="cuit" data-label="CUIT" onclick="toggleColumna('erp', 'cuit', this)">
                                <div class="th-inner"><span class="th-title">CUIT</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="medio" data-label="Medio" onclick="toggleColumna('erp', 'medio', this)">
                                <div class="th-inner"><span class="th-title">Medio</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="monto" data-label="Monto" onclick="toggleColumna('erp', 'monto', this)">
                                <div class="th-inner"><span class="th-title">Monto</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                            <th class="col-header" data-col="estado" data-label="Estado" onclick="toggleColumna('erp', 'estado', this)">
                                <div class="th-inner"><span class="th-title">Estado</span><span class="th-short"></span><span class="th-toggle-icon">◂</span></div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="tbody_erp">
                        <tr><td colspan="8" style="text-align:center; color:#94A3B8; padding:2rem;">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="table-footer-pagination" id="erp_footer_paginacion">
                <div class="pagination-spacer"></div>
                <div class="pagination-buttons" id="erp_paginacion_controles"></div>
                <label class="per-page-selector">
                    <span>Filas por pág.:</span>
                    <select id="erp_per_page" onchange="cambiarPerPageErp(this.value)">
                        <option value="15">15</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>
    </div>
    
    {{-- 5. Barra de Acciones Siempre Visible --}}
    <div id="contextual_bar" class="contextual-bar" style="display:flex; background:#F8FAFC; color:#1E293B; border:1px solid #CBD5E1; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="display:flex; align-items:center; gap:0.65rem;">
            <span style="font-size:1.1rem;">☑️</span>
            <span id="contextual_selection_text" style="font-weight:600; font-size:0.9rem; color:#334155;">0 movimientos y 0 cobranzas seleccionados</span>
        </div>
        <div style="display:flex; gap:0.75rem;">
            <button type="button" id="btn_quitar_seleccion" class="btn-action btn-secondary" onclick="quitarSeleccion()" disabled style="opacity:0.5; cursor:not-allowed;">Quitar selección</button>
            <button type="button" id="btn_organizar_seleccion" class="btn-action btn-primary" onclick="abrirModalOrganizar()">Organizar selección</button>
        </div>
    </div>

    {{-- 6. Área de Conciliación (4 pestañas) --}}
    <div class="reconciliation-area">
        <div class="reconciliation-header-bar">
            <h2 class="reconciliation-title">Área de conciliación</h2>
        </div>
        <div class="nav-tabs">
            <button type="button" class="nav-tab-btn active" id="tab_btn_manual" onclick="cambiarPestana('manual', this)">Conciliación manual</button>
            <button type="button" class="nav-tab-btn" id="tab_btn_alta_confianza" onclick="cambiarPestana('alta_confianza', this)">Coincidencias sugeridas</button>
            <button type="button" class="nav-tab-btn" id="tab_btn_para_revisar" onclick="cambiarPestana('para_revisar', this)">Coincidencias por revisar</button>
            <button type="button" class="nav-tab-btn" id="tab_btn_historial" onclick="cambiarPestana('historial', this)">Conciliaciones realizadas</button>
        </div>

        <div id="tab_content_area" style="min-height:160px; color:#475569;">
            {{-- Contenido dinámico según pestaña --}}
        </div>
    </div>
</div>

{{-- MODAL: Organizar Selección (Vinculación visual por líneas + Vista previa de grupos) --}}
<div id="modal_organizar" class="modal-overlay">
    <div class="modal-box" id="box_organizar">
        <div class="modal-header">
            <h3 style="font-weight:700; margin:0; color:#1E293B;">Organizar selección</h3>
            <div class="modal-header-actions">
                <button type="button" class="icon-btn" onclick="toggleMaximizar('box_organizar')" title="Maximizar / Restaurar">⤢</button>
                <button type="button" class="icon-btn" onclick="intentarCerrarOrganizar()" title="Cerrar">✕</button>
            </div>
        </div>

        <div class="organizar-toolbar">
            <span style="font-size:0.85rem; color:#475569;">
                Hacé clic en un registro de la izquierda y luego en uno de la derecha para conectarlos.
            </span>
            <div class="organizar-history-btns">
                <button type="button" id="btn_org_undo" class="btn-history-icon" onclick="deshacerConexion()" title="Deshacer última acción" disabled>↶</button>
                <button type="button" id="btn_org_redo" class="btn-history-icon" onclick="rehacerConexion()" title="Rehacer acción" disabled>↷</button>
                <button type="button" class="btn-action btn-secondary" style="padding:0.35rem 0.7rem; font-size:0.8rem;" onclick="limpiarConexiones()">Limpiar conexiones</button>
            </div>
        </div>

        {{-- Títulos fijos arriba y centrados en cada columna --}}
        <div class="organizar-headers-row">
            <div class="organizar-col-header">Movimientos bancarios</div>
            <div></div>
            <div class="organizar-col-header">Rendiciones a conciliar</div>
        </div>

        {{-- Área fluida sin scroll interno --}}
        <div class="organizar-scroll-viewport" id="organizar_scroll_viewport">
            <div class="organizar-container" id="organizar_container">
                <div class="organizar-col" id="org_col_banco"></div>
                <div class="connections-canvas-col">
                    <svg id="connectionsSvg"></svg>
                </div>
                <div class="organizar-col" id="org_col_erp"></div>
            </div>
        </div>

        {{-- Sección de Vista Previa (Grupos Vinculados + Registros Aislados con Checkbox) --}}
        <div class="organizar-preview-section">
            {{-- Parte 1: Grupos Vinculados --}}
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.55rem;">
                <span style="font-size:0.86rem; font-weight:700; color:#1E293B;">
                    Grupos vinculados a crear (<span id="org_preview_count">0</span>)
                </span>
                <span style="font-size:0.77rem; color:#64748B;">
                    Podés personalizar el nombre de cada grupo
                </span>
            </div>
            <div class="organizar-preview-grid" id="org_preview_grupos"></div>

            {{-- Parte 2: Registros Aislados con Checkbox --}}
            <div style="margin-top: 1.25rem; padding-top: 0.85rem; border-top: 1px dashed #CBD5E1;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.55rem;">
                    <span style="font-size:0.86rem; font-weight:700; color:#1E293B;">
                        Registros aislados disponibles (<span id="org_aislados_count">0</span>)
                    </span>
                    <span style="font-size:0.77rem; color:#64748B;">
                        Marcá el check en los registros que quieras agregar como grupos individuales
                    </span>
                </div>
                <div class="organizar-preview-grid" id="org_preview_aislados"></div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; align-items:center; gap:0.6rem; margin-top:1.25rem; padding-top:0.75rem; border-top:1px solid #E2E8F0;">
            <button type="button" class="btn-action btn-secondary" onclick="cancelarOrganizar()">Cancelar</button>
            <button type="button" class="btn-action btn-primary" onclick="guardarDistribucionGrupos()">Guardar distribución</button>
        </div>
    </div>
</div>

{{-- Modal de Importación de Extractos Bancarios (Redimensionable y Maximizable) --}}
    <div id="modal_extracto" class="modal-overlay">
        <div id="modal_extracto_box" class="modal-box" style="width: 600px; min-width: 440px; max-width: 96vw; min-height: 360px; max-height: 92vh; resize: both; overflow: auto; display: flex; flex-direction: column;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
                <h3 style="margin:0; font-size:1.1rem; color:#1E293B;">📥 Importar extractos bancarios</h3>
                <div style="display:flex; align-items:center; gap:0.35rem;">
                    <button type="button" onclick="toggleMaximizar('modal_extracto_box')" title="Maximizar / Restaurar tamaño" style="background:#F1F5F9; border:1px solid #CBD5E1; border-radius:6px; padding:2px 8px; font-size:0.85rem; cursor:pointer; color:#334155;">⛶</button>
                    <button type="button" onclick="intentarCerrarModalExtracto()" title="Cerrar" style="background:none; border:none; font-size:1.25rem; cursor:pointer; color:#64748B;">✕</button>
                </div>
            </div>
            <p style="margin:0 0 0.85rem 0; font-size:0.83rem; color:#64748B;">
                Arrastrá uno o varios extractos bancarios acá o seleccionalos desde tu computadora (<code>.csv</code>, <code>.txt</code>, <code>.xls</code>, <code>.xlsx</code>).
            </p>

            <form id="form_importar_extracto" onsubmit="enviarExtractosMultiples(event)" style="display:flex; flex-direction:column; flex:1; min-height:0;">
                {{-- Zona Drag & Drop --}}
                <div id="dropzone_extracto"
                     onclick="document.getElementById('input_archivo_extracto').click()"
                     style="border: 2px dashed #93C5FD; background: #F8FAFC; border-radius: 10px; padding: 1.35rem 1rem; text-align: center; cursor: pointer; transition: all 0.15s;">
                    <div style="font-size: 1.6rem; margin-bottom: 0.35rem;">📄</div>
                    <div style="font-weight: 600; color: #1E293B; font-size: 0.9rem;">
                        Soltá los archivos aquí o <span style="color: #2563EB; text-decoration: underline;">buscá en tu computadora</span>
                    </div>
                    <div style="font-size: 0.76rem; color: #64748B; margin-top: 0.25rem;">
                        Podés cargar múltiples archivos juntos antes de subirlos
                    </div>
                    <input type="file" id="input_archivo_extracto" multiple accept=".csv,.txt,.xls,.xlsx" style="display:none;" onchange="archivosSeleccionados(this)">
                </div>

                {{-- Lista de archivos preparados --}}
                <div id="lista_archivos_container" style="display:none; margin-top: 0.85rem; border: 1px solid #E2E8F0; border-radius: 8px; background: #FFFFFF; padding: 0.65rem 0.75rem; flex: 1; min-height: 110px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.45rem; padding-bottom:0.35rem; border-bottom:1px solid #F1F5F9;">
                        <span id="contador_archivos_espera" style="font-size:0.82rem; font-weight:600; color:#334155;">Archivos listos para subir</span>
                        <button type="button" class="btn-action btn-secondary" style="padding: 0.28rem 0.65rem; font-size: 0.78rem;" onclick="limpiarListaArchivos()">Limpiar lista</button>
                    </div>
                    <ul id="lista_archivos_ul" style="list-style:none; margin:0; padding:0; max-height: 220px; overflow-y: auto; font-size: 0.82rem; color: #334155;"></ul>
                </div>

                {{-- Estado de carga y resultados --}}
                <div id="import_spinner" style="display:none; margin-top:0.75rem; padding:0.65rem; background:#EFF6FF; color:#1D4ED8; border-radius:8px; font-size:0.84rem; text-align:center;">
                    Procesando extractos, aguarde un momento...
                </div>
                <div id="import_resultado" style="display:none; margin-top:0.75rem; flex: 1; min-height: 140px; overflow-y: auto; padding-right: 2px;"></div>

                {{-- Pie fijo siempre abajo --}}
                <div style="display:flex; justify-content:flex-end; align-items:center; gap:0.5rem; margin-top: auto; padding-top: 0.85rem; border-top: 1px solid #E2E8F0;">
                    <button type="button" class="btn-action btn-secondary" onclick="intentarCerrarModalExtracto()">Cancelar</button>
                    <button type="submit" id="btn_submit_extracto" class="btn-action btn-primary" disabled>Subir extractos</button>
                </div>
            </form>
        </div>
    </div>

{{-- Modal Propio de Confirmación (reemplaza confirm/prompt nativos del navegador) --}}
    <div id="modal_confirmacion_sistema" class="modal-overlay" style="z-index: 9999;">
        <div class="modal-box" style="max-width: 440px; text-align: left;">
            <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.5rem;">
                <span id="confirm_sys_icon" style="font-size:1.35rem;">⚠️</span>
                <h3 id="confirm_sys_titulo" style="margin:0; font-size:1.05rem; color:#1E293B;">Confirmar acción</h3>
            </div>
            <p id="confirm_sys_mensaje" style="margin:0 0 1.15rem 0; font-size:0.86rem; color:#475569; line-height:1.45;"></p>
            <div id="confirm_sys_botones" style="display:flex; justify-content:flex-end; flex-wrap:wrap; gap:0.5rem;"></div>
        </div>
    </div>

{{-- Modal de Configuración de Apariencia y Colores por Estado --}}
    <div id="modal_config" class="modal-overlay" onclick="if(event.target === this) intentarCerrarConfiguracion()">
        <div class="modal-box" style="max-width: 540px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
                <h3 style="margin:0; font-size:1.1rem; color:#1E293B;">🎨 Colores de fondo por estado</h3>
                <button type="button" onclick="intentarCerrarConfiguracion()" style="background:none; border:none; font-size:1.25rem; cursor:pointer; color:#64748B;">✕</button>
            </div>
            <p style="margin:0 0 0.6rem 0; font-size:0.82rem; color:#64748B;">
                Elegí un tono pastel suave para resaltar visualmente las filas en cada listado.
            </p>

            <div class="config-color-section-title">Movimientos bancarios</div>
            <div id="lista_config_colores_banco" class="config-color-list"></div>

            <div class="config-color-section-title">Rendiciones a conciliar</div>
            <div id="lista_config_colores_erp" class="config-color-list"></div>

            <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem; margin-top:1.1rem;">
                <button type="button" class="btn-action btn-secondary" onclick="restaurarTodosPredeterminados()">Restaurar predeterminados</button>
                <div style="display:flex; gap:0.5rem;">
                    <button type="button" class="btn-action btn-secondary" onclick="cancelarConfiguracion()">Cancelar</button>
                    <button type="button" class="btn-action btn-primary" onclick="guardarConfiguracion()">Guardar cambios</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
/**
 * ESTADO DE PANTALLA PERSISTENTE EN SESIÓN (SESSIONSTORAGE)
 */
const STORAGE_KEY = 'erp_conciliacion_state';

const CONFIG_ESTADOS = ['pendiente', 'matcheado', 'en_disputa', 'revision_manual', 'no_aplica', 'cerrado_definitivo'];

const DEFAULT_COLORES = {
    pendiente: '#FFFFFF',
    matcheado: '#DCFCE7',
    en_disputa: '#FFFFFF',
    revision_manual: '#FFFFFF',
    no_aplica: '#FFFFFF',
    cerrado_definitivo: '#FFFFFF'
};

const ETIQUETAS_ESTADO = {
    pendiente: 'Pendiente',
    revision_manual: 'Revisión manual',
    en_disputa: 'En disputa',
    matcheado: 'Matcheado',
    no_aplica: 'No aplica',
    cerrado_definitivo: 'Cerrado definitivo'
};

const ETIQUETAS_TIPO = {
    cobranza_cliente: 'Cobranza cliente',
    transferencia_propia: 'Transferencia propia',
    gasto_bancario: 'Gasto bancario',
    impuesto_bancario: 'Impuesto bancario',
    debito_rechazo: 'Débito / Rechazo',
    pago_proveedor: 'Pago a proveedor',
    pago_sueldos: 'Pago de sueldos',
    pago_servicios: 'Pago de servicios',
    desconocido: 'Desconocido',
    otro: 'Otro'
};

const TODOS_ESTADOS_BANCO = ['pendiente', 'revision_manual', 'en_disputa', 'matcheado', 'no_aplica', 'cerrado_definitivo'];
const TODOS_TIPOS_BANCO = [
    'cobranza_cliente', 'transferencia_propia', 'gasto_bancario', 'impuesto_bancario',
    'debito_rechazo', 'pago_proveedor', 'pago_sueldos', 'pago_servicios', 'desconocido', 'otro'
];

const ETIQUETAS_ESTADO_RENDICION = {
    aprobada: 'Aprobada',
    pendiente: 'Pendiente',
    rechazada: 'Rechazada'
};

const ETIQUETAS_MEDIO_ERP = {
    transferencia: 'Transferencia',
    cheque: 'Cheque'
};

const TODOS_ESTADOS_ERP = ['aprobada', 'pendiente', 'rechazada'];
const TODOS_MEDIOS_ERP = ['transferencia', 'cheque'];

let state = {
    banco: {
        fecha_desde: '',
        fecha_hasta: '',
        estados: ['pendiente'],
        tipos: [...TODOS_TIPOS_BANCO],
        monto_min: '',
        monto_max: '',
        signo: '',
        cuit: '',
        comprobante: '',
        q: '',
        ids_movimiento: '',
        page: 1,
        per_page: 25,
        colsContraidas: new Set()
    },
    erp: {
        fecha_desde: '',
        fecha_hasta: '',
        estados: ['aprobada'],
        medios: [...TODOS_MEDIOS_ERP],
        monto_min: '',
        monto_max: '',
        cliente_q: '',
        ids_cobro: '',
        page: 1,
        per_page: 25,
        colsContraidas: new Set()
    },
    seleccionBanco: new Set(),
    seleccionErp: new Set(),
    gruposManuales: [],
    conexionesTemp: [],
    pestanaActiva: 'manual',
    configModificada: false,
    colores: { ...DEFAULT_COLORES }
};

let archivosEnEspera = [];
let reqIdBanco = 0;
let reqIdErp = 0;
let cacheBancoItems = {};
let cacheErpItems = {};
let historialConexiones = [[]];
let indiceHistorialConexiones = 0;
let nombresPreviewGrupos = {};
let connectingBancoId = null;
let activeRecord = { tipo: null, id: null }; // Registro actualmente activo
let gruposIndividualesConfirmados = new Set(); // Almacena IDs de registros confirmados como aislados ("banco:43" o "erp:2")
let aisladosChecks = {};

/**
 * MODAL PROPIO DE CONFIRMACIÓN (SIN ALERT/CONFIRM/PROMPT DEL NAVEGADOR)
 */
function mostrarConfirmacionSistema({ icono = '⚠️', titulo, mensaje, botones = [] }) {
    const overlay = document.getElementById('modal_confirmacion_sistema');
    document.getElementById('confirm_sys_icon').textContent = icono;
    document.getElementById('confirm_sys_titulo').textContent = titulo;
    document.getElementById('confirm_sys_mensaje').innerHTML = mensaje;

    const contBotones = document.getElementById('confirm_sys_botones');
    contBotones.innerHTML = '';

    botones.forEach(btn => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = `btn-action ${btn.clase || 'btn-secondary'}`;
        b.textContent = btn.texto;
        b.onclick = () => {
            overlay.classList.remove('active');
            if (typeof btn.onClick === 'function') btn.onClick();
        };
        contBotones.appendChild(b);
    });

    overlay.classList.add('active');
}

function guardarEstadoEnSession() {
    const backup = {
        ...state,
        banco: { ...state.banco, colsContraidas: Array.from(state.banco.colsContraidas) },
        erp: { ...state.erp, colsContraidas: Array.from(state.erp.colsContraidas) },
        seleccionBanco: Array.from(state.seleccionBanco),
        seleccionErp: Array.from(state.seleccionErp)
    };
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(backup));
}

function recuperarEstadoSession() {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return;
    try {
        const parsed = JSON.parse(raw);

        // Recuperación Banco
        let estadosRecuperados = ['pendiente'];
        if (Array.isArray(parsed.banco?.estados)) {
            estadosRecuperados = parsed.banco.estados;
        } else if (typeof parsed.banco?.estado === 'string') {
            estadosRecuperados = parsed.banco.estado ? [parsed.banco.estado] : [];
        }

        let tiposRecuperados = [...TODOS_TIPOS_BANCO];
        if (Array.isArray(parsed.banco?.tipos)) {
            tiposRecuperados = parsed.banco.tipos;
        } else if (typeof parsed.banco?.tipo === 'string' && parsed.banco.tipo) {
            tiposRecuperados = [parsed.banco.tipo];
        }

        // Recuperación Rendiciones (ERP)
        let estadosErpRec = ['aprobada'];
        if (Array.isArray(parsed.erp?.estados)) {
            estadosErpRec = parsed.erp.estados;
        } else if (typeof parsed.erp?.estado === 'string') {
            const normEst = parsed.erp.estado === 'aprobado' ? 'aprobada' : parsed.erp.estado;
            estadosErpRec = normEst ? [normEst] : [];
        }

        let mediosErpRec = [...TODOS_MEDIOS_ERP];
        if (Array.isArray(parsed.erp?.medios)) {
            mediosErpRec = parsed.erp.medios;
        } else if (typeof parsed.erp?.medio === 'string' && parsed.erp.medio) {
            mediosErpRec = [parsed.erp.medio];
        }

        state = {
            ...state,
            ...parsed,
            banco: {
                ...state.banco,
                ...(parsed.banco || {}),
                estados: estadosRecuperados,
                tipos: tiposRecuperados,
                colsContraidas: new Set(parsed.banco?.colsContraidas || [])
            },
            erp: {
                ...state.erp,
                ...(parsed.erp || {}),
                estados: estadosErpRec,
                medios: mediosErpRec,
                colsContraidas: new Set(parsed.erp?.colsContraidas || [])
            },
            seleccionBanco: new Set((parsed.seleccionBanco || []).map(Number)),
            seleccionErp: new Set((parsed.seleccionErp || []).map(Number)),
            colores: { ...DEFAULT_COLORES, ...(parsed.colores || {}) }
        };
    } catch(e) { console.error("Error restaurando estado:", e); }
}

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

const debouncedActualizarBanco = debounce(() => actualizarFiltrosBanco(), 350);
const debouncedActualizarErp = debounce(() => actualizarFiltrosErp(), 350);

/**
 * CONTROLES MULTI-SELECT (BANCO Y RENDICIONES)
 */
function toggleMultiselect(wrapperId, event) {
    event.stopPropagation();
    const wrapper = document.getElementById(wrapperId);
    const menu = wrapper.querySelector('.multiselect-menu');
    const trigger = wrapper.querySelector('.multiselect-trigger');
    const estabaAbierto = menu.classList.contains('open');

    cerrarTodosMultiselect();

    if (!estabaAbierto) {
        menu.classList.add('open');
        trigger.classList.add('active');
    }
}

function cerrarTodosMultiselect() {
    document.querySelectorAll('.multiselect-menu.open').forEach(m => m.classList.remove('open'));
    document.querySelectorAll('.multiselect-trigger.active').forEach(t => t.classList.remove('active'));
}

document.addEventListener('click', () => cerrarTodosMultiselect());

function seleccionarTodosMultiselect(grupo, marcar) {
    const selector = grupo === 'estado' ? '.chk-banco-estado' : '.chk-banco-tipo';
    document.querySelectorAll(selector).forEach(chk => { chk.checked = marcar; });
    actualizarFiltrosBanco();
}

function restablecerPredeterminadoMultiselect(grupo) {
    if (grupo === 'estado') {
        document.querySelectorAll('.chk-banco-estado').forEach(chk => {
            chk.checked = (chk.value === 'pendiente');
        });
    } else if (grupo === 'tipo') {
        document.querySelectorAll('.chk-banco-tipo').forEach(chk => {
            chk.checked = true;
        });
    }
    actualizarFiltrosBanco();
}

function actualizarEtiquetasMultiselect() {
    const lblEstado = document.getElementById('ms_banco_estado_label');
    if (state.banco.estados.length === 0) {
        lblEstado.innerText = 'Ninguno';
    } else if (state.banco.estados.length === TODOS_ESTADOS_BANCO.length) {
        lblEstado.innerText = 'Todos';
    } else if (state.banco.estados.length <= 2) {
        lblEstado.innerText = state.banco.estados.map(e => ETIQUETAS_ESTADO[e] || e).join(', ');
    } else {
        lblEstado.innerText = `${state.banco.estados.length} estados seleccionados`;
    }

    const lblTipo = document.getElementById('ms_banco_tipo_label');
    if (state.banco.tipos.length === 0) {
        lblTipo.innerText = 'Ninguno';
    } else if (state.banco.tipos.length === TODOS_TIPOS_BANCO.length) {
        lblTipo.innerText = 'Todos';
    } else if (state.banco.tipos.length <= 2) {
        lblTipo.innerText = state.banco.tipos.map(t => ETIQUETAS_TIPO[t] || t).join(', ');
    } else {
        lblTipo.innerText = `${state.banco.tipos.length} tipos seleccionados`;
    }
}

function seleccionarTodosMultiselectErp(grupo, marcar) {
    const selector = grupo === 'estado' ? '.chk-erp-estado' : '.chk-erp-medio';
    document.querySelectorAll(selector).forEach(chk => { chk.checked = marcar; });
    actualizarFiltrosErp();
}

function restablecerPredeterminadoMultiselectErp(grupo) {
    if (grupo === 'estado') {
        document.querySelectorAll('.chk-erp-estado').forEach(chk => {
            chk.checked = (chk.value === 'aprobada');
        });
    } else if (grupo === 'medio') {
        document.querySelectorAll('.chk-erp-medio').forEach(chk => {
            chk.checked = true;
        });
    }
    actualizarFiltrosErp();
}

function actualizarEtiquetasMultiselectErp() {
    const lblEstado = document.getElementById('ms_erp_estado_label');
    if (state.erp.estados.length === 0) {
        lblEstado.innerText = 'Ninguno';
    } else if (state.erp.estados.length === TODOS_ESTADOS_ERP.length) {
        lblEstado.innerText = 'Todos';
    } else if (state.erp.estados.length <= 2) {
        lblEstado.innerText = state.erp.estados.map(e => ETIQUETAS_ESTADO_RENDICION[e] || e).join(', ');
    } else {
        lblEstado.innerText = `${state.erp.estados.length} estados seleccionados`;
    }

    const lblMedio = document.getElementById('ms_erp_medio_label');
    if (state.erp.medios.length === 0) {
        lblMedio.innerText = 'Ninguno';
    } else if (state.erp.medios.length === TODOS_MEDIOS_ERP.length) {
        lblMedio.innerText = 'Todos';
    } else {
        lblMedio.innerText = state.erp.medios.map(m => ETIQUETAS_MEDIO_ERP[m] || m).join(', ');
    }
}

/**
 * TOGGLE SENTIDO BANCO, VALIDACIONES DE INPUTS Y CHIPS
 */
function toggleSignoBanco(valor) {
    state.banco.signo = (state.banco.signo === valor) ? '' : valor;
    sincronizarBotonesSigno();
    state.banco.page = 1;
    actualizarChips();
    guardarEstadoEnSession();
    cargarMovimientosBancarios();
}

function sincronizarBotonesSigno() {
    const btnIng = document.getElementById('btn_signo_ingreso');
    const btnEgr = document.getElementById('btn_signo_egreso');
    btnIng.classList.toggle('active-ingreso', state.banco.signo === 'ingreso');
    btnEgr.classList.toggle('active-egreso', state.banco.signo === 'egreso');
}

function filtrarInputCuit(input) {
    input.value = input.value.replace(/\D/g, '').slice(0, 11);
    debouncedActualizarBanco();
}

function filtrarInputIdsBanco(input) {
    input.value = input.value.replace(/[^0-9,\s]/g, '');
    debouncedActualizarBanco();
}

function validarSoloPositivo(input) {
    if (input.value !== '' && parseFloat(input.value) < 0) {
        input.value = Math.abs(parseFloat(input.value));
    }
}

function filtrarInputIdsCobro(input) {
    input.value = input.value.replace(/[^0-9,\s]/g, '');
    debouncedActualizarErp();
}

function quitarFiltroBanco(campo) {
    if (campo === 'signo') {
        state.banco.signo = '';
        sincronizarBotonesSigno();
    } else {
        const mapaInputs = {
            fecha_desde: 'banco_fecha_desde',
            fecha_hasta: 'banco_fecha_hasta',
            monto_min: 'banco_monto_min',
            monto_max: 'banco_monto_max',
            cuit: 'banco_cuit',
            comprobante: 'banco_comprobante',
            q: 'banco_q',
            ids_movimiento: 'banco_ids_movimiento'
        };
        const el = document.getElementById(mapaInputs[campo]);
        if (el) el.value = '';
    }
    actualizarFiltrosBanco();
}

function quitarFiltroErp(campo) {
    const mapaInputs = {
        fecha_desde: 'erp_fecha_desde',
        fecha_hasta: 'erp_fecha_hasta',
        monto_min: 'erp_monto_min',
        monto_max: 'erp_monto_max',
        cliente_q: 'erp_cliente_q',
        ids_cobro: 'erp_ids_cobro'
    };
    const el = document.getElementById(mapaInputs[campo]);
    if (el) el.value = '';
    actualizarFiltrosErp();
}

function actualizarChips() {
    const containerBanco = document.getElementById('chips_banco');
    containerBanco.innerHTML = '';

    const addChipBanco = (campo, etiqueta) => {
        containerBanco.innerHTML += `
            <span class="filter-chip">
                ${etiqueta}
                <button type="button" class="filter-chip-close" onclick="quitarFiltroBanco('${campo}')" title="Quitar filtro">&times;</button>
            </span>`;
    };

    if (state.banco.fecha_desde) addChipBanco('fecha_desde', `Desde: ${state.banco.fecha_desde}`);
    if (state.banco.fecha_hasta) addChipBanco('fecha_hasta', `Hasta: ${state.banco.fecha_hasta}`);
    if (state.banco.monto_min !== '') addChipBanco('monto_min', `Mín: $${state.banco.monto_min}`);
    if (state.banco.monto_max !== '') addChipBanco('monto_max', `Máx: $${state.banco.monto_max}`);
    if (state.banco.signo === 'ingreso') addChipBanco('signo', 'Sentido: + Solo ingresos');
    if (state.banco.signo === 'egreso') addChipBanco('signo', 'Sentido: - Solo egresos');
    if (state.banco.cuit) addChipBanco('cuit', `CUIT: ${state.banco.cuit}`);
    if (state.banco.comprobante) addChipBanco('comprobante', `Comp: ${state.banco.comprobante}`);
    if (state.banco.q) addChipBanco('q', `Texto: ${state.banco.q}`);
    if (state.banco.ids_movimiento) addChipBanco('ids_movimiento', `IDs: ${state.banco.ids_movimiento}`);

    const containerErp = document.getElementById('chips_erp');
    containerErp.innerHTML = '';

    const addChipErp = (campo, etiqueta) => {
        containerErp.innerHTML += `
            <span class="filter-chip">
                ${etiqueta}
                <button type="button" class="filter-chip-close" onclick="quitarFiltroErp('${campo}')" title="Quitar filtro">&times;</button>
            </span>`;
    };

    if (state.erp.fecha_desde) addChipErp('fecha_desde', `Desde: ${state.erp.fecha_desde}`);
    if (state.erp.fecha_hasta) addChipErp('fecha_hasta', `Hasta: ${state.erp.fecha_hasta}`);
    if (state.erp.monto_min !== '') addChipErp('monto_min', `Mín: $${state.erp.monto_min}`);
    if (state.erp.monto_max !== '') addChipErp('monto_max', `Máx: $${state.erp.monto_max}`);
    if (state.erp.cliente_q) addChipErp('cliente_q', `Cliente: ${state.erp.cliente_q}`);
    if (state.erp.ids_cobro) addChipErp('ids_cobro', `IDs: ${state.erp.ids_cobro}`);
}

function toggleFiltrosAvanzados(id, btn) {
    const el = document.getElementById(id);
    const expanded = el.classList.toggle('expanded');
    btn.innerHTML = expanded ? '▲ Menos filtros' : '▼ Filtros avanzados';
}

function actualizarFiltrosBanco() {
    state.banco.fecha_desde = document.getElementById('banco_fecha_desde').value;
    state.banco.fecha_hasta = document.getElementById('banco_fecha_hasta').value;
    state.banco.estados = Array.from(document.querySelectorAll('.chk-banco-estado:checked')).map(c => c.value);
    state.banco.tipos = Array.from(document.querySelectorAll('.chk-banco-tipo:checked')).map(c => c.value);
    state.banco.monto_min = document.getElementById('banco_monto_min').value;
    state.banco.monto_max = document.getElementById('banco_monto_max').value;
    state.banco.cuit = document.getElementById('banco_cuit').value;
    state.banco.comprobante = document.getElementById('banco_comprobante').value;
    state.banco.q = document.getElementById('banco_q').value;
    state.banco.page = 1;
    state.banco.ids_movimiento = document.getElementById('banco_ids_movimiento').value;

    actualizarEtiquetasMultiselect();
    actualizarChips();
    guardarEstadoEnSession();
    cargarMovimientosBancarios();
}

function actualizarFiltrosErp() {
    state.erp.fecha_desde = document.getElementById('erp_fecha_desde').value;
    state.erp.fecha_hasta = document.getElementById('erp_fecha_hasta').value;
    state.erp.estados = Array.from(document.querySelectorAll('.chk-erp-estado:checked')).map(c => c.value);
    state.erp.medios = Array.from(document.querySelectorAll('.chk-erp-medio:checked')).map(c => c.value);
    state.erp.monto_min = document.getElementById('erp_monto_min').value;
    state.erp.monto_max = document.getElementById('erp_monto_max').value;
    state.erp.cliente_q = document.getElementById('erp_cliente_q').value;
    state.erp.ids_cobro = document.getElementById('erp_ids_cobro').value;
    state.erp.page = 1;

    actualizarEtiquetasMultiselectErp();
    actualizarChips();
    guardarEstadoEnSession();
    cargarCobranzasErp();
}

function limpiarFiltrosBancarios() {
    document.getElementById('banco_fecha_desde').value = '';
    document.getElementById('banco_fecha_hasta').value = '';
    document.querySelectorAll('.chk-banco-estado').forEach(chk => { chk.checked = (chk.value === 'pendiente'); });
    document.querySelectorAll('.chk-banco-tipo').forEach(chk => { chk.checked = true; });
    document.getElementById('banco_monto_min').value = '';
    document.getElementById('banco_monto_max').value = '';
    state.banco.signo = '';
    sincronizarBotonesSigno();
    document.getElementById('banco_cuit').value = '';
    document.getElementById('banco_comprobante').value = '';
    document.getElementById('banco_q').value = '';
    document.getElementById('banco_ids_movimiento').value = '';
    actualizarFiltrosBanco();
}

function limpiarFiltrosCobranzas() {
    document.getElementById('erp_fecha_desde').value = '';
    document.getElementById('erp_fecha_hasta').value = '';
    document.querySelectorAll('.chk-erp-estado').forEach(chk => { chk.checked = (chk.value === 'aprobada'); });
    document.querySelectorAll('.chk-erp-medio').forEach(chk => { chk.checked = true; });
    document.getElementById('erp_monto_min').value = '';
    document.getElementById('erp_monto_max').value = '';
    document.getElementById('erp_cliente_q').value = '';
    document.getElementById('erp_ids_cobro').value = '';
    actualizarFiltrosErp();
}

/**
 * LISTADOS Y CARGA CON RESPETO A CONCURRENCIA
 */
function expandirAbreviaturasDescripcion(texto) {
    if (!texto) return '-';
    const reglas = [
        [/\bTRANSF\.?\b/gi, 'TRANSFERENCIA'],
        [/\bTRF\.?\b/gi, 'TRANSFERENCIA'],
        [/\bDEB\.?\b/gi, 'DEBITO'],
        [/\bCRED\.?\b/gi, 'CREDITO'],
        [/\bIMP\.?\b/gi, 'IMPUESTO'],
        [/\bCOM\.?\b/gi, 'COMISION'],
        [/\bCHQ\.?\b/gi, 'CHEQUE'],
        [/\bECHEQ\.?\b/gi, 'E-CHEQ'],
        [/\bDEP\.?\b/gi, 'DEPOSITO'],
        [/\bSERV\.?\b/gi, 'SERVICIO'],
        [/\bAUT\.?\b/gi, 'AUTOMATICO'],
        [/\bMANT\.?\b/gi, 'MANTENIMIENTO'],
        [/\bCTA\.?\b/gi, 'CUENTA'],
        [/\bCTE\.?\b/gi, 'CORRIENTE'],
        [/\bBCO\.?\b/gi, 'BANCO'],
        [/\bINTERBANC\.?\b/gi, 'INTERBANCARIA'],
        [/\bACRED\.?\b/gi, 'ACREDITACION'],
        [/\bLIQ\.?\b/gi, 'LIQUIDACION'],
        [/\bRET\.?\b/gi, 'RETENCION'],
        [/\bPERC\.?\b/gi, 'PERCEPCION'],
        [/\bING\.?\s*BRUTOS\b/gi, 'INGRESOS BRUTOS'],
        [/\bVAR\.?\b/gi, 'VARIOS'],
        [/\bDB\.?\s*DEBIN\b/gi, 'DEBITO DEBIN'],
        [/\bCR\.?\s*DEBIN\b/gi, 'CREDITO DEBIN'],
        [/\bCDNI\b/gi, 'CUENTA DNI'],
        [/\bII\.?\s*BB\.?\b/gi, 'INGRESOS BRUTOS']
    ];
    let resultado = texto;
    for (const [regex, reemplazo] of reglas) {
        resultado = resultado.replace(regex, reemplazo);
    }
    return resultado;
}

function formatearFechaAr(fechaRaw) {
    if (!fechaRaw) return '-';
    const soloFecha = String(fechaRaw).substring(0, 10);
    const partes = soloFecha.split('-');
    if (partes.length === 3) {
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }
    return soloFecha;
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

async function cargarMovimientosBancarios() {
    const currentReq = ++reqIdBanco;
    const params = new URLSearchParams();
    if (state.banco.fecha_desde) params.append('fecha_desde', state.banco.fecha_desde);
    if (state.banco.fecha_hasta) params.append('fecha_hasta', state.banco.fecha_hasta);

    params.append('estado_conciliacion', state.banco.estados.length > 0 ? state.banco.estados.join(',') : '__ninguno__');
    params.append('tipo_movimiento', state.banco.tipos.length > 0 ? state.banco.tipos.join(',') : '__ninguno__');

    if (state.banco.monto_min !== '') params.append('monto_min', state.banco.monto_min);
    if (state.banco.monto_max !== '') params.append('monto_max', state.banco.monto_max);
    if (state.banco.signo) params.append('signo', state.banco.signo);
    if (state.banco.cuit) params.append('cuit', state.banco.cuit);
    if (state.banco.comprobante) params.append('comprobante', state.banco.comprobante);
    if (state.banco.q) params.append('q', state.banco.q);
    if (state.banco.ids_movimiento) params.append('ids_movimiento', state.banco.ids_movimiento);
    params.append('page', state.banco.page);
    params.append('per_page', state.banco.per_page);

    try {
        const res = await fetch('/api/movimientos-bancarios?' + params.toString());
        const json = await res.json();
        if (currentReq !== reqIdBanco) return;

        const tbody = document.getElementById('tbody_banco');
        tbody.innerHTML = '';

        if (!json.data || json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; color:#94A3B8; padding:2rem;">No se encontraron movimientos bancarios con la configuración de filtros actual.</td></tr>';
            document.getElementById('banco_total_badge').innerText = '0 registros';
            actualizarFooterPaginacion('banco', 0, 1, state.banco.per_page, 0);
            sincronizarCheckboxMaestro('banco');
            return;
        }

        document.getElementById('banco_total_badge').innerText = `${json.total} registros`;
        actualizarFooterPaginacion('banco', json.last_page, json.current_page, json.per_page, json.total);

        json.data.forEach(m => {
            cacheBancoItems[m.id_movimiento_bancario] = m; // <-- Caché poblada
            const isSelected = state.seleccionBanco.has(m.id_movimiento_bancario);
            const tr = document.createElement('tr');
            tr.className = `row-state-${m.estado_conciliacion}`;
            if (isSelected) tr.classList.add('selected-row');

            const fechaAr = formatearFechaAr(m.fecha_operacion);
            const descExpandida = expandirAbreviaturasDescripcion(m.descripcion_normalizada || m.descripcion_original);
            const descOriginal = escapeHtml(m.descripcion_original || descExpandida);
            const tipoLegible = ETIQUETAS_TIPO[m.tipo_movimiento] || m.tipo_movimiento || '-';
            const estadoLegible = ETIQUETAS_ESTADO[m.estado_conciliacion] || m.estado_conciliacion;
            const cuitTxt = m.cuit_extraido || '-';
            const compTxt = m.nro_comprobante_extraido || '-';
            const montoFormateado = `${m.signo_monto === 'egreso' ? '-' : ''}$${parseFloat(m.monto).toLocaleString('es-AR', {minimumFractionDigits: 2})}`;

            tr.innerHTML = `
                <td class="td-fixed-chk"><input type="checkbox" onchange="toggleSelectBanco(${m.id_movimiento_bancario}, this)" ${isSelected ? 'checked' : ''} title="Seleccionar para organizar conciliación manual"></td>
                <td data-col="id" title="#${m.id_movimiento_bancario}">#${m.id_movimiento_bancario}</td>
                <td data-col="fecha" title="${fechaAr}">${fechaAr}</td>
                <td data-col="desc" title="${descOriginal}">${escapeHtml(descExpandida)}</td>
                <td data-col="tipo" title="${escapeHtml(tipoLegible)}">${escapeHtml(tipoLegible)}</td>
                <td data-col="cuit" title="${escapeHtml(cuitTxt)}">${escapeHtml(cuitTxt)}</td>
                <td data-col="comp" title="${escapeHtml(compTxt)}">${escapeHtml(compTxt)}</td>
                <td data-col="monto" title="${montoFormateado}" style="font-weight:600; text-align:right; color:#1E293B;">${montoFormateado}</td>
                <td data-col="estado" title="${escapeHtml(estadoLegible)}"><span class="badge-status badge-${m.estado_conciliacion}">${escapeHtml(estadoLegible)}</span></td>
            `;
            tbody.appendChild(tr);
        });

        aplicarColumnasContraidas('banco');
        sincronizarCheckboxMaestro('banco');
    } catch(e) { console.error(e); }
}

async function cargarCobranzasErp() {
    const currentReq = ++reqIdErp;
    const params = new URLSearchParams();
    if (state.erp.fecha_desde) params.append('fecha_desde', state.erp.fecha_desde);
    if (state.erp.fecha_hasta) params.append('fecha_hasta', state.erp.fecha_hasta);

    params.append('estado', state.erp.estados.length > 0 ? state.erp.estados.join(',') : '__ninguno__');
    params.append('medio_pago', state.erp.medios.length > 0 ? state.erp.medios.join(',') : '__ninguno__');

    if (state.erp.monto_min !== '') params.append('monto_min', state.erp.monto_min);
    if (state.erp.monto_max !== '') params.append('monto_max', state.erp.monto_max);
    if (state.erp.cliente_q) params.append('cliente_q', state.erp.cliente_q);
    if (state.erp.ids_cobro) params.append('ids_cobro', state.erp.ids_cobro);
    params.append('page', state.erp.page);
    params.append('per_page', state.erp.per_page);

    try {
        const res = await fetch('/api/cobranzas-erp?' + params.toString());
        const json = await res.json();
        if (currentReq !== reqIdErp) return;

        const tbody = document.getElementById('tbody_erp');
        tbody.innerHTML = '';

        if (!json.data || json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; color:#94A3B8; padding:2rem;">No se encontraron rendiciones a conciliar con la configuración de filtros actual.</td></tr>';
            document.getElementById('erp_total_badge').innerText = '0 registros';
            actualizarFooterPaginacion('erp', 0, 1, state.erp.per_page, 0);
            sincronizarCheckboxMaestro('erp');
            return;
        }

        document.getElementById('erp_total_badge').innerText = `${json.total} registros`;
        actualizarFooterPaginacion('erp', json.last_page, json.current_page, json.per_page, json.total);

        json.data.forEach(c => {
            cacheErpItems[c.id_rendicion_cobro] = c; // <-- Caché poblada
            const isSelected = state.seleccionErp.has(c.id_rendicion_cobro);
            const estadoRaw = (c.estado || '').toLowerCase();
            const tr = document.createElement('tr');
            tr.className = `row-erp-${estadoRaw}`;
            if (isSelected) tr.classList.add('selected-row');

            const fechaAr = formatearFechaAr(c.fecha);
            const clienteTxt = c.cliente_nombre || '-';
            const cuitTxt = c.cliente_cuit || '-';
            const medioTxt = ETIQUETAS_MEDIO_ERP[c.medio_pago] || c.medio_pago || '-';
            const estadoTxt = ETIQUETAS_ESTADO_RENDICION[estadoRaw] || c.estado || '-';
            const montoFormateado = `$${parseFloat(c.monto).toLocaleString('es-AR', {minimumFractionDigits: 2})}`;

            tr.innerHTML = `
                <td class="td-fixed-chk"><input type="checkbox" onchange="toggleSelectErp(${c.id_rendicion_cobro}, this)" ${isSelected ? 'checked' : ''} title="Seleccionar para organizar conciliación manual"></td>
                <td data-col="id" title="#${c.id_rendicion_cobro}">#${c.id_rendicion_cobro}</td>
                <td data-col="fecha" title="${fechaAr}">${fechaAr}</td>
                <td data-col="cliente" title="${escapeHtml(clienteTxt)}">${escapeHtml(clienteTxt)}</td>
                <td data-col="cuit" title="${escapeHtml(cuitTxt)}">${escapeHtml(cuitTxt)}</td>
                <td data-col="medio" title="${escapeHtml(medioTxt)}">${escapeHtml(medioTxt)}</td>
                <td data-col="monto" title="${montoFormateado}" style="font-weight:600; text-align:right; color:#1E293B;">${montoFormateado}</td>
                <td data-col="estado" title="${escapeHtml(estadoTxt)}"><span class="badge-status">${escapeHtml(estadoTxt)}</span></td>
            `;
            tbody.appendChild(tr);
        });

        aplicarColumnasContraidas('erp');
        sincronizarCheckboxMaestro('erp');
    } catch(e) { console.error(e); }
}

function actualizarFooterPaginacion(tipo, lastPage, currentPage, perPage, total) {
    const controlesEl = document.getElementById(`${tipo}_paginacion_controles`);
    if (!controlesEl) return;

    const totalPaginas = Math.max(1, lastPage);
    const pagActual = Math.min(Math.max(1, currentPage), totalPaginas);

    let html = `
        <button type="button" class="btn-page" ${pagActual <= 1 || total === 0 ? 'disabled' : ''} onclick="irPagina('${tipo}', 1)" title="Primera página">«</button>
        <button type="button" class="btn-page" ${pagActual <= 1 || total === 0 ? 'disabled' : ''} onclick="irPagina('${tipo}', ${pagActual - 1})" title="Página anterior">‹</button>
    `;

    const inicio = Math.max(1, pagActual - 2);
    const fin = Math.min(totalPaginas, pagActual + 2);

    for (let p = inicio; p <= fin; p++) {
        html += `<button type="button" class="btn-page ${p === pagActual ? 'active' : ''}" ${total === 0 ? 'disabled' : ''} onclick="irPagina('${tipo}', ${p})">${p}</button>`;
    }

    html += `
        <button type="button" class="btn-page" ${pagActual >= totalPaginas || total === 0 ? 'disabled' : ''} onclick="irPagina('${tipo}', ${pagActual + 1})" title="Página siguiente">›</button>
        <button type="button" class="btn-page" ${pagActual >= totalPaginas || total === 0 ? 'disabled' : ''} onclick="irPagina('${tipo}', ${totalPaginas})" title="Última página">»</button>
    `;

    controlesEl.innerHTML = html;
}

function irPagina(tipo, p) {
    state[tipo].page = p;
    guardarEstadoEnSession();
    if (tipo === 'banco') cargarMovimientosBancarios();
    else cargarCobranzasErp();
}

function cambiarPerPageBanco(val) {
    state.banco.per_page = parseInt(val);
    state.banco.page = 1;
    guardarEstadoEnSession();
    cargarMovimientosBancarios();
}

function cambiarPerPageErp(val) {
    state.erp.per_page = parseInt(val);
    state.erp.page = 1;
    guardarEstadoEnSession();
    cargarCobranzasErp();
}

/**
 * COLUMNAS CONTRAÍBLES
 */
function toggleColumna(tipo, colKey, th) {
    const set = state[tipo].colsContraidas;
    if (set.has(colKey)) set.delete(colKey);
    else set.add(colKey);
    aplicarColumnasContraidas(tipo);
    guardarEstadoEnSession();
}

function aplicarColumnasContraidas(tipo) {
    const set = state[tipo].colsContraidas;
    const tabla = document.getElementById(`tabla_${tipo}`);
    if (!tabla) return;

    tabla.querySelectorAll('col[data-col]').forEach(col => {
        const key = col.getAttribute('data-col');
        col.classList.toggle('col-contracted', set.has(key));
    });

    tabla.querySelectorAll('th[data-col]').forEach(th => {
        const key = th.getAttribute('data-col');
        const titleEl = th.querySelector('.th-title');
        const shortEl = th.querySelector('.th-short');
        const tituloVisible = titleEl ? titleEl.textContent.trim() : '';
        const label = th.getAttribute('data-label') || tituloVisible;
        const isContraida = set.has(key);

        if (shortEl && tituloVisible) {
            shortEl.textContent = tituloVisible.charAt(0).toUpperCase();
        }

        th.classList.toggle('col-contracted', isContraida);
        th.title = isContraida ? `Expandir columna: ${label}` : `Clic para contraer columna: ${label}`;

        const icon = th.querySelector('.th-toggle-icon');
        if (icon) {
            icon.innerText = isContraida ? '▸' : '◂';
        }
    });

    tabla.querySelectorAll('tbody td[data-col]').forEach(td => {
        const key = td.getAttribute('data-col');
        const isContraida = set.has(key);

        if (td.dataset.originalHtml === undefined) {
            td.dataset.originalHtml = td.innerHTML;
        }

        td.classList.toggle('col-contracted', isContraida);
        if (isContraida) {
            td.innerHTML = '<span class="col-dot"></span>';
        } else {
            td.innerHTML = td.dataset.originalHtml;
        }
    });
}

/**
 * SELECCIÓN TEMPORAL Y BARRA CONTEXTUAL
 */
function sincronizarCheckboxMaestro(tipo) {
    const masterId = tipo === 'banco' ? 'chk_all_banco' : 'chk_all_erp';
    const tbodyId = tipo === 'banco' ? 'tbody_banco' : 'tbody_erp';
    const master = document.getElementById(masterId);
    if (!master) return;

    const checkboxes = Array.from(document.querySelectorAll(`#${tbodyId} input[type="checkbox"]`));
    if (checkboxes.length === 0) {
        master.checked = false;
        master.indeterminate = false;
        return;
    }

    const marcados = checkboxes.filter(chk => chk.checked).length;
    master.checked = (marcados === checkboxes.length);
    master.indeterminate = (marcados > 0 && marcados < checkboxes.length);
}

function toggleSelectBanco(id, chk) {
    const numId = Number(id);
    if (chk.checked) state.seleccionBanco.add(numId);
    else state.seleccionBanco.delete(numId);
    chk.closest('tr').classList.toggle('selected-row', chk.checked);
    sincronizarCheckboxMaestro('banco');
    actualizarBarraContextual();
    guardarEstadoEnSession();
}

function toggleSelectErp(id, chk) {
    const numId = Number(id);
    if (chk.checked) state.seleccionErp.add(numId);
    else state.seleccionErp.delete(numId);
    chk.closest('tr').classList.toggle('selected-row', chk.checked);
    sincronizarCheckboxMaestro('erp');
    actualizarBarraContextual();
    guardarEstadoEnSession();
}

function toggleSelectAllBanco(master) {
    document.querySelectorAll('#tbody_banco input[type="checkbox"]').forEach(chk => {
        chk.checked = master.checked;
        const id = Number(chk.getAttribute('onchange').match(/\d+/)[0]);
        if (master.checked) state.seleccionBanco.add(id);
        else state.seleccionBanco.delete(id);
        chk.closest('tr').classList.toggle('selected-row', master.checked);
    });
    master.indeterminate = false;
    actualizarBarraContextual();
    guardarEstadoEnSession();
}

function toggleSelectAllErp(master) {
    document.querySelectorAll('#tbody_erp input[type="checkbox"]').forEach(chk => {
        chk.checked = master.checked;
        const id = Number(chk.getAttribute('onchange').match(/\d+/)[0]);
        if (master.checked) state.seleccionErp.add(id);
        else state.seleccionErp.delete(id);
        chk.closest('tr').classList.toggle('selected-row', master.checked);
    });
    master.indeterminate = false;
    actualizarBarraContextual();
    guardarEstadoEnSession();
}

function actualizarBarraContextual() {
    const bCount = state.seleccionBanco.size;
    const eCount = state.seleccionErp.size;
    const totalSel = bCount + eCount;

    const txt = document.getElementById('contextual_selection_text');
    const btnQuitar = document.getElementById('btn_quitar_seleccion');
    if (txt) {
        txt.innerText = `${bCount} movimiento(s) bancario(s) y ${eCount} rendición(es) seleccionado(s)`;
    }
    if (btnQuitar) {
        btnQuitar.disabled = (totalSel === 0);
        btnQuitar.style.opacity = (totalSel === 0) ? '0.5' : '1';
        btnQuitar.style.cursor = (totalSel === 0) ? 'not-allowed' : 'pointer';
    }
}

function quitarSeleccion() {
    state.seleccionBanco.clear();
    state.seleccionErp.clear();
    const chkAllBanco = document.getElementById('chk_all_banco');
    const chkAllErp = document.getElementById('chk_all_erp');
    if (chkAllBanco) { chkAllBanco.checked = false; chkAllBanco.indeterminate = false; }
    if (chkAllErp) { chkAllErp.checked = false; chkAllErp.indeterminate = false; }
    document.querySelectorAll('#tbody_banco input[type="checkbox"], #tbody_erp input[type="checkbox"]').forEach(chk => {
        chk.checked = false;
    });
    document.querySelectorAll('#tbody_banco tr, #tbody_erp tr').forEach(tr => {
        tr.classList.remove('selected-row');
    });
    actualizarBarraContextual();
    guardarEstadoEnSession();
}

/**
 * MODAL ORGANIZAR SELECCIÓN Y VINCULACIÓN VISUAL (SECCIÓN 8)
 */
function abrirModalOrganizar() {
    try {
        if (state.seleccionBanco.size === 0 && state.seleccionErp.size === 0) {
            mostrarConfirmacionSistema({
                icono: 'ℹ️',
                titulo: 'No hay registros seleccionados',
                mensaje: 'Para organizar una conciliación, primero tildá al menos un movimiento bancario o una rendición a conciliar en las tablas superiores.',
                botones: [{ texto: 'Entendido', clase: 'btn-primary' }]
            });
            return;
        }

        const colBanco = document.getElementById('org_col_banco');
        const colErp = document.getElementById('org_col_erp');
        if (!colBanco || !colErp) return;

        colBanco.innerHTML = '';
        colErp.innerHTML = '';

        state.seleccionBanco.forEach(rawId => {
            const id = Number(rawId);
            const m = cacheBancoItems[id] || {};
            const item = document.createElement('div');
            item.className = 'organizar-item';
            item.id = `org_item_banco_${id}`;

            const fechaAr = formatearFechaAr(m.fecha_operacion || '');
            const signoStr = m.signo_monto === 'egreso' ? '-' : '+';
            const montoVal = m.monto !== undefined ? parseFloat(m.monto).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '0,00';
            const montoStr = `${signoStr}$${montoVal}`;
            const colorMonto = m.signo_monto === 'egreso' ? '#DC2626' : '#16A34A';
            const desc = expandirAbreviaturasDescripcion(m.descripcion_normalizada || m.descripcion_original || `Movimiento #${id}`);
            const cuitTxt = m.cuit_extraido ? `CUIT: ${m.cuit_extraido}` : '';

            item.innerHTML = `
                <div class="organizar-item-top">
                    <span><strong>#${id}</strong> ${fechaAr ? '· <span style="color:#64748B;">' + fechaAr + '</span>' : ''} ${cuitTxt ? '· <span style="font-size:0.75rem; color:#475569;">' + escapeHtml(cuitTxt) + '</span>' : ''}</span>
                    <strong style="color:${colorMonto};">${montoStr}</strong>
                </div>
                <div class="organizar-item-desc" title="${escapeHtml(desc)}">${escapeHtml(desc)}</div>
            `;

            item.onclick = () => seleccionarParaConectar(id, 'banco', item);
            colBanco.appendChild(item);
        });

        state.seleccionErp.forEach(rawId => {
            const id = Number(rawId);
            const c = cacheErpItems[id] || {};
            const item = document.createElement('div');
            item.className = 'organizar-item';
            item.id = `org_item_erp_${id}`;

            const fechaAr = formatearFechaAr(c.fecha || '');
            const montoVal = c.monto !== undefined ? parseFloat(c.monto).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '0,00';
            const montoStr = `$${montoVal}`;
            const cliente = c.cliente_nombre || `Rendición #${id}`;
            const medio = ETIQUETAS_MEDIO_ERP[c.medio_pago] || c.medio_pago || '';
            const cuitTxt = c.cliente_cuit ? `CUIT: ${c.cliente_cuit}` : '';

            item.innerHTML = `
                <div class="organizar-item-top">
                    <span><strong>#${id}</strong> ${fechaAr ? '· <span style="color:#64748B;">' + fechaAr + '</span>' : ''} ${cuitTxt ? '· <span style="font-size:0.75rem; color:#475569;">' + escapeHtml(cuitTxt) + '</span>' : ''}</span>
                    <strong style="color:#1E293B;">${montoStr}</strong>
                </div>
                <div class="organizar-item-desc" title="${escapeHtml(cliente)} ${medio ? '(' + escapeHtml(medio) + ')' : ''}">
                    ${escapeHtml(cliente)} ${medio ? '· <span style="color:#64748B;">' + escapeHtml(medio) + '</span>' : ''}
                </div>
            `;

            item.onclick = () => seleccionarParaConectar(id, 'erp', item);
            colErp.appendChild(item);
        });

        state.conexionesTemp = [];
        historialConexiones = [[]];
        indiceHistorialConexiones = 0;
        nombresPreviewGrupos = {};
        aisladosChecks = {};

        const modal = document.getElementById('modal_organizar');
        if (modal) modal.classList.add('active');

        actualizarBotonesHistorial();
        renderVistaPreviaGrupos();
        requestAnimationFrame(() => dibujarLineasConexion());

    } catch (err) {
        console.error("Error crítico al intentar abrir el modal de organizar:", err);
    }
}

function seleccionarParaConectar(id, tipo, el) {
    const numId = Number(id);

    // Caso B: Clic consecutivo en el mismo lado (genera grupo individual provisional/confirmado del anterior)
    if (activeRecord.tipo === tipo) {
        if (activeRecord.id === numId) {
            // Clic repetido sobre el mismo desmarca la actividad actual
            el.classList.remove('active-connect');
            activeRecord = { tipo: null, id: null };
            renderVistaPreviaGrupos();
            return;
        }
        // El registro anterior pasa a ser grupo individual confirmado
        gruposIndividualesConfirmados.add(`${activeRecord.tipo}:${activeRecord.id}`);
        // Limpiamos su estilo activo anterior
        const prevEl = document.getElementById(`org_item_${activeRecord.tipo}_${activeRecord.id}`);
        if (prevEl) prevEl.classList.remove('active-connect');
    }

    // Si el nuevo registro ya tenía un grupo individual confirmado, lo removemos para permitir su vinculación
    gruposIndividualesConfirmados.delete(`${tipo}:${numId}`);

    if (activeRecord.tipo && activeRecord.tipo !== tipo) {
        // Caso A: Conexión cruzada (Movimiento <-> Rendición)
        const bId = activeRecord.tipo === 'banco' ? activeRecord.id : numId;
        const eId = activeRecord.tipo === 'erp' ? activeRecord.id : numId;

        const yaExiste = state.conexionesTemp.some(([b, e]) => Number(b) === bId && Number(e) === eId);
        if (!yaExiste) {
            state.conexionesTemp.push([bId, eId]);
            registrarEstadoConexiones();
        }

        document.querySelectorAll('.organizar-item').forEach(i => i.classList.remove('active-connect'));
        activeRecord = { tipo: null, id: null };
    } else {
        // Establecer nuevo registro activo
        document.querySelectorAll('.organizar-item').forEach(i => i.classList.remove('active-connect'));
        el.classList.add('active-connect');
        activeRecord = { tipo, id: numId };
    }

    renderVistaPreviaGrupos();
}

function dibujarLineasConexion() {
    const svg = document.getElementById('connectionsSvg');
    const container = document.getElementById('organizar_container');
    if (!svg || !container) return;

    svg.innerHTML = '';

    // Marcar tarjetas conectadas
    document.querySelectorAll('.organizar-item').forEach(el => el.classList.remove('is-connected'));

    const svgRect = svg.getBoundingClientRect();
    if (svgRect.width === 0 || svgRect.height === 0) return;

    state.conexionesTemp.forEach(([bId, eId], index) => {
        const bEl = document.getElementById(`org_item_banco_${bId}`);
        const eEl = document.getElementById(`org_item_erp_${eId}`);
        if (!bEl || !eEl) return;

        bEl.classList.add('is-connected');
        eEl.classList.add('is-connected');

        const bRect = bEl.getBoundingClientRect();
        const eRect = eEl.getBoundingClientRect();

        const x1 = 0;
        const y1 = (bRect.top + bRect.height / 2) - svgRect.top;
        const x2 = svgRect.width;
        const y2 = (eRect.top + eRect.height / 2) - svgRect.top;
        const cx = svgRect.width / 2;

        // Curva Bézier suave de extremo a extremo
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', `M ${x1} ${y1} C ${cx} ${y1}, ${cx} ${y2}, ${x2} ${y2}`);
        path.setAttribute('class', 'conn-line');
        path.title = 'Clic para eliminar esta conexión';
        path.onclick = (ev) => {
            ev.stopPropagation();
            state.conexionesTemp.splice(index, 1);
            registrarEstadoConexiones();
        };
        svg.appendChild(path);
    });
}

function intentarCerrarOrganizar() {
    if (state.conexionesTemp.length > 0 || gruposIndividualesConfirmados.size > 0 || (activeRecord.tipo && activeRecord.id)) {
        mostrarConfirmacionSistema({
            icono: '🔗',
            titulo: 'Tenés cambios sin guardar',
            mensaje: '¿Qué querés hacer con la distribución de grupos que armaste?',
            botones: [
                { texto: 'Seguir editando', clase: 'btn-secondary' },
                { texto: 'Descartar cambios', clase: 'btn-secondary', onClick: () => cancelarOrganizar() },
                { texto: 'Guardar distribución', clase: 'btn-primary', onClick: () => guardarDistribucionGrupos() }
            ]
        });
        return;
    }
    cancelarOrganizar();
}

function cancelarOrganizar() {
    document.getElementById('modal_organizar').classList.remove('active');
}

/**
 * ÁREA DE CONCILIACIÓN Y GRUPOS (SECCIÓN 9 Y 12)
 */
function cambiarPestana(pestana, btn) {
    document.querySelectorAll('.nav-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    state.pestanaActiva = pestana;
    guardarEstadoEnSession();

    const area = document.getElementById('tab_content_area');
    if (pestana === 'manual') {
        renderGruposManuales();
    } else if (pestana === 'alta_confianza' || pestana === 'para_revisar') {
        cargarPropuestasMatching(pestana);
    } else if (pestana === 'historial') {
        cargarHistorialConciliaciones(); // <--- Reemplazamos el texto estático por la función de carga
    }
}

function renderGruposManuales() {
    repararInformacionGruposGuardados(); // Asegura que ningún grupo pierda su información al renderizar

    const area = document.getElementById('tab_content_area');
    if (state.gruposManuales.length === 0) {
        area.innerHTML = `
            <p style="margin-top:0.5rem; color:#64748B; font-size:0.88rem;">
                No hay grupos organizados todavía. Seleccioná movimientos y cobranzas en las tablas superiores y pulsá <strong>"Organizar selección"</strong>.
            </p>
        `;
        return;
    }

    const incompletos = state.gruposManuales.filter(g => g.bancoIds.length === 0 || g.erpIds.length === 0);
    const completos = state.gruposManuales.filter(g => g.bancoIds.length > 0 && g.erpIds.length > 0);
    const ordenados = [...incompletos, ...completos];

    const todosSeleccionados = completos.length > 0 && completos.every(g => g.checked);

    let html = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #E2E8F0;">
            <label style="font-size:0.84rem; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:0.4rem; color:#334155;">
                <input type="checkbox" style="width:15px; height:15px; outline:none !important; box-shadow:none !important;" ${todosSeleccionados ? 'checked' : ''} onchange="seleccionarTodosConciliables(this)"> Seleccionar todos los conciliables
            </label>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1rem;">
    `;

    ordenados.forEach(g => {
        const esIncompleto = g.bancoIds.length === 0 || g.erpIds.length === 0;
        
        let bancoHtml = '<span style="color:#94A3B8; font-style:italic;">Sin movimiento asignado</span>';
        if (g.bancoItemsInfo && g.bancoItemsInfo.length > 0) {
            bancoHtml = g.bancoItemsInfo.map(m => {
                const id = m.id_movimiento_bancario || m.id;
                const fechaAr = formatearFechaAr(m.fecha_operacion || m.fecha || '');
                const signoStr = m.signo_monto === 'egreso' ? '-' : '+';
                const montoVal = m.monto !== undefined ? parseFloat(m.monto).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '0,00';
                const montoStr = `${signoStr}$${montoVal}`;
                const colorMonto = m.signo_monto === 'egreso' ? '#DC2626' : '#16A34A';
                const desc = expandirAbreviaturasDescripcion(m.descripcion_normalizada || m.descripcion_original || `Movimiento #${id}`);
                const cuitTxt = m.cuit_extraido ? `CUIT: ${m.cuit_extraido}` : '';

                return `
                    <div style="display:flex; flex-direction:column; gap:0.1rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span><strong>#${id}</strong> ${fechaAr ? '· ' + fechaAr : ''} ${cuitTxt ? '· ' + escapeHtml(cuitTxt) : ''}</span>
                            <strong style="color:${colorMonto};">${montoStr}</strong>
                        </div>
                        <div style="font-size:0.75rem; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(desc)}">
                            ${escapeHtml(desc)}
                        </div>
                    </div>
                `;
            }).join('<hr style="border:0; border-top:1px dashed #CBD5E1; margin:4px 0;">');
        }

        let erpHtml = '<span style="color:#94A3B8; font-style:italic;">Sin rendición asignada</span>';
        if (g.erpItemsInfo && g.erpItemsInfo.length > 0) {
            erpHtml = g.erpItemsInfo.map(c => {
                const id = c.id_rendicion_cobro || c.id;
                const fechaAr = formatearFechaAr(c.fecha || '');
                const montoVal = c.monto !== undefined ? parseFloat(c.monto).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '0,00';
                const montoStr = `$${montoVal}`;
                const cliente = c.cliente_nombre || `Rendición #${id}`;
                const medio = ETIQUETAS_MEDIO_ERP[c.medio_pago] || c.medio_pago || '';
                const cuitTxt = c.cliente_cuit ? `CUIT: ${c.cliente_cuit}` : '';

                return `
                    <div style="display:flex; flex-direction:column; gap:0.1rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span><strong>#${id}</strong> ${fechaAr ? '· ' + fechaAr : ''} ${cuitTxt ? '· ' + escapeHtml(cuitTxt) : ''}</span>
                            <strong style="color:#1E293B;">${montoStr}</strong>
                        </div>
                        <div style="font-size:0.75rem; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(cliente)}">
                            ${escapeHtml(cliente)} ${medio ? '· ' + escapeHtml(medio) : ''}
                        </div>
                    </div>
                `;
            }).join('<hr style="border:0; border-top:1px dashed #CBD5E1; margin:4px 0;">');
        }

        html += `
            <div class="manual-match-row ${esIncompleto ? 'incomplete' : 'complete'}">
                <div style="display:flex; align-items:flex-start; gap:0.5rem; min-width:0;">
                    <input type="checkbox" style="width:15px; height:15px; margin-top:0.2rem; outline:none !important; box-shadow:none !important; flex-shrink:0;" ${esIncompleto ? 'disabled title="Necesita contraparte para conciliar"' : ''} ${g.checked ? 'checked' : ''} onchange="toggleGrupoCheck(${g.id}, this)">
                    <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:0.35rem;">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:0.4rem;">
                            <input type="text" value="${escapeHtml(g.nombre)}" onchange="renombrarGrupo(${g.id}, this.value)" style="border:1px dashed #CBD5E1; border-radius:4px; padding:2px 6px; font-weight:700; color:#1E293B; font-size:0.82rem; width:100%; background:rgba(255,255,255,0.9);">
                        </div>
                        <div class="manual-card-side">
                            ${bancoHtml}
                        </div>
                    </div>
                </div>

                <div class="manual-line-connector">
                    <span style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:999px; padding:2px 6px; font-size:0.68rem; font-weight:700; color:#64748B; z-index:2;">↔</span>
                </div>

                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:0.5rem; min-width:0;">
                    <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:0.35rem;">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:0.4rem; height:24px;">
                            <span style="font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:999px; ${esIncompleto ? 'background:#FEF3C7; color:#92400E;' : 'background:#DCFCE7; color:#166534;'}">
                                ${esIncompleto ? 'Pendiente' : 'Completo'}
                            </span>
                        </div>
                        <div class="manual-card-side">
                            ${erpHtml}
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; height:100%; padding-top:1.4rem;">
                        <button type="button" class="btn-action btn-secondary" style="padding:0.25rem 0.55rem; font-size:0.78rem; height:32px;" onclick="eliminarGrupo(${g.id})">Eliminar</button>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    html += `
        <div style="display:flex; justify-content:flex-end; align-items:center; padding-top:0.75rem; border-top:1px solid #E2E8F0;">
            <button type="button" class="btn-action btn-success" style="padding:0.4rem 0.8rem; font-size:0.82rem;" onclick="confirmarGruposSeleccionados()">Confirmar conciliaciones seleccionadas</button>
        </div>
    `;

    area.innerHTML = html;
}

function toggleGrupoCheck(id, chk) {
    const g = state.gruposManuales.find(x => x.id === id);
    if (g) g.checked = chk.checked;
    guardarEstadoEnSession();
}

function renombrarGrupo(id, nuevoNombre) {
    const g = state.gruposManuales.find(x => x.id === id);
    if (g) g.nombre = nuevoNombre;
    guardarEstadoEnSession();
}

function eliminarGrupo(id) {
    state.gruposManuales = state.gruposManuales.filter(x => x.id !== id);
    guardarEstadoEnSession();
    renderGruposManuales();
}

function seleccionarTodosConciliables(master) {
    state.gruposManuales.forEach(g => {
        if (g.bancoIds.length > 0 && g.erpIds.length > 0) {
            g.checked = master.checked;
        }
    });
    renderGruposManuales();
}

async function confirmarGruposSeleccionados() {
    const paraConfirmar = state.gruposManuales.filter(g => g.checked && g.bancoIds.length > 0 && g.erpIds.length > 0);
    
    if (paraConfirmar.length === 0) {
        mostrarConfirmacionSistema({
            icono: 'ℹ️',
            titulo: 'Selección incompleta',
            mensaje: 'Seleccioná al menos un grupo completo con su casilla tildada para poder conciliar.',
            botones: [{ texto: 'Entendido', clase: 'btn-primary' }]
        });
        return;
    }

    // Generamos la lista de nombres itemizada con viñetas
    const listaGruposHtml = paraConfirmar.map(g => `• ${escapeHtml(g.nombre)}`).join('<br>');

    mostrarConfirmacionSistema({
        titulo: 'Confirmar conciliaciones',
        mensaje: `¿Estás seguro de que querés confirmar ${paraConfirmar.length} grupo(s) de conciliación seleccionados?<br><br>${listaGruposHtml}`,
        botones: [
            { texto: 'Cancelar', clase: 'btn-secondary' },
            {
                texto: 'Sí, confirmar',
                clase: 'btn-success',
                onClick: async () => {
                    for (const g of paraConfirmar) {
                        for (const bId of g.bancoIds) {
                            for (const eId of g.erpIds) {
                                try {
                                    await fetch('/api/conciliacion/manual', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: JSON.stringify({
                                            id_movimiento_bancario: bId,
                                            id_rendicion_cobro: eId,
                                            observaciones: `Conciliado en ${g.nombre}`
                                        })
                                    });
                                } catch(e) {}
                            }
                        }
                    }

                    mostrarConfirmacionSistema({
                        icono: '✅',
                        titulo: 'Conciliación exitosa',
                        mensaje: 'Las conciliaciones seleccionadas fueron confirmadas correctamente.',
                        botones: [{ texto: 'Aceptar', clase: 'btn-primary' }]
                    });

                    state.gruposManuales = state.gruposManuales.filter(g => !g.checked);
                    quitarSeleccion();
                    guardarEstadoEnSession();
                    cargarMovimientosBancarios();
                    cargarCobranzasErp();
                    renderGruposManuales();
                }
            }
        ]
    });
}

async function cargarPropuestasMatching(pestana) {
    const area = document.getElementById('tab_content_area');
    area.innerHTML = '<p style="margin-top:0.5rem; color:#64748B; font-size:0.88rem;">Evaluando coincidencias automáticas...</p>';
    try {
        const res = await fetch('/api/conciliacion/propuestas');
        const json = await res.json();
        const propuestas = pestana === 'alta_confianza' ? json.data.alta_confianza : json.data.para_revisar;

        if (!propuestas || propuestas.length === 0) {
            area.innerHTML = `<p style="margin-top:0.5rem; color:#64748B; font-size:0.88rem;">No hay propuestas de ${pestana === 'alta_confianza' ? 'alta confianza' : 'revisión'}.</p>`;
            return;
        }

        let html = '<div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.5rem;">';
        propuestas.forEach(p => {
            const montoMov = p.movimiento && p.movimiento.monto !== undefined ? `$${parseFloat(p.movimiento.monto).toLocaleString('es-AR', {minimumFractionDigits:2})}` : '';
            const montoCobro = p.cobro && p.cobro.monto !== undefined ? `$${parseFloat(p.cobro.monto).toLocaleString('es-AR', {minimumFractionDigits:2})}` : '';
            const clienteNom = p.cliente && p.cliente.nombre ? p.cliente.nombre : (p.cobro.cliente_nombre || `#${p.cobro.id_cliente}`);

            html += `
                <div style="background:#FFFFFF; border:1px solid #CBD5E1; border-left:4px solid #2563EB; border-radius:8px; padding:0.65rem 0.85rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 1px 2px rgba(0,0,0,0.03); font-size:0.85rem;">
                    <div style="display:flex; flex-direction:column; gap:0.2rem; flex:1; min-width:0;">
                        <div style="font-size:0.78rem; color:#475569;">
                            🏦 <strong>Mov. #${p.movimiento.id_movimiento_bancario}</strong> (${formatearFechaAr(p.movimiento.fecha_operacion)}) - ${escapeHtml(p.movimiento.descripcion_normalizada || p.movimiento.descripcion_original)} <strong style="color:#16A34A;">${montoMov}</strong>
                        </div>
                        <div style="font-size:0.78rem; color:#475569;">
                            📊 <strong>Rend. #${p.cobro.id_rendicion_cobro}</strong> (${formatearFechaAr(p.cobro.fecha)}) - Cliente: ${escapeHtml(clienteNom)} <strong style="color:#1E293B;">${montoCobro}</strong>
                        </div>
                    </div>
                    <div style="flex-shrink:0; margin-left:0.75rem;">
                        <button type="button" class="btn-action btn-success" style="padding:0.3rem 0.7rem; font-size:0.78rem;" onclick="confirmarMatchIndividual(${p.movimiento.id_movimiento_bancario}, ${p.cobro.id_rendicion_cobro})">Confirmar</button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        area.innerHTML = html;
    } catch(e) { 
        area.innerHTML = '<p style="color:#DC2626; font-size:0.88rem;">Error al evaluar coincidencias.</p>'; 
    }
}

async function cargarHistorialConciliaciones() {
    const area = document.getElementById('tab_content_area');
    area.innerHTML = '<p style="margin-top:0.5rem; color:#64748B; font-size:0.88rem;">Cargando conciliaciones realizadas...</p>';
    
    try {
        // Consultamos los movimientos matcheados (el backend ya incluye las rendiciones relacionadas)
        const res = await fetch('/api/movimientos-bancarios?estado_conciliacion=matcheado&per_page=50');
        const json = await res.json();

        if (!json.data || json.data.length === 0) {
            area.innerHTML = '<p style="margin-top:0.5rem; color:#64748B; font-size:0.88rem;">No hay conciliaciones realizadas registradas todavía.</p>';
            return;
        }

        let html = '<div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.5rem;">';
        
        json.data.forEach(m => {
            const fechaBanco = formatearFechaAr(m.fecha_operacion);
            const signoStr = m.signo_monto === 'egreso' ? '-' : '+';
            const montoBancoVal = m.monto !== undefined ? parseFloat(m.monto).toLocaleString('es-AR', {minimumFractionDigits: 2}) : '0,00';
            const montoBancoStr = `${signoStr}$${montoBancoVal}`;
            const colorMonto = m.signo_monto === 'egreso' ? '#DC2626' : '#16A34A';
            const descBanco = expandirAbreviaturasDescripcion(m.descripcion_normalizada || m.descripcion_original || `Movimiento #${m.id_movimiento_bancario}`);
            const cuitBanco = m.cuit_extraido ? `CUIT: ${m.cuit_extraido}` : '';

            // 1. Renderizado del lado izquierdo (Movimiento Bancario)
            const bancoHtml = `
                <div style="display:flex; flex-direction:column; gap:0.1rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span><strong>#${m.id_movimiento_bancario}</strong> ${fechaBanco ? '· ' + fechaBanco : ''} ${cuitBanco ? '· ' + escapeHtml(cuitBanco) : ''}</span>
                        <strong style="color:${colorMonto};">${montoBancoStr}</strong>
                    </div>
                    <div style="font-size:0.75rem; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(descBanco)}">
                        ${escapeHtml(descBanco)}
                    </div>
                </div>
            `;

            // 2. Renderizado del lado derecho (Rendición/es ERP asociada/s real/es)
            let erpHtml = '<span style="color:#94A3B8; font-style:italic;">Sin rendición asociada</span>';
            const rendicionesAsociadas = m.rendiciones_cobro || m.rendicionesCobro || [];

            if (rendicionesAsociadas.length > 0) {
                erpHtml = rendicionesAsociadas.map(c => {
                    const idC = c.id_rendicion_cobro || c.id;
                    const fechaErp = formatearFechaAr(c.fecha_registro || c.fecha || '');
                    const montoErpVal = c.monto !== undefined ? parseFloat(c.monto).toLocaleString('es-AR', {minimumFractionDigits: 2}) : '0,00';
                    const clienteNom = c.cliente_nombre || `Rendición #${idC}`;
                    const medio = ETIQUETAS_MEDIO_ERP[c.medio_pago] || c.medio_pago || '';
                    const cuitErp = c.cliente_cuit ? `CUIT: ${c.cliente_cuit}` : '';

                    return `
                        <div style="display:flex; flex-direction:column; gap:0.1rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span><strong>#${idC}</strong> ${fechaErp ? '· ' + fechaErp : ''} ${cuitErp ? '· ' + escapeHtml(cuitErp) : ''}</span>
                                <strong style="color:#1E293B;">$${montoErpVal}</strong>
                            </div>
                            <div style="font-size:0.75rem; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(clienteNom)}">
                                ${escapeHtml(clienteNom)} ${medio ? '· ' + escapeHtml(medio) : ''}
                            </div>
                        </div>
                    `;
                }).join('<hr style="border:0; border-top:1px dashed #CBD5E1; margin:4px 0;">');
            }

            // Estructura en paralelo idéntica a la conciliación manual (.manual-match-row.complete)
            html += `
                <div class="manual-match-row complete">
                    <div style="display:flex; align-items:flex-start; gap:0.5rem; min-width:0; width:100%;">
                        <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:0.35rem;">
                            <div style="display:flex; align-items:center; justify-content:space-between; height:24px;">
                                <span style="font-size:0.7rem; font-weight:700; color:#1E293B;">Movimiento Bancario</span>
                                <span style="font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:999px; background:#DCFCE7; color:#166534;">Matcheado</span>
                            </div>
                            <div class="manual-card-side">
                                ${bancoHtml}
                            </div>
                        </div>
                    </div>

                    <div class="manual-line-connector">
                        <span style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:999px; padding:2px 6px; font-size:0.68rem; font-weight:700; color:#64748B; z-index:2;">↔</span>
                    </div>

                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:0.5rem; min-width:0; width:100%;">
                        <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:0.35rem;">
                            <div style="display:flex; align-items:center; justify-content:space-between; height:24px;">
                                <span style="font-size:0.7rem; font-weight:700; color:#1E293B;">Rendición ERP Conciliada</span>
                            </div>
                            <div class="manual-card-side">
                                ${erpHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        area.innerHTML = html;
    } catch(e) {
        area.innerHTML = '<p style="color:#DC2626; font-size:0.88rem;">Error al cargar el historial de conciliaciones.</p>';
    }
}

async function confirmarMatchIndividual(idMov, idCobro) {
    try {
        await fetch('/api/conciliacion/manual', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                id_movimiento_bancario: idMov,
                id_rendicion_cobro: idCobro,
                observaciones: 'Confirmado desde sugerencias de matching'
            })
        });
        alert("Match confirmado.");
        cargarMovimientosBancarios();
        cargarCobranzasErp();
        document.querySelector('.nav-tab-btn.active').click();
    } catch(e) { alert("Error: " + e.message); }
}

async function ejecutarAutoMatching() {
    if (!confirm("¿Deseás ejecutar el motor de matching sobre los registros de alta confianza?")) return;
    try {
        const res = await fetch('/api/conciliacion/auto-matching', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        const json = await res.json();
        alert(json.mensaje || "Auto-matching finalizado.");
        cargarMovimientosBancarios();
        cargarCobranzasErp();
    } catch(e) { alert("Error: " + e.message); }
}
/**Funciones pa no se que */
function registrarEstadoConexiones() {
    historialConexiones = historialConexiones.slice(0, indiceHistorialConexiones + 1);
    historialConexiones.push(state.conexionesTemp.map(par => [...par]));
    indiceHistorialConexiones++;
    actualizarBotonesHistorial();
    renderVistaPreviaGrupos();
    dibujarLineasConexion();
}

function actualizarBotonesHistorial() {
    const btnUndo = document.getElementById('btn_org_undo');
    const btnRedo = document.getElementById('btn_org_redo');
    if (btnUndo) btnUndo.disabled = (indiceHistorialConexiones <= 0);
    if (btnRedo) btnRedo.disabled = (indiceHistorialConexiones >= historialConexiones.length - 1);
}

function deshacerConexion() {
    if (indiceHistorialConexiones <= 0) return;
    indiceHistorialConexiones--;
    state.conexionesTemp = historialConexiones[indiceHistorialConexiones].map(par => [...par]);
    actualizarBotonesHistorial();
    renderVistaPreviaGrupos();
    dibujarLineasConexion();
}

function rehacerConexion() {
    if (indiceHistorialConexiones >= historialConexiones.length - 1) return;
    indiceHistorialConexiones++;
    state.conexionesTemp = historialConexiones[indiceHistorialConexiones].map(par => [...par]);
    actualizarBotonesHistorial();
    renderVistaPreviaGrupos();
    dibujarLineasConexion();
}

function calcularGruposVinculados() {
    const bancoList = Array.from(state.seleccionBanco);
    const erpList = Array.from(state.seleccionErp);

    const adjBanco = {};
    const adjErp = {};
    bancoList.forEach(b => adjBanco[b] = new Set());
    erpList.forEach(e => adjErp[e] = new Set());

    state.conexionesTemp.forEach(([b, e]) => {
        if (adjBanco[b]) adjBanco[b].add(e);
        if (adjErp[e]) adjErp[e].add(b);
    });

    const visB = new Set();
    const visE = new Set();
    const grupos = [];

    bancoList.forEach(bStart => {
        if (visB.has(bStart) || adjBanco[bStart].size === 0) return;
        const gB = [];
        const gE = [];
        const cola = [{ tipo: 'b', id: bStart }];
        visB.add(bStart);

        while (cola.length > 0) {
            const actual = cola.shift();
            if (actual.tipo === 'b') {
                gB.push(actual.id);
                adjBanco[actual.id].forEach(eNext => {
                    if (!visE.has(eNext)) {
                        visE.add(eNext);
                        cola.push({ tipo: 'e', id: eNext });
                    }
                });
            } else {
                gE.push(actual.id);
                adjErp[actual.id].forEach(bNext => {
                    if (!visB.has(bNext)) {
                        visB.add(bNext);
                        cola.push({ tipo: 'b', id: bNext });
                    }
                });
            }
        }
        grupos.push({ bancoIds: gB, erpIds: gE, completo: true });
    });

    return grupos;
}

function renderVistaPreviaGrupos() {
    // 1. Renderizar Grupos Vinculados
    const contGrupos = document.getElementById('org_preview_grupos');
    const countGrupos = document.getElementById('org_preview_count');
    if (contGrupos) {
        const gruposVinculados = calcularGruposVinculados();
        if (countGrupos) countGrupos.innerText = gruposVinculados.length;
        const baseNum = state.gruposManuales.length + 1;
        contGrupos.innerHTML = '';

        if (gruposVinculados.length === 0) {
            contGrupos.innerHTML = '<div style="font-size:0.8rem; color:#94A3B8; grid-column: 1 / -1; padding: 0.25rem 0;">No hay grupos vinculados creados aún. Conectá registros arriba.</div>';
        } else {
            gruposVinculados.forEach((g, idx) => {
                const claveUnica = `B:${g.bancoIds.join(',')}|E:${g.erpIds.join(',')}`;
                const nombreDefecto = `Grupo ${baseNum + idx}`;
                const nombreActual = nombresPreviewGrupos[claveUnica] || nombreDefecto;
                const bTxt = g.bancoIds.length > 0 ? `Mov. #${g.bancoIds.join(', #')}` : '';
                const eTxt = g.erpIds.length > 0 ? `Rend. #${g.erpIds.join(', #')}` : '';

                const card = document.createElement('div');
                card.className = 'preview-group-card';
                card.innerHTML = `
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.4rem;">
                        <input type="text"
                               value="${escapeHtml(nombreActual)}"
                               onchange="nombresPreviewGrupos['${claveUnica}'] = this.value"
                               oninput="nombresPreviewGrupos['${claveUnica}'] = this.value"
                               style="font-weight:700; font-size:0.82rem; color:#1E293B; border:1px dashed #94A3B8; border-radius:4px; padding:2px 6px; width:100%; background:rgba(255,255,255,0.85);">
                        <span style="flex-shrink:0; font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:999px; background:#DCFCE7; color:#166534;">Listo</span>
                    </div>
                    <div style="font-size:0.76rem; color:#475569;">${bTxt} ↔ ${eTxt}</div>
                `;
                contGrupos.appendChild(card);
            });
        }
    }

    // 2. Renderizar Registros Aislados con información completa y nombre editable
    const contAislados = document.getElementById('org_preview_aislados');
    const countAislados = document.getElementById('org_aislados_count');
    if (contAislados) {
        const idsConectadosBanco = new Set();
        const idsConectadosErp = new Set();
        state.conexionesTemp.forEach(([b, e]) => {
            idsConectadosBanco.add(Number(b));
            idsConectadosErp.add(Number(e));
        });

        const bancoAislados = Array.from(state.seleccionBanco).filter(id => !idsConectadosBanco.has(Number(id)));
        const erpAislados = Array.from(state.seleccionErp).filter(id => !idsConectadosErp.has(Number(id)));
        const totalAislados = bancoAislados.length + erpAislados.length;

        if (countAislados) countAislados.innerText = totalAislados;
        contAislados.innerHTML = '';

        if (totalAislados === 0) {
            contAislados.innerHTML = '<div style="font-size:0.8rem; color:#94A3B8; grid-column: 1 / -1; padding: 0.25rem 0;">No hay registros aislados disponibles.</div>';
        } else {
            bancoAislados.forEach(id => {
                const key = `banco_${id}`;
                const isChecked = !!aisladosChecks[key];
                const m = cacheBancoItems[id] || {};
                
                const fechaAr = formatearFechaAr(m.fecha_operacion || '');
                const signoStr = m.signo_monto === 'egreso' ? '-' : '+';
                const montoVal = m.monto !== undefined ? parseFloat(m.monto).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '0,00';
                const montoStr = `${signoStr}$${montoVal}`;
                const colorMonto = m.signo_monto === 'egreso' ? '#DC2626' : '#16A34A';
                const desc = expandirAbreviaturasDescripcion(m.descripcion_normalizada || m.descripcion_original || `Movimiento #${id}`);
                const cuitTxt = m.cuit_extraido ? `CUIT: ${m.cuit_extraido}` : '';

                const nombreDefecto = `Grupo Mov. #${id}`;
                const claveNombre = `nombre_banco_${id}`;
                const nombreActual = nombresPreviewGrupos[claveNombre] || nombreDefecto;

                const card = document.createElement('div');
                card.className = 'preview-group-card incomplete';
                card.innerHTML = `
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.4rem; margin-bottom:0.25rem;">
                        <label style="display:flex; align-items:center; gap:0.4rem; font-weight:600; font-size:0.82rem; color:#1E293B; cursor:pointer;">
                            <input type="checkbox" style="width:15px; height:15px; min-width:15px; cursor:pointer; margin:0; outline:none !important; box-shadow:none !important;" ${isChecked ? 'checked' : ''} onchange="aisladosChecks['${key}'] = this.checked">
                            <span style="font-size:0.75rem; color:#64748B;">Incluir</span>
                        </label>
                        <span style="font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:999px; background:#FEF3C7; color:#92400E;">Aislado</span>
                    </div>
                    <div style="margin-bottom:0.3rem;">
                        <input type="text"
                               value="${escapeHtml(nombreActual)}"
                               onchange="nombresPreviewGrupos['${claveNombre}'] = this.value"
                               oninput="nombresPreviewGrupos['${claveNombre}'] = this.value"
                               style="font-weight:700; font-size:0.82rem; color:#1E293B; border:1px dashed #94A3B8; border-radius:4px; padding:2px 6px; width:100%; background:rgba(255,255,255,0.85);">
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.78rem;">
                        <span><strong>#${id}</strong> ${fechaAr ? '· ' + fechaAr : ''} ${cuitTxt ? '· ' + escapeHtml(cuitTxt) : ''}</span>
                        <strong style="color:${colorMonto};">${montoStr}</strong>
                    </div>
                    <div style="font-size:0.75rem; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(desc)}">
                        ${escapeHtml(desc)}
                    </div>
                `;
                contAislados.appendChild(card);
            });

            erpAislados.forEach(id => {
                const key = `erp_${id}`;
                const isChecked = !!aisladosChecks[key];
                const c = cacheErpItems[id] || {};

                const fechaAr = formatearFechaAr(c.fecha || '');
                const montoVal = c.monto !== undefined ? parseFloat(c.monto).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '0,00';
                const montoStr = `$${montoVal}`;
                const cliente = c.cliente_nombre || `Rendición #${id}`;
                const medio = ETIQUETAS_MEDIO_ERP[c.medio_pago] || c.medio_pago || '';
                const cuitTxt = c.cliente_cuit ? `CUIT: ${c.cliente_cuit}` : '';

                const nombreDefecto = `Grupo Rend. #${id}`;
                const claveNombre = `nombre_erp_${id}`;
                const nombreActual = nombresPreviewGrupos[claveNombre] || nombreDefecto;

                const card = document.createElement('div');
                card.className = 'preview-group-card incomplete';
                card.innerHTML = `
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.4rem; margin-bottom:0.25rem;">
                        <label style="display:flex; align-items:center; gap:0.4rem; font-weight:600; font-size:0.82rem; color:#1E293B; cursor:pointer;">
                            <input type="checkbox" style="width:15px; height:15px; min-width:15px; cursor:pointer; margin:0; outline:none !important; box-shadow:none !important;" ${isChecked ? 'checked' : ''} onchange="aisladosChecks['${key}'] = this.checked">
                            <span style="font-size:0.75rem; color:#64748B;">Incluir</span>
                        </label>
                        <span style="font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:999px; background:#FEF3C7; color:#92400E;">Aislado</span>
                    </div>
                    <div style="margin-bottom:0.3rem;">
                        <input type="text"
                               value="${escapeHtml(nombreActual)}"
                               onchange="nombresPreviewGrupos['${claveNombre}'] = this.value"
                               oninput="nombresPreviewGrupos['${claveNombre}'] = this.value"
                               style="font-weight:700; font-size:0.82rem; color:#1E293B; border:1px dashed #94A3B8; border-radius:4px; padding:2px 6px; width:100%; background:rgba(255,255,255,0.85);">
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.78rem;">
                        <span><strong>#${id}</strong> ${fechaAr ? '· ' + fechaAr : ''} ${cuitTxt ? '· ' + escapeHtml(cuitTxt) : ''}</span>
                        <strong style="color:#1E293B;">${montoStr}</strong>
                    </div>
                    <div style="font-size:0.75rem; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(cliente)}">
                        ${escapeHtml(cliente)} ${medio ? '· ' + escapeHtml(medio) : ''}
                    </div>
                `;
                contAislados.appendChild(card);
            });
        }
    }
}

function guardarDistribucionGrupos() {
    const gruposVinculados = calcularGruposVinculados();
    const baseNum = state.gruposManuales.length + 1;

    let candidatosNuevos = [];

    // 1. Grupos vinculados guardando la info completa de cada ítem
    gruposVinculados.forEach((g, idx) => {
        const claveUnica = `B:${g.bancoIds.join(',')}|E:${g.erpIds.join(',')}`;
        const nombreFinal = (nombresPreviewGrupos[claveUnica] || `Grupo ${baseNum + idx}`).trim() || `Grupo ${baseNum + idx}`;

        candidatosNuevos.push({
            nombre: nombreFinal,
            bancoIds: g.bancoIds,
            erpIds: g.erpIds,
            checked: true,
            bancoItems: g.bancoIds.map(id => {
                const m = cacheBancoItems[id] || {};
                return {
                    id_movimiento_bancario: id,
                    fecha_operacion: m.fecha_operacion || '',
                    signo_monto: m.signo_monto || 'ingreso',
                    monto: m.monto !== undefined ? m.monto : 0,
                    descripcion_original: m.descripcion_original || m.descripcion_normalizada || `Movimiento #${id}`,
                    cuit_extraido: m.cuit_extraido || ''
                };
            }),
            erpItems: g.erpIds.map(id => {
                const c = cacheErpItems[id] || {};
                return {
                    id_rendicion_cobro: id,
                    fecha: c.fecha || '',
                    monto: c.monto !== undefined ? c.monto : 0,
                    cliente_nombre: c.cliente_nombre || `Rendición #${id}`,
                    medio_pago: c.medio_pago || '',
                    cliente_cuit: c.cliente_cuit || ''
                };
            })
        });
    });

    // 2. Registros aislados tildados guardando la info completa de cada ítem
    let counterAislados = 1;
    Object.keys(aisladosChecks).forEach(key => {
        if (aisladosChecks[key]) {
            const [tipo, idStr] = key.split('_');
            const id = Number(idStr);
            const claveNombre = `nombre_${tipo}_${id}`;

            if (tipo === 'banco') {
                const nombrePersonalizado = (nombresPreviewGrupos[claveNombre] || `Grupo Mov. #${id}`).trim() || `Grupo Mov. #${id}`;
                const m = cacheBancoItems[id] || {};
                candidatosNuevos.push({
                    nombre: nombrePersonalizado,
                    bancoIds: [id],
                    erpIds: [],
                    checked: false,
                    bancoItems: [{
                        id_movimiento_bancario: id,
                        fecha_operacion: m.fecha_operacion || '',
                        signo_monto: m.signo_monto || 'ingreso',
                        monto: m.monto !== undefined ? m.monto : 0,
                        descripcion_original: m.descripcion_original || m.descripcion_normalizada || `Movimiento #${id}`,
                        cuit_extraido: m.cuit_extraido || ''
                    }],
                    erpItems: []
                });
            } else if (tipo === 'erp') {
                const nombrePersonalizado = (nombresPreviewGrupos[claveNombre] || `Grupo Rend. #${id}`).trim() || `Grupo Rend. #${id}`;
                const c = cacheErpItems[id] || {};
                candidatosNuevos.push({
                    nombre: nombrePersonalizado,
                    bancoIds: [],
                    erpIds: [id],
                    checked: false,
                    bancoItems: [],
                    erpItems: [{
                        id_rendicion_cobro: id,
                        fecha: c.fecha || '',
                        monto: c.monto !== undefined ? c.monto : 0,
                        cliente_nombre: c.cliente_nombre || `Rendición #${id}`,
                        medio_pago: c.medio_pago || '',
                        cliente_cuit: c.cliente_cuit || ''
                    }]
                });
            }
        }
    });

    if (candidatosNuevos.length === 0) {
        document.getElementById('modal_organizar').classList.remove('active');
        return;
    }

    // Validación de duplicados (Caso B)
    const idsBancoExistentes = new Set();
    const idsErpExistentes = new Set();
    state.gruposManuales.forEach(g => {
        if (Array.isArray(g.bancoIds)) g.bancoIds.forEach(id => idsBancoExistentes.add(Number(id)));
        if (Array.isArray(g.erpIds)) g.erpIds.forEach(id => idsErpExistentes.add(Number(id)));
    });

    let duplicadosDetectados = [];
    candidatosNuevos.forEach(c => {
        c.bancoIds.forEach(id => {
            if (idsBancoExistentes.has(Number(id))) duplicadosDetectados.push(`Movimiento bancario #${id}`);
        });
        c.erpIds.forEach(id => {
            if (idsErpExistentes.has(Number(id))) duplicadosDetectados.push(`Rendición ERP #${id}`);
        });
    });

    if (duplicadosDetectados.length > 0) {
        const unicos = [...new Set(duplicadosDetectados)];
        mostrarConfirmacionSistema({
            icono: '⚠️',
            titulo: 'Registros ya cargados',
            mensaje: `Los siguientes registros ya forman parte de otro grupo existente en la conciliación manual: ${unicos.join(', ')}. Desmarcalos o eliminalos del grupo anterior para poder continuar.`,
            botones: [{ texto: 'Entendido', clase: 'btn-primary' }]
        });
        return;
    }

    candidatosNuevos.forEach((c, idx) => {
        state.gruposManuales.push({
            id: Date.now() + idx,
            ...c
        });
    });

    document.getElementById('modal_organizar').classList.remove('active');
    quitarSeleccion();
    guardarEstadoEnSession();
    renderGruposManuales();
}

// Función auxiliar para reparar automáticamente grupos antiguos guardados sin respaldo al iniciar
function repararInformacionGruposGuardados() {
    if (!Array.isArray(state.gruposManuales)) return;
    state.gruposManuales.forEach(g => {
        if (!g.bancoItemsInfo || g.bancoItemsInfo.length === 0) {
            g.bancoItemsInfo = (g.bancoIds || []).map(id => cacheBancoItems[id] || { id_movimiento_bancario: id, monto: 0, descripcion_original: `Movimiento #${id}` });
        }
        if (!g.erpItemsInfo || g.erpItemsInfo.length === 0) {
            g.erpItemsInfo = (g.erpIds || []).map(id => cacheErpItems[id] || { id_rendicion_cobro: id, monto: 0, cliente_nombre: `Rendición #${id}` });
        }
    });
}
/**
 * IMPORTACIÓN DE EXTRACTOS MÚLTIPLES (DRAG & DROP + MENSAJES AMIGABLES)
 */
function abrirModalExtracto() {
    document.getElementById('import_resultado').style.display = 'none';
    renderListaArchivos();
    document.getElementById('modal_extracto').classList.add('active');
}

function intentarCerrarModalExtracto() {
    if (archivosEnEspera.length > 0) {
        mostrarConfirmacionSistema({
            icono: '📥',
            titulo: 'Tenés extractos sin subir',
            mensaje: `Hay ${archivosEnEspera.length} archivo(s) preparado(s) en la lista. Si salís ahora, podés conservarlos enlistados para subirlos más tarde.`,
            botones: [
                { texto: 'Seguir acá', clase: 'btn-secondary' },
                {
                    texto: 'Descartar lista y salir',
                    clase: 'btn-secondary',
                    onClick: () => {
                        limpiarListaArchivos();
                        cerrarModalExtracto();
                    }
                },
                {
                    texto: 'Salir y mantener lista',
                    clase: 'btn-primary',
                    onClick: () => cerrarModalExtracto()
                }
            ]
        });
        return;
    }
    cerrarModalExtracto();
}

function cerrarModalExtracto() {
    document.getElementById('modal_extracto').classList.remove('active');
}

function agregarArchivosALista(fileList) {
    if (!fileList || fileList.length === 0) return;
    const extensionesPermitidas = ['csv', 'txt', 'xls', 'xlsx'];

    for (let i = 0; i < fileList.length; i++) {
        const archivo = fileList[i];
        const ext = (archivo.name.split('.').pop() || '').toLowerCase();
        if (!extensionesPermitidas.includes(ext)) {
            alert(`El archivo "${archivo.name}" no tiene un formato válido. Solo se permiten archivos CSV, TXT, XLS o XLSX.`);
            continue;
        }
        const yaEnLista = archivosEnEspera.some(f => f.name === archivo.name && f.size === archivo.size);
        if (!yaEnLista) {
            archivosEnEspera.push(archivo);
        }
    }
    renderListaArchivos();
}

function archivosSeleccionados(input) {
    agregarArchivosALista(input.files);
    input.value = '';
}

function inicializarDragAndDropExtractos() {
    // Evitar que el navegador descargue o abra archivos arrastrados sobre la ventana
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evento => {
        window.addEventListener(evento, e => {
            e.preventDefault();
        }, false);
    });

    const dropzone = document.getElementById('dropzone_extracto');
    if (!dropzone) return;

    ['dragenter', 'dragover'].forEach(evento => {
        dropzone.addEventListener(evento, e => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.style.borderColor = '#2563EB';
            dropzone.style.background = '#EFF6FF';
        });
    });

    ['dragleave', 'drop'].forEach(evento => {
        dropzone.addEventListener(evento, e => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.style.borderColor = '#93C5FD';
            dropzone.style.background = '#F8FAFC';
        });
    });

    dropzone.addEventListener('drop', e => {
        const files = e.dataTransfer ? e.dataTransfer.files : null;
        agregarArchivosALista(files);
    });
}

function renderListaArchivos() {
    const ul = document.getElementById('lista_archivos_ul');
    const container = document.getElementById('lista_archivos_container');
    const contador = document.getElementById('contador_archivos_espera');
    const btnSubmit = document.getElementById('btn_submit_extracto');
    ul.innerHTML = '';

    if (archivosEnEspera.length === 0) {
        container.style.display = 'none';
        btnSubmit.disabled = true;
        return;
    }

    container.style.display = 'block';
    btnSubmit.disabled = false;
    if (contador) {
        contador.innerText = `${archivosEnEspera.length} archivo(s) listo(s) para subir`;
    }

    archivosEnEspera.forEach((f, idx) => {
        const li = document.createElement('li');
        li.style.display = 'flex';
        li.style.justifyContent = 'space-between';
        li.style.alignItems = 'center';
        li.style.padding = '5px 4px';
        li.style.borderBottom = '1px solid #F1F5F9';
        li.innerHTML = `
            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-right:8px;">
                📄 <strong>${escapeHtml(f.name)}</strong> <span style="color:#64748B;">(${(f.size / 1024).toFixed(1)} KB)</span>
            </span>
            <button type="button" onclick="eliminarArchivoEspera(${idx})" title="Quitar de la lista" style="background:none; border:none; color:#64748B; font-weight:700; cursor:pointer; padding:2px 6px;">✕</button>
        `;
        ul.appendChild(li);
    });
}

function eliminarArchivoEspera(idx) {
    archivosEnEspera.splice(idx, 1);
    renderListaArchivos();
}

function limpiarListaArchivos() {
    archivosEnEspera = [];
    renderListaArchivos();
}

function interpretarErrorImportacionAmigable(mensajeTecnico, statusHttp) {
    const msg = String(mensajeTecnico || '');

    if (msg.includes('ya fue importado anteriormente') || msg.includes('SHA-256')) {
        return 'Este archivo ya fue subido anteriormente al sistema (extracto duplicado).';
    }
    if (statusHttp === 422 || msg.includes('The archivo field')) {
        return 'El formato o el tamaño del archivo no es válido. Verificá que sea CSV, TXT, XLS o XLSX.';
    }
    if (statusHttp === 419) {
        return 'Tu sesión expiró. Recargá la página e intentá subir el archivo nuevamente.';
    }
    if (msg.includes('Unexpected token') || statusHttp >= 500) {
        return 'No se pudo leer la estructura interna del archivo. Puede que esté dañado o tenga un formato de columnas distinto al esperado.';
    }
    if (msg.toLowerCase().includes('vacío') || msg.toLowerCase().includes('sin filas')) {
        return 'El archivo no contiene movimientos válidos para importar.';
    }
    return 'No se pudo procesar este archivo. Revisá que sea un extracto bancario válido.';
}

function obtenerBadgeTipoArchivo(nombreArchivo) {
    const ext = (nombreArchivo.split('.').pop() || '').toLowerCase();
    if (ext === 'xls' || ext === 'xlsx' || ext === 'csv') {
        return `<span style="flex-shrink:0; font-size:0.72rem; font-weight:700; padding:2px 8px; border-radius:999px; background:#E0F2FE; color:#0369A1; border:1px solid #BAE6FD;">📊 EXCEL (.${ext})</span>`;
    }
    return `<span style="flex-shrink:0; font-size:0.72rem; font-weight:700; padding:2px 8px; border-radius:999px; background:#F1F5F9; color:#334155; border:1px solid #CBD5E1;">📝 TEXTO (.${ext})</span>`;
}

async function enviarExtractosMultiples(event) {
    event.preventDefault();
    if (archivosEnEspera.length === 0) return;

    const spinner = document.getElementById('import_spinner');
    const resDiv = document.getElementById('import_resultado');
    const btnSubmit = document.getElementById('btn_submit_extracto');

    spinner.style.display = 'block';
    resDiv.style.display = 'none';
    btnSubmit.disabled = true;

    let exitososConNuevos = 0;
    let resumenHtml = '<div style="display:flex; flex-direction:column; gap:0.55rem;">';

    for (let idx = 0; idx < archivosEnEspera.length; idx++) {
        const f = archivosEnEspera[idx];
        const numeroItem = idx + 1;
        const badgeTipo = obtenerBadgeTipoArchivo(f.name);
        const formData = new FormData();
        formData.append('archivo', f);

        try {
            const res = await fetch('/api/extractos/importar', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            });

            const rawText = await res.text();
            let data = null;
            try {
                data = JSON.parse(rawText);
            } catch (_) {
                data = null;
            }

            if (res.ok && data && data.success) {
                const insertados = Number(data.filas_insertadas || 0);
                const omitidos = Number(data.filas_omitidas || 0);

                if (insertados > 0) {
                    exitososConNuevos++;
                    resumenHtml += `
                        <div style="background:#ECFDF5; border:1px solid #A7F3D0; border-radius:8px; padding:0.65rem 0.75rem; color:#065F46;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem;">
                                <div style="font-size:0.84rem;">
                                    <strong>${numeroItem}. ${escapeHtml(f.name)}</strong>
                                </div>
                                ${badgeTipo}
                            </div>
                            <div style="margin-top:0.3rem; font-size:0.81rem;">
                                ✅ Se cargaron <strong>${insertados}</strong> movimiento(s) nuevo(s)${omitidos > 0 ? ` y se omitieron <strong>${omitidos}</strong> ya existentes` : ''}.
                            </div>
                        </div>`;
                } else {
                    // Todos los movimientos ya estaban cargados previamente -> tarjeta gris neutra
                    resumenHtml += `
                        <div style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:8px; padding:0.65rem 0.75rem; color:#334155;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem;">
                                <div style="font-size:0.84rem;">
                                    <strong>${numeroItem}. ${escapeHtml(f.name)}</strong>
                                </div>
                                ${badgeTipo}
                            </div>
                            <div style="margin-top:0.3rem; font-size:0.81rem; color:#475569;">
                                ℹ️ No se agregaron movimientos nuevos: los <strong>${omitidos}</strong> registro(s) de este extracto ya estaban cargados en el sistema.
                            </div>
                        </div>`;
                }
            } else {
                const detalleBackend = (data && (data.mensaje || data.message))
                    ? (data.mensaje || data.message)
                    : rawText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220);

                const esDuplicadoArchivo = detalleBackend.includes('ya fue importado anteriormente') || detalleBackend.includes('SHA-256');
                const explicacionAmigable = interpretarErrorImportacionAmigable(detalleBackend, res.status);

                if (esDuplicadoArchivo) {
                    // Archivo duplicado por SHA-256 -> tarjeta gris neutra
                    resumenHtml += `
                        <div style="background:#F8FAFC; border:1px solid #CBD5E1; border-radius:8px; padding:0.65rem 0.75rem; color:#334155;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem;">
                                <div style="font-size:0.84rem;">
                                    <strong>${numeroItem}. ${escapeHtml(f.name)}</strong>
                                </div>
                                ${badgeTipo}
                            </div>
                            <div style="margin-top:0.3rem; font-size:0.81rem; color:#475569;">
                                ℹ️ ${explicacionAmigable}
                            </div>
                            <details style="margin-top:4px; font-size:0.74rem; color:#64748B; cursor:pointer;">
                                <summary>Ver detalle técnico</summary>
                                <div style="margin-top:2px; font-family:monospace; word-break:break-word;">${escapeHtml(detalleBackend)}</div>
                            </details>
                        </div>`;
                } else {
                    // Error real de formato/procesamiento -> tarjeta roja
                    resumenHtml += `
                        <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:8px; padding:0.65rem 0.75rem; color:#991B1B;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem;">
                                <div style="font-size:0.84rem;">
                                    <strong>${numeroItem}. ${escapeHtml(f.name)}</strong>
                                </div>
                                ${badgeTipo}
                            </div>
                            <div style="margin-top:0.3rem; font-size:0.81rem;">
                                ⚠️ ${explicacionAmigable}
                            </div>
                            <details style="margin-top:4px; font-size:0.74rem; color:#7F1D1D; cursor:pointer;">
                                <summary>Ver detalle técnico</summary>
                                <div style="margin-top:2px; font-family:monospace; word-break:break-word;">${escapeHtml(detalleBackend || `Error HTTP ${res.status}`)}</div>
                            </details>
                        </div>`;
                }
            }
        } catch (e) {
            resumenHtml += `
                <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:8px; padding:0.65rem 0.75rem; color:#991B1B;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem;">
                        <div style="font-size:0.84rem;"><strong>${numeroItem}. ${escapeHtml(f.name)}</strong></div>
                        ${badgeTipo}
                    </div>
                    <div style="margin-top:0.3rem; font-size:0.81rem;">⚠️ No hubo conexión con el servidor al intentar subir el archivo.</div>
                </div>`;
        }
    }

    resumenHtml += '</div>';

    spinner.style.display = 'none';
    resDiv.style.display = 'flex';
    resDiv.style.flexDirection = 'column';
    resDiv.style.background = 'transparent';
    resDiv.style.border = 'none';
    resDiv.style.padding = '0';

    const hayFiltros = (state.banco.estados.length > 0 || state.banco.tipos.length > 0 || state.banco.fecha_desde || state.banco.fecha_hasta || state.banco.monto_min !== '' || state.banco.monto_max !== '' || state.banco.signo || state.banco.cuit || state.banco.comprobante || state.banco.q);
    if (hayFiltros && exitososConNuevos > 0) {
        resumenHtml += '<p style="margin:0.55rem 0 0 0; padding:0.5rem 0.65rem; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:6px; font-size:0.78rem; color:#1E40AF;">ℹ️ Tenés filtros activos en el listado; algunos movimientos recién importados pueden quedar ocultos hasta limpiar los filtros.</p>';
    }

    resDiv.innerHTML = resumenHtml;
    archivosEnEspera = [];
    renderListaArchivos();
    state.banco.page = 1;
    cargarMovimientosBancarios();
}

/**
 * CONFIGURACIÓN Y CAMBIOS SIN GUARDAR (APARIENCIA Y VISUALIZACIÓN - PALETA 10 PASTEL)
 */
const PALETA_PASTEL_10 = [
    { hex: '#FFFFFF', nombre: 'Blanco puro' },
    { hex: '#F8FAFC', nombre: 'Gris perla' },
    { hex: '#EFF6FF', nombre: 'Celeste hielo' },
    { hex: '#ECFEFF', nombre: 'Turquesa pálido' },
    { hex: '#ECFDF5', nombre: 'Verde menta' },
    { hex: '#F7FEE7', nombre: 'Lima muy claro' },
    { hex: '#FEFCE8', nombre: 'Amarillo crema' },
    { hex: '#FFF7ED', nombre: 'Durazno suave' },
    { hex: '#FEF2F2', nombre: 'Rosa claro' },
    { hex: '#F5F3FF', nombre: 'Lavanda suave' }
];

const DEFAULT_COLORES_ERP = {
    aprobada: '#ECFDF5',
    pendiente: '#FFFFFF',
    rechazada: '#FEF2F2'
};

let borradorColoresBanco = {};
let borradorColoresErp = {};

function aplicarVariablesCssColores() {
    CONFIG_ESTADOS.forEach(est => {
        const val = (state.colores && state.colores[est]) ? state.colores[est] : DEFAULT_COLORES[est];
        document.documentElement.style.setProperty(`--color-estado-${est}`, val);
    });
    TODOS_ESTADOS_ERP.forEach(est => {
        const val = (state.coloresErp && state.coloresErp[est]) ? state.coloresErp[est] : DEFAULT_COLORES_ERP[est];
        document.documentElement.style.setProperty(`--color-erp-${est}`, val);
    });
}

function renderizarSwatchesPaleta(grupo, estado, etiqueta, colorActivo) {
    const botones = PALETA_PASTEL_10.map(c => {
        const isSelected = (colorActivo || '').toUpperCase() === c.hex.toUpperCase();
        return `<button type="button" class="color-swatch ${isSelected ? 'active' : ''}" style="background-color:${c.hex};" title="${c.nombre}" onclick="seleccionarSwatchColor('${grupo}', '${estado}', '${c.hex}')"></button>`;
    }).join('');

    return `
        <div class="config-color-row">
            <span class="config-color-label">${etiqueta}</span>
            <div class="palette-swatches">${botones}</div>
        </div>
    `;
}

function renderizarModalPaletas() {
    const contBanco = document.getElementById('lista_config_colores_banco');
    const contErp = document.getElementById('lista_config_colores_erp');

    if (contBanco) {
        contBanco.innerHTML = CONFIG_ESTADOS.map(est =>
            renderizarSwatchesPaleta('banco', est, ETIQUETAS_ESTADO[est] || est, borradorColoresBanco[est])
        ).join('');
    }
    if (contErp) {
        contErp.innerHTML = TODOS_ESTADOS_ERP.map(est =>
            renderizarSwatchesPaleta('erp', est, ETIQUETAS_ESTADO_RENDICION[est] || est, borradorColoresErp[est])
        ).join('');
    }
}

function seleccionarSwatchColor(grupo, estado, hex) {
    if (grupo === 'banco') borradorColoresBanco[estado] = hex;
    else borradorColoresErp[estado] = hex;
    marcarConfigModificada();
    renderizarModalPaletas();
}

function abrirConfiguracion() {
    state.configModificada = false;
    borradorColoresBanco = { ...DEFAULT_COLORES, ...(state.colores || {}) };
    borradorColoresErp = { ...DEFAULT_COLORES_ERP, ...(state.coloresErp || {}) };
    renderizarModalPaletas();
    document.getElementById('modal_config').classList.add('active');
}

function marcarConfigModificada() { state.configModificada = true; }

function restaurarTodosPredeterminados() {
    borradorColoresBanco = { ...DEFAULT_COLORES };
    borradorColoresErp = { ...DEFAULT_COLORES_ERP };
    marcarConfigModificada();
    renderizarModalPaletas();
}

function intentarCerrarConfiguracion() {
    if (state.configModificada) {
        mostrarConfirmacionSistema({
            icono: '🎨',
            titulo: 'Cambios de colores sin guardar',
            mensaje: 'Modificaste la paleta de estados. ¿Querés guardar los cambios antes de salir?',
            botones: [
                { texto: 'Seguir editando', clase: 'btn-secondary' },
                { texto: 'Descartar cambios', clase: 'btn-secondary', onClick: () => cancelarConfiguracion() },
                { texto: 'Guardar cambios', clase: 'btn-primary', onClick: () => guardarConfiguracion() }
            ]
        });
        return;
    }
    cancelarConfiguracion();
}

function cancelarConfiguracion() {
    document.getElementById('modal_config').classList.remove('active');
}

function guardarConfiguracion() {
    state.colores = { ...borradorColoresBanco };
    state.coloresErp = { ...borradorColoresErp };
    aplicarVariablesCssColores();
    cancelarConfiguracion();
    guardarEstadoEnSession();
    cargarMovimientosBancarios();
    cargarCobranzasErp();
}

function toggleMaximizar(boxId) {
    document.getElementById(boxId).classList.toggle('maximized');
}

/**
 * INICIALIZACIÓN
 */
document.addEventListener('DOMContentLoaded', () => {
    recuperarEstadoSession();
    inicializarDragAndDropExtractos();

    // Sincronizar inputs básicos banco
    document.getElementById('banco_fecha_desde').value = state.banco.fecha_desde || '';
    document.getElementById('banco_fecha_hasta').value = state.banco.fecha_hasta || '';
    document.querySelectorAll('.chk-banco-estado').forEach(chk => {
        chk.checked = state.banco.estados.includes(chk.value);
    });
    document.querySelectorAll('.chk-banco-tipo').forEach(chk => {
        chk.checked = state.banco.tipos.includes(chk.value);
    });
    actualizarEtiquetasMultiselect();

    // Sincronizar inputs avanzados banco
    document.getElementById('banco_monto_min').value = state.banco.monto_min ?? '';
    document.getElementById('banco_monto_max').value = state.banco.monto_max ?? '';
    sincronizarBotonesSigno();
    document.getElementById('banco_cuit').value = state.banco.cuit || '';
    document.getElementById('banco_comprobante').value = state.banco.comprobante || '';
    document.getElementById('banco_q').value = state.banco.q || '';
    document.getElementById('banco_ids_movimiento').value = state.banco.ids_movimiento || '';

    // Expandir automáticamente si hay algún filtro avanzado activo
    const hayAvanzadoBanco = (state.banco.monto_min !== '' || state.banco.monto_max !== '' || state.banco.signo || state.banco.cuit || state.banco.comprobante || state.banco.q || state.banco.ids_movimiento);
    if (hayAvanzadoBanco) {
        document.getElementById('adv_banco').classList.add('expanded');
        document.getElementById('btn_toggle_adv_banco').innerHTML = '▲ Menos filtros';
    }

    // Sincronizar inputs ERP / Rendiciones
    document.getElementById('erp_fecha_desde').value = state.erp.fecha_desde || '';
    document.getElementById('erp_fecha_hasta').value = state.erp.fecha_hasta || '';
    document.querySelectorAll('.chk-erp-estado').forEach(chk => {
        chk.checked = state.erp.estados.includes(chk.value);
    });
    document.querySelectorAll('.chk-erp-medio').forEach(chk => {
        chk.checked = state.erp.medios.includes(chk.value);
    });
    actualizarEtiquetasMultiselectErp();
    document.getElementById('erp_monto_min').value = state.erp.monto_min ?? '';
    document.getElementById('erp_monto_max').value = state.erp.monto_max ?? '';
    document.getElementById('erp_cliente_q').value = state.erp.cliente_q || '';
    document.getElementById('erp_ids_cobro').value = state.erp.ids_cobro || '';

    const hayAvanzadoErp = (state.erp.monto_min !== '' || state.erp.monto_max !== '' || state.erp.cliente_q || state.erp.ids_cobro);
    if (hayAvanzadoErp) {
        document.getElementById('adv_erp').classList.add('expanded');
        document.getElementById('btn_toggle_adv_erp').innerHTML = '▲ Menos filtros';
    }

    aplicarVariablesCssColores();

    actualizarChips();
    actualizarBarraContextual();
    cargarMovimientosBancarios();
    cargarCobranzasErp();
    renderGruposManuales();
});
window.abrirModalOrganizar = abrirModalOrganizar;
window.quitarSeleccion = quitarSeleccion;
window.deshacerConexion = deshacerConexion;
window.rehacerConexion = rehacerConexion;
window.limpiarConexiones = limpiarConexiones;
window.guardarDistribucionGrupos = guardarDistribucionGrupos;
window.cancelarOrganizar = cancelarOrganizar;
window.intentarCerrarOrganizar = intentarCerrarOrganizar;
</script>
@endpush