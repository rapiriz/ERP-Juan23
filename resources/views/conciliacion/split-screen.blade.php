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
    .per-page-selector {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.4rem;
        font-size: 0.78rem;
        color: #475569;
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

/* Modales */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: #FFFFFF;
    border-radius: 12px;
    max-width: 650px;
    width: 90%;
    padding: 1.5rem;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25);
    max-height: 90vh;
    overflow-y: auto;
    transition: all 0.2s ease;
}
.modal-box.maximized {
    max-width: 96vw;
    width: 96vw;
    height: 94vh;
    max-height: 94vh;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    border-bottom: 1px solid #E2E8F0;
    padding-bottom: 0.75rem;
}
.modal-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.icon-btn {
    background: none;
    border: none;
    font-size: 1.15rem;
    cursor: pointer;
    color: #64748B;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
}
.icon-btn:hover { background: #F1F5F9; color: #0F172A; }

/* Canvas y Conexiones en Ventana Organizar */
.organizar-container {
    display: grid;
    grid-template-columns: 1fr 140px 1fr;
    gap: 1rem;
    min-height: 400px;
    position: relative;
}
.organizar-col {
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 0.75rem;
    background: #F8FAFC;
    overflow-y: auto;
    max-height: 60vh;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.organizar-item {
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    padding: 0.6rem;
    font-size: 0.85rem;
    cursor: pointer;
    user-select: none;
    position: relative;
    transition: all 0.15s;
}
.organizar-item:hover { border-color: #2563EB; box-shadow: 0 2px 4px rgba(37,99,235,0.1); }
.organizar-item.active-connect { border-color: #2563EB; background: #EFF6FF; }
.connections-canvas-col {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}
#connectionsSvg {
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0; left: 0;
    pointer-events: all;
}
.conn-line {
    stroke: #2563EB;
    stroke-width: 2.5;
    cursor: pointer;
    transition: stroke 0.15s, stroke-width 0.15s;
}
.conn-line:hover { stroke: #DC2626; stroke-width: 4; }

/* Zona de subida */
.drop-zone {
    border: 2px dashed #94A3B8;
    border-radius: 8px;
    padding: 1.75rem;
    text-align: center;
    background: #F8FAFC;
    cursor: pointer;
    margin-bottom: 1rem;
}
.drop-zone:hover { border-color: #2563EB; background: #EFF6FF; }
</style>
@endpush

@section('contenido')
<div class="conciliacion-container">
    {{-- 1. Encabezado y acciones generales --}}
    <div class="conciliacion-header">
        <div>
            <h1>Conciliación Bancaria</h1>
            <p style="color:#64748B; font-size: 0.95rem;">
                Módulo G2: Importación inmutable de extractos, cruce con Rendiciones y gestión visual de grupos.
            </p>
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
    
    {{-- Aviso informativo para organizar selecciones --}}
    <div class="selection-notice-banner">
        <div class="selection-notice-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
        </div>
        <div class="selection-notice-text">
            <strong>Organización de selecciones:</strong> Seleccioná registros con las casillas de verificación en las tablas de <em>Movimientos bancarios</em> y <em>Cobranzas ERP</em> para habilitar la organización de selecciones y agrupar conciliaciones (1 a 1, 1 a N, N a 1 o N a N).
        </div>
    </div>

    {{-- 5. Barra de Acciones Contextual --}}
    <div id="contextual_bar" class="contextual-bar" style="display:none;">
        <span id="contextual_selection_text">0 movimientos y 0 cobranzas seleccionados</span>
        <div style="display:flex; gap:0.75rem;">
            <button type="button" class="btn-action btn-primary" onclick="abrirModalOrganizar()">Organizar selección</button>
            <button type="button" class="btn-action btn-secondary" onclick="quitarSeleccion()">Quitar selección</button>
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

{{-- MODAL: Organizar Selección (Vinculación visual por líneas) --}}
<div id="modal_organizar" class="modal-overlay">
    <div class="modal-box" id="box_organizar" style="max-width:950px;">
        <div class="modal-header">
            <h3 style="font-weight:700;">Organizar selección</h3>
            <div class="modal-header-actions">
                <button type="button" class="icon-btn" onclick="toggleMaximizar('box_organizar')" title="Maximizar / Restaurar">⤢</button>
                <button type="button" class="icon-btn" onclick="intentarCerrarOrganizar()" title="Cerrar">✕</button>
            </div>
        </div>
        <p style="font-size:0.85rem; color:#64748B; margin-bottom:0.75rem;">
            Arrastrá o hacé clic en un registro de la izquierda y luego en uno de la derecha para conectarlos.
        </p>

        <div class="organizar-container">
            <div class="organizar-col" id="org_col_banco"></div>
            <div class="connections-canvas-col">
                <svg id="connectionsSvg"></svg>
            </div>
            <div class="organizar-col" id="org_col_erp"></div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.25rem;">
            <div style="display:flex; gap:0.5rem;">
                <button type="button" class="btn-action btn-secondary" onclick="deshacerConexion()">Deshacer</button>
                <button type="button" class="btn-action btn-secondary" onclick="limpiarConexiones()">Limpiar conexiones</button>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <button type="button" class="btn-action btn-secondary" onclick="cancelarOrganizar()">Cancelar</button>
                <button type="button" class="btn-action btn-primary" onclick="guardarDistribucionGrupos()">Guardar distribución</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL: Subir Extracto Bancario --}}
<div id="modal_extracto" class="modal-overlay">
    <div class="modal-box" id="box_extracto">
        <div class="modal-header">
            <h3 style="font-weight:700;">Subir extracto bancario</h3>
            <div class="modal-header-actions">
                <button type="button" class="icon-btn" onclick="toggleMaximizar('box_extracto')" title="Maximizar / Restaurar">⤢</button>
                <button type="button" class="icon-btn" onclick="cerrarModalExtracto()" title="Cerrar">✕</button>
            </div>
        </div>

        <form id="form_subir_extracto" onsubmit="enviarExtractosMultiples(event)">
            @csrf
            <div class="drop-zone" onclick="document.getElementById('input_archivo_extracto').click()">
                <div style="margin-bottom:0.5rem; color:#64748B;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                </div>
                <div style="font-weight:600; color:#1E293B;">Arrastrá o buscá tus archivos (.xls o .txt)</div>
                <div style="font-size:0.8rem; color:#64748B; margin-top:0.25rem;">Admite selección múltiple</div>
                <input type="file" id="input_archivo_extracto" name="archivos[]" accept=".xls,.txt" multiple style="display:none;" onchange="archivosSeleccionados(this)">
            </div>

            <div id="lista_archivos_container" style="margin-bottom:1rem; display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                    <span style="font-weight:600; font-size:0.85rem; color:#334155;">Archivos seleccionados:</span>
                    <button type="button" onclick="limpiarListaArchivos()" style="background:none; border:none; color:#DC2626; font-size:0.8rem; cursor:pointer;">Limpiar lista</button>
                </div>
                <ul id="lista_archivos_ul" style="list-style:none; padding:0; margin:0; font-size:0.85rem; max-height:120px; overflow-y:auto;"></ul>
            </div>

            <div id="import_spinner" style="display:none; text-align:center; padding:1rem; font-weight:600; color:#2563EB;">
                Procesando extracto bancario...
            </div>

            <div id="import_resultado" style="display:none; padding:1rem; border-radius:8px; margin-bottom:1rem; font-size:0.9rem;"></div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1rem;">
                <button type="button" class="btn-action btn-secondary" onclick="cerrarModalExtracto()">Cerrar</button>
                <button type="submit" id="btn_submit_extracto" class="btn-action btn-primary" disabled>Subir</button>
            </div>
        </form>
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
let connectingBancoId = null;

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
            seleccionBanco: new Set(parsed.seleccionBanco || []),
            seleccionErp: new Set(parsed.seleccionErp || []),
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
            return;
        }

        document.getElementById('banco_total_badge').innerText = `${json.total} registros`;
        actualizarFooterPaginacion('banco', json.last_page, json.current_page, json.per_page, json.total);

        json.data.forEach(m => {
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
            return;
        }

        document.getElementById('erp_total_badge').innerText = `${json.total} registros`;
        actualizarFooterPaginacion('erp', json.last_page, json.current_page, json.per_page, json.total);

        json.data.forEach(c => {
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
function toggleSelectBanco(id, chk) {
    if (chk.checked) state.seleccionBanco.add(id);
    else state.seleccionBanco.delete(id);
    chk.closest('tr').classList.toggle('selected-row', chk.checked);
    actualizarBarraContextual();
    guardarEstadoEnSession();
}

function toggleSelectErp(id, chk) {
    if (chk.checked) state.seleccionErp.add(id);
    else state.seleccionErp.delete(id);
    chk.closest('tr').classList.toggle('selected-row', chk.checked);
    actualizarBarraContextual();
    guardarEstadoEnSession();
}

function toggleSelectAllBanco(master) {
    document.querySelectorAll('#tbody_banco input[type="checkbox"]').forEach(chk => {
        chk.checked = master.checked;
        const id = parseInt(chk.getAttribute('onchange').match(/\d+/)[0]);
        if (master.checked) state.seleccionBanco.add(id);
        else state.seleccionBanco.delete(id);
        chk.closest('tr').classList.toggle('selected-row', master.checked);
    });
    actualizarBarraContextual();
    guardarEstadoEnSession();
}

function toggleSelectAllErp(master) {
    document.querySelectorAll('#tbody_erp input[type="checkbox"]').forEach(chk => {
        chk.checked = master.checked;
        const id = parseInt(chk.getAttribute('onchange').match(/\d+/)[0]);
        if (master.checked) state.seleccionErp.add(id);
        else state.seleccionErp.delete(id);
        chk.closest('tr').classList.toggle('selected-row', master.checked);
    });
    actualizarBarraContextual();
    guardarEstadoEnSession();
}

function actualizarBarraContextual() {
    const bar = document.getElementById('contextual_bar');
    const bCount = state.seleccionBanco.size;
    const eCount = state.seleccionErp.size;

    if (bCount > 0 || eCount > 0) {
        bar.style.display = 'flex';
        document.getElementById('contextual_selection_text').innerText = `${bCount} movimientos y ${eCount} cobranzas seleccionados`;
    } else {
        bar.style.display = 'none';
    }
}

function quitarSeleccion() {
    state.seleccionBanco.clear();
    state.seleccionErp.clear();
    document.querySelectorAll('.table-erp input[type="checkbox"]').forEach(chk => chk.checked = false);
    document.querySelectorAll('.table-erp tr').forEach(tr => tr.classList.remove('selected-row'));
    actualizarBarraContextual();
    guardarEstadoEnSession();
}

/**
 * MODAL ORGANIZAR SELECCIÓN Y VINCULACIÓN VISUAL (SECCIÓN 8)
 */
function abrirModalOrganizar() {
    if (state.seleccionBanco.size === 0 && state.seleccionErp.size === 0) {
        alert("Seleccioná al menos un movimiento o cobranza.");
        return;
    }

    const colBanco = document.getElementById('org_col_banco');
    const colErp = document.getElementById('org_col_erp');
    colBanco.innerHTML = '<strong style="font-size:0.85rem; color:#475569;">Movimientos bancarios</strong>';
    colErp.innerHTML = '<strong style="font-size:0.85rem; color:#475569;">Cobranzas ERP</strong>';

    state.seleccionBanco.forEach(id => {
        const item = document.createElement('div');
        item.className = 'organizar-item';
        item.id = `org_item_banco_${id}`;
        item.innerText = `MB-${id}`;
        item.onclick = () => seleccionarParaConectar(id, 'banco', item);
        colBanco.appendChild(item);
    });

    state.seleccionErp.forEach(id => {
        const item = document.createElement('div');
        item.className = 'organizar-item';
        item.id = `org_item_erp_${id}`;
        item.innerText = `CE-${id}`;
        item.onclick = () => seleccionarParaConectar(id, 'erp', item);
        colErp.appendChild(item);
    });

    state.conexionesTemp = [];
    dibujarLineasConexion();
    document.getElementById('modal_organizar').classList.add('active');
}

function seleccionarParaConectar(id, tipo, el) {
    if (tipo === 'banco') {
        document.querySelectorAll('#org_col_banco .organizar-item').forEach(i => i.classList.remove('active-connect'));
        el.classList.add('active-connect');
        connectingBancoId = id;
    } else if (tipo === 'erp' && connectingBancoId !== null) {
        state.conexionesTemp.push([connectingBancoId, id]);
        document.querySelectorAll('#org_col_banco .organizar-item').forEach(i => i.classList.remove('active-connect'));
        connectingBancoId = null;
        dibujarLineasConexion();
    }
}

function dibujarLineasConexion() {
    const svg = document.getElementById('connectionsSvg');
    svg.innerHTML = '';

    state.conexionesTemp.forEach(([bId, eId], index) => {
        const bEl = document.getElementById(`org_item_banco_${bId}`);
        const eEl = document.getElementById(`org_item_erp_${eId}`);
        if (!bEl || !eEl) return;

        const containerRect = svg.getBoundingClientRect();
        const bRect = bEl.getBoundingClientRect();
        const eRect = eEl.getBoundingClientRect();

        const y1 = (bRect.top + bRect.height / 2) - containerRect.top;
        const y2 = (eRect.top + eRect.height / 2) - containerRect.top;

        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', '0');
        line.setAttribute('y1', y1.toString());
        line.setAttribute('x2', '140');
        line.setAttribute('y2', y2.toString());
        line.setAttribute('class', 'conn-line');
        line.title = "Clic para eliminar esta conexión";
        line.onclick = () => {
            state.conexionesTemp.splice(index, 1);
            dibujarLineasConexion();
        };
        svg.appendChild(line);
    });
}

function deshacerConexion() {
    state.conexionesTemp.pop();
    dibujarLineasConexion();
}

function limpiarConexiones() {
    state.conexionesTemp = [];
    dibujarLineasConexion();
}

function intentarCerrarOrganizar() {
    if (state.conexionesTemp.length > 0) {
        if (confirm("Tenés modificaciones sin guardar. ¿Deseás guardar la distribución antes de salir?")) {
            guardarDistribucionGrupos();
            return;
        }
    }
    cancelarOrganizar();
}

function cancelarOrganizar() {
    document.getElementById('modal_organizar').classList.remove('active');
}

function guardarDistribucionGrupos() {
    const nuevoGrupoId = state.gruposManuales.length + 1;
    const bancoIds = new Set();
    const erpIds = new Set();

    state.conexionesTemp.forEach(([bId, eId]) => {
        bancoIds.add(bId);
        erpIds.add(eId);
    });

    if (bancoIds.size > 0 || erpIds.size > 0) {
        state.gruposManuales.push({
            id: nuevoGrupoId,
            nombre: `Grupo ${nuevoGrupoId}`,
            bancoIds: Array.from(bancoIds),
            erpIds: Array.from(erpIds),
            checked: false
        });
    }

    document.getElementById('modal_organizar').classList.remove('active');
    guardarEstadoEnSession();
    renderGruposManuales();
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
        area.innerHTML = '<p style="margin-top:0.5rem; color:#64748B;">Para consultar movimientos conciliados con fecha y comprobante, usá el filtro "Estado conciliación = Matcheado" en la tabla superior.</p>';
    }
}

function renderGruposManuales() {
    const area = document.getElementById('tab_content_area');
    if (state.gruposManuales.length === 0) {
        area.innerHTML = `
            <p style="margin-top:0.5rem; color:#64748B;">
                No hay grupos organizados todavía. Seleccioná movimientos y cobranzas en las tablas superiores y pulsá <strong>"Organizar selección"</strong>.
            </p>
        `;
        return;
    }

    const incompletos = state.gruposManuales.filter(g => g.bancoIds.length === 0 || g.erpIds.length === 0);
    const completos = state.gruposManuales.filter(g => g.bancoIds.length > 0 && g.erpIds.length > 0);
    const ordenados = [...incompletos, ...completos];

    let html = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
            <label style="font-size:0.85rem; font-weight:600; cursor:pointer;">
                <input type="checkbox" onchange="seleccionarTodosConciliables(this)"> Seleccionar todos los conciliables
            </label>
            <button type="button" class="btn-action btn-success" onclick="confirmarGruposSeleccionados()">Confirmar conciliaciones seleccionadas</button>
        </div>
    `;

    ordenados.forEach(g => {
        const esIncompleto = g.bancoIds.length === 0 || g.erpIds.length === 0;
        html += `
            <div class="group-card ${esIncompleto ? 'incomplete' : 'complete'}">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <input type="checkbox" ${esIncompleto ? 'disabled title="Necesita contraparte para conciliar"' : ''} ${g.checked ? 'checked' : ''} onchange="toggleGrupoCheck(${g.id}, this)">
                    <div>
                        <input type="text" value="${g.nombre}" onchange="renombrarGrupo(${g.id}, this.value)" style="border:1px dashed #CBD5E1; border-radius:4px; padding:2px 6px; font-weight:700; color:#1E293B;">
                        <div style="font-size:0.8rem; color:#64748B; margin-top:2px;">
                            ${g.bancoIds.length} movimientos bancarios · ${g.erpIds.length} cobranzas ERP
                            ${esIncompleto ? '<strong>(Pendiente de contraparte)</strong>' : ''}
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:0.4rem;">
                    <button type="button" class="btn-action btn-secondary" style="padding:0.3rem 0.6rem; font-size:0.8rem;" onclick="eliminarGrupo(${g.id})">Eliminar</button>
                </div>
            </div>
        `;
    });

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
        alert("Seleccioná al menos un grupo completo con su casilla tildada.");
        return;
    }

    if (!confirm(`¿Confirmar ${paraConfirmar.length} grupo(s) de conciliación?`)) return;

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

    alert("Conciliaciones confirmadas exitosamente.");
    state.gruposManuales = state.gruposManuales.filter(g => !g.checked);
    quitarSeleccion();
    guardarEstadoEnSession();
    cargarMovimientosBancarios();
    cargarCobranzasErp();
    renderGruposManuales();
}

async function cargarPropuestasMatching(pestana) {
    const area = document.getElementById('tab_content_area');
    area.innerHTML = '<p style="margin-top:0.5rem;">Evaluando coincidencias automáticas...</p>';
    try {
        const res = await fetch('/api/conciliacion/propuestas');
        const json = await res.json();
        const propuestas = pestana === 'alta_confianza' ? json.data.alta_confianza : json.data.para_revisar;

        if (!propuestas || propuestas.length === 0) {
            area.innerHTML = `<p style="margin-top:0.5rem; color:#64748B;">No hay propuestas de ${pestana === 'alta_confianza' ? 'alta confianza' : 'revisión'}.</p>`;
            return;
        }

        let html = '<div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.5rem;">';
        propuestas.forEach(p => {
            html += `
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; padding:0.75rem; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong>Movimiento:</strong> $${p.movimiento.monto} (${p.movimiento.fecha_operacion}) - ${p.movimiento.descripcion_normalizada}<br>
                        <strong>Cobro Rendición #${p.cobro.id_rendicion_cobro}:</strong> $${p.cobro.monto} (${p.cobro.fecha_registro.substring(0, 10)}) - Cliente: ${p.cliente ? p.cliente.nombre : '#' + p.cobro.id_cliente}
                    </div>
                    <button type="button" class="btn-action btn-success" style="padding:0.35rem 0.75rem; font-size:0.8rem;" onclick="confirmarMatchIndividual(${p.movimiento.id_movimiento_bancario}, ${p.cobro.id_rendicion_cobro})">Confirmar</button>
                </div>
            `;
        });
        html += '</div>';
        area.innerHTML = html;
    } catch(e) { area.innerHTML = '<p style="color:#DC2626;">Error al evaluar coincidencias.</p>'; }
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

/**
 * IMPORTACIÓN DE EXTRACTOS MÚLTIPLES (SECCIÓN 11)
 */
function abrirModalExtracto() { document.getElementById('modal_extracto').classList.add('active'); }
function cerrarModalExtracto() { document.getElementById('modal_extracto').classList.remove('active'); }

function archivosSeleccionados(input) {
    if (input.files && input.files.length > 0) {
        for (let i = 0; i < input.files.length; i++) {
            archivosEnEspera.push(input.files[i]);
        }
        renderListaArchivos();
    }
}

function renderListaArchivos() {
    const ul = document.getElementById('lista_archivos_ul');
    const container = document.getElementById('lista_archivos_container');
    const btnSubmit = document.getElementById('btn_submit_extracto');
    ul.innerHTML = '';

    if (archivosEnEspera.length === 0) {
        container.style.display = 'none';
        btnSubmit.disabled = true;
        return;
    }

    container.style.display = 'block';
    btnSubmit.disabled = false;

    archivosEnEspera.forEach((f, idx) => {
        const li = document.createElement('li');
        li.style.display = 'flex';
        li.style.justifyContent = 'space-between';
        li.style.padding = '3px 0';
        li.innerHTML = `
            <span><strong>[Archivo]</strong> ${f.name} (${(f.size / 1024).toFixed(1)} KB)</span>
            <button type="button" onclick="eliminarArchivoEspera(${idx})" style="background:none; border:none; color:#DC2626; cursor:pointer;">✕</button>
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

async function enviarExtractosMultiples(event) {
    event.preventDefault();
    if (archivosEnEspera.length === 0) return;

    const spinner = document.getElementById('import_spinner');
    const resDiv = document.getElementById('import_resultado');
    const btnSubmit = document.getElementById('btn_submit_extracto');

    spinner.style.display = 'block';
    resDiv.style.display = 'none';
    btnSubmit.disabled = true;

    let exitosos = 0;
    let resumenHtml = '';

    for (const f of archivosEnEspera) {
        const formData = new FormData();
        formData.append('archivo', f);
        try {
            const res = await fetch('/api/extractos/importar', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                body: formData
            });
            const data = await res.json();
            if (res.ok && data.success) {
                exitosos++;
                resumenHtml += `<div><strong>[OK]</strong> <strong>${f.name}</strong>: ${data.filas_insertadas} insertadas, ${data.filas_omitidas} omitidas.</div>`;
            } else {
                resumenHtml += `<div><strong>[Error]</strong> <strong>${f.name}</strong>: ${data.mensaje || 'Error'}</div>`;
            }
        } catch(e) {
            resumenHtml += `<div><strong>[Error]</strong> <strong>${f.name}</strong>: ${e.message}</div>`;
        }
    }

    spinner.style.display = 'none';
    resDiv.style.display = 'block';
    resDiv.style.background = exitosos > 0 ? '#DCFCE7' : '#FEE2E2';
    resDiv.style.color = exitosos > 0 ? '#166534' : '#991B1B';

    const hayFiltros = (state.banco.estados.length > 0 || state.banco.tipos.length > 0 || state.banco.fecha_desde || state.banco.fecha_hasta || state.banco.monto_min !== '' || state.banco.monto_max !== '' || state.banco.signo || state.banco.cuit || state.banco.comprobante || state.banco.q);
    if (hayFiltros) {
        resumenHtml += '<p style="margin-top:0.5rem; font-size:0.8rem; font-style:italic;">Filtros activos. Los listados se actualizaron respetando los filtros activos. Algunos movimientos importados podrían no estar visibles.</p>';
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
        const accion = prompt("Tenés cambios sin guardar.\nEscribí:\n1: Guardar cambios y cerrar\n2: Descartar cambios y cerrar\n3: Volver");
        if (accion === '1') guardarConfiguracion();
        else if (accion === '2') cancelarConfiguracion();
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
</script>
@endpush