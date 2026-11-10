/* Conciliación: selección y totales orientativos; el servidor valida y aplica todo. */
(function () {
    'use strict';
    const cents = value => Math.round(Number(value) * 100);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const total = rows => rows.reduce((sum, row) => sum + cents(row.monto), 0);
    if (typeof module !== 'undefined') module.exports = { cents, total, esc };
    if (typeof document === 'undefined') return;
    const app = document.getElementById('bankApp');
    if (!app) return;
    const $ = id => document.getElementById(id);
    const money = value => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);
    let data = { periodos: [], movimientos: [], origenes: [], propuestas: {}, historial: [] };
    let selectedBank = new Set(), selectedSource = new Set(), tab = 'manual', reasonAction = null;
    const sourceKey = row => `${row.origen}:${row.id}`;
    const status = message => { $('bankStatus').textContent = message; };
    async function api(path, method = 'GET', body = null) {
        const options = { method };
        if (body instanceof FormData) options.body = body;
        else if (body) { options.headers = { 'Content-Type': 'application/json' }; options.body = JSON.stringify(body); }
        const response = await g2Fetch(`/g2/api/${path}`, options), result = await response.json();
        if (!response.ok || result.error) throw new Error(result.mensaje || result.message || 'No se pudo completar la operación.');
        return result;
    }
    async function load() {
        const period = $('bankPeriod').value;
        data = await api('bancos' + (period ? `?periodo_id=${Number(period)}` : ''));
        $('bankPeriod').innerHTML = '<option value="">Todos</option>' + data.periodos.map(p => `<option value="${Number(p.id_periodo)}">${esc(String(p.fecha_desde).slice(0, 10))} / ${esc(String(p.fecha_hasta).slice(0, 10))} · ${esc(p.estado)}</option>`).join('');
        $('bankPeriod').value = period;
        selectedBank.clear(); selectedSource.clear(); $('bankConfirm').hidden = true;
        render(); await renderTab();
    }
    function render() {
        const term = $('bankSearch').value.toLocaleLowerCase('es');
        const rows = data.movimientos.filter(m => `${m.descripcion_original} ${m.nro_comprobante_extraido ?? ''}`.toLocaleLowerCase('es').includes(term));
        $('bankRows').innerHTML = rows.map(m => {
            const eligible = m.id_periodo && ['pendiente', 'revision_manual'].includes(m.estado_conciliacion);
            let action = '';
            if (!m.id_periodo) action = `<button class="button button-secondary" type="button" data-assign="${Number(m.id_movimiento_bancario)}">Asignar al período seleccionado</button>`;
            else if (m.estado_conciliacion === 'en_disputa') action = `<button class="button button-secondary" type="button" data-classify="resolver" data-id="${Number(m.id_movimiento_bancario)}">Resolver disputa</button>`;
            else if (['pendiente', 'revision_manual', 'no_aplica'].includes(m.estado_conciliacion)) {
                action = `<button class="button button-secondary" type="button" data-classify="disputar" data-id="${Number(m.id_movimiento_bancario)}">Marcar disputa</button>`;
                if (['gasto_bancario', 'impuesto_bancario'].includes(m.tipo_movimiento)) action += `<button class="button button-secondary" type="button" data-classify="gasto" data-id="${Number(m.id_movimiento_bancario)}">Registrar gasto bancario</button>`;
            }
            return `<tr><td><input type="checkbox" data-bank-id="${Number(m.id_movimiento_bancario)}" aria-label="Seleccionar movimiento ${Number(m.id_movimiento_bancario)}" ${selectedBank.has(Number(m.id_movimiento_bancario)) ? 'checked' : ''} ${eligible ? '' : 'disabled'}></td><td>${esc(String(m.fecha_operacion).slice(0, 10))}<p>${esc(m.descripcion_original)}</p></td><td>${money(m.monto)}<p>${esc(m.signo_monto)}</p></td><td>${esc(m.estado_conciliacion)} ${action}</td></tr>`;
        }).join('') || '<tr><td colspan="4">No hay movimientos para la consulta.</td></tr>';
        $('bankSources').innerHTML = data.origenes.map(s => `<tr><td><input type="checkbox" data-source-key="${esc(sourceKey(s))}" aria-label="Seleccionar ${esc(sourceKey(s))}" ${selectedSource.has(sourceKey(s)) ? 'checked' : ''}></td><td>${esc(s.origen)} #${Number(s.id)}<p>${esc(s.referencia)}</p></td><td>${esc(s.fecha)}</td><td>${money(s.monto)}<p>${esc(s.signo)}</p></td></tr>`).join('') || '<tr><td colspan="4">No hay operaciones disponibles.</td></tr>';
        totals();
    }
    function selection() {
        return { banks: data.movimientos.filter(m => selectedBank.has(Number(m.id_movimiento_bancario))), sources: data.origenes.filter(s => selectedSource.has(sourceKey(s))) };
    }
    function totals() {
        const { banks, sources } = selection(), bankSum = total(banks), sourceSum = total(sources);
        $('bankTotals').textContent = `Banco: ${banks.length} movimiento(s), ${money(bankSum / 100)}. Sistema: ${sources.length} operación(es), ${money(sourceSum / 100)}. Diferencia: ${money((bankSum - sourceSum) / 100)}.`;
        $('bankReconcile').disabled = !banks.length || !sources.length || bankSum !== sourceSum;
    }
    async function renderTab() {
        $('bankManual').hidden = tab !== 'manual'; $('bankOther').hidden = tab === 'manual';
        document.querySelectorAll('[data-bank-tab]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.bankTab === tab)));
        if (tab === 'manual') return;
        const area = $('bankOther');
        if (tab === 'historial') {
            area.innerHTML = '<h2>Conciliaciones registradas</h2>' + (data.historial.map(g => {
                let selected = {}; try { selected = typeof g.seleccion === 'string' ? JSON.parse(g.seleccion) : (g.seleccion || {}); } catch (_) { /* El historial conserva la evidencia aunque falte un detalle legible. */ }
                const banks = (selected.banco || []).map(id => `#${Number(id)}`).join(', ');
                const sources = (selected.origenes || []).map(s => `${esc(s.origen)} #${Number(s.id)}`).join(', ');
                return `<article class="section-block"><strong>${money(g.monto)} · ${esc(g.metodo)} · ${esc(g.estado)}</strong><p>${esc(g.nota)} · ${esc(g.created_at)}</p><details><summary>Ver cruce registrado</summary><p>Banco: ${banks || 'Sin detalle disponible'}</p><p>Sistema: ${sources || 'Sin detalle disponible'}</p><p>Usuario #${Number(g.usuario_id || 0)} · Grupo ${esc(g.id)}</p></details>${g.estado === 'activo' ? `<button class="button button-secondary" type="button" data-reverse="${esc(g.id)}">Revertir conciliación</button>` : ''}</article>`;
            }).join('') || '<p>No hay conciliaciones registradas.</p>');
        } else if (tab === 'cheques') {
            const result = await api('cheques');
            area.innerHTML = '<h2>Cartera de cheques</h2>' + ((result.data || []).map(c => `<article class="section-block"><strong>Cheque ${esc(c.numero_cheque)} · ${esc(c.banco_emisor)}</strong><p>${money(c.cobro?.monto_total ?? 0)} · ${esc(c.estado)}</p>${c.estado === 'en_cartera' ? `<button class="button button-secondary" type="button" data-deposit="${Number(c.id_cheque)}">Depositar</button>` : ''}${app.dataset.admin === '1' && !['rechazado', 'anulado'].includes(c.estado) ? `<button class="button button-secondary" type="button" data-reject="${Number(c.id_cheque)}">Rechazar y revertir cobro</button>` : ''}</article>`).join('') || '<p>No hay cheques registrados.</p>');
        } else {
            const rows = data.propuestas[tab] || [];
            area.innerHTML = `<h2>${tab === 'alta_confianza' ? 'Coincidencias de alta confianza' : 'Revisión manual'}</h2>` + (rows.map(p => `<article class="section-block"><strong>Movimiento #${Number(p.id_movimiento_bancario)}</strong><p>${p.candidatos.length} candidato(s): ${p.candidatos.map(c => `${esc(c.origen)} #${Number(c.id)} · ${money(c.monto)}`).join(', ') || 'Sin coincidencia exacta; revisá la selección manual.'}</p></article>`).join('') || '<p>No hay propuestas. Seleccioná un período abierto y actualizá.</p>');
        }
    }
    function reason(title, action) { $('bankReasonTitle').textContent = title; $('bankReasonPanel').hidden = false; $('bankReason').value = ''; $('bankReason').focus(); reasonAction = action; }
    async function run(action) {
        const buttons = [...app.querySelectorAll('button')]; buttons.forEach(b => { b.disabled = true; });
        try { const result = await action(); await load(); status(result?.mensaje || 'Operación completada.'); }
        catch (error) { status(error.message); }
        finally { buttons.forEach(b => { if (b.isConnected) b.disabled = false; }); totals(); }
    }
    $('bankFilters').addEventListener('submit', event => { event.preventDefault(); run(load); });
    $('bankSearch').addEventListener('input', render);
    app.addEventListener('change', event => {
        const element = event.target;
        if (element.dataset.bankId) element.checked ? selectedBank.add(Number(element.dataset.bankId)) : selectedBank.delete(Number(element.dataset.bankId));
        if (element.dataset.sourceKey) element.checked ? selectedSource.add(element.dataset.sourceKey) : selectedSource.delete(element.dataset.sourceKey);
        totals();
    });
    $('bankOrganize').addEventListener('click', () => {
        const { banks, sources } = selection(); $('bankSelection').innerHTML = banks.map(b => `<li>Banco #${Number(b.id_movimiento_bancario)}: ${money(b.monto)}</li>`).join('') + sources.map(s => `<li>${esc(s.origen)} #${Number(s.id)}: ${money(s.monto)}</li>`).join('');
        $('bankConfirm').hidden = false; totals();
    });
    $('bankClear').addEventListener('click', () => { selectedBank.clear(); selectedSource.clear(); $('bankConfirm').hidden = true; render(); });
    $('bankReconcile').addEventListener('click', () => run(() => api('bancos/grupos', 'POST', { request_key: crypto.randomUUID(), movimientos: [...selectedBank], origenes: selection().sources.map(s => ({ origen: s.origen, id: s.id })), nota: $('bankNote').value })));
    $('bankPeriodForm').addEventListener('submit', event => { event.preventDefault(); run(() => api('bancos/periodos', 'POST', Object.fromEntries(new FormData(event.target)))); });
    $('bankImport').addEventListener('submit', event => { event.preventDefault(); run(() => api('bancos/extractos', 'POST', new FormData(event.target))); });
    $('bankAuto').addEventListener('click', () => run(() => { if (!$('bankPeriod').value) throw new Error('Seleccioná un período.'); return api(`bancos/periodos/${Number($('bankPeriod').value)}/automatico`, 'POST'); }));
    $('bankClose').addEventListener('click', () => run(() => { if (!$('bankPeriod').value) throw new Error('Seleccioná un período.'); return api(`bancos/periodos/${Number($('bankPeriod').value)}/cerrar`, 'POST'); }));
    $('bankReasonCancel').addEventListener('click', () => { $('bankReasonPanel').hidden = true; reasonAction = null; });
    $('bankReasonForm').addEventListener('submit', event => { event.preventDefault(); run(async () => { const result = await reasonAction($('bankReason').value); $('bankReasonPanel').hidden = true; return result; }); });
    app.addEventListener('click', event => {
        const button = event.target.closest('button'); if (!button) return;
        if (button.dataset.bankTab) { tab = button.dataset.bankTab; renderTab().catch(error => status(error.message)); }
        if (button.dataset.classify) reason('Clasificar movimiento', note => api(`bancos/movimientos/${Number(button.dataset.id)}/clasificar`, 'POST', { accion: button.dataset.classify, nota: note }));
        if (button.dataset.reverse) reason('Revertir conciliación', note => api(`bancos/grupos/${encodeURIComponent(button.dataset.reverse)}/revertir`, 'POST', { motivo: note }));
        if (button.dataset.reject) reason('Rechazar cheque y restablecer deuda', note => api(`cheques/${Number(button.dataset.reject)}/rechazar`, 'PATCH', { motivo: note }));
        if (button.dataset.deposit) run(() => api(`cheques/${Number(button.dataset.deposit)}/depositar`, 'PATCH'));
        if (button.dataset.assign) run(() => { if (!$('bankPeriod').value) throw new Error('Seleccioná el período de destino.'); return api(`bancos/movimientos/${Number(button.dataset.assign)}/periodo`, 'POST', { periodo_id: Number($('bankPeriod').value) }); });
    });
    load().catch(error => status(error.message));
})();
