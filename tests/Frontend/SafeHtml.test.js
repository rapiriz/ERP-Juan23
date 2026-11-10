const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const context = vm.createContext({ window: { location: { origin: 'https://erp.example.test' } }, URL });
vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/safe-html.js'), 'utf8'), context);
const safe = context.window.erpSafeHtml;

test('replenishment rows escape product and supplier data received from the API', () => {
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/sugerencias-reposicion.js'), 'utf8');
    const render = source.slice(source.indexOf('function renderTablaSugerencias'), source.indexOf('function toggleTodas'));
    const tbody = { innerHTML: '' };
    const payload = `O'Connor <img src=x onerror=alert(1)>`;
    const browser = vm.createContext({ document: { getElementById: () => tbody },
        escapeSuggestionHtml: safe.escape, obtenerBadgeMotivo: () => 'badge', obtenerTextoMotivo: () => 'Stock bajo',
        actualizarContadorSeleccionadas: () => {}, rows: [{ id: 1, producto: { nombre: payload, codigo: payload },
            proveedor: { nombre: payload }, cantidad_sugerida: 4, precio_unitario: 2, costo_total: 8,
            velocidad_venta_diaria: 1, plazo_entrega_dias: 2, fecha_reorden: payload, estado: payload }] });
    vm.runInContext(`${render}\nrenderTablaSugerencias(rows);`, browser);
    assert.equal(tbody.innerHTML.includes('<img'), false);
    assert.ok(tbody.innerHTML.includes(safe.escape(payload)));
});

test('collection rows escape observations in attributes and distributor names', () => {
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/g2-rendiciones.js'), 'utf8');
    const render = source.slice(source.indexOf('function renderizarTabla'), source.indexOf('function filtrarEstadoTab'));
    const rendered = [];
    const tbody = { innerHTML: '', appendChild: row => rendered.push(row.innerHTML) };
    const payload = `\"><img src=x onerror=alert(1)>`;
    const browser = vm.createContext({ document: { getElementById: () => tbody, createElement: () => ({ innerHTML: '' }) },
        escapeG2Html: safe.escape, g2Admin: true, rows: [{ id_rendicion: 1, fecha: '2026-11-08',
            repartidor: { nombre: payload }, total_rendido: 4, estado: 'pendiente', observaciones: payload }] });
    vm.runInContext(`${render}\nrenderizarTabla(rows);`, browser);
    assert.equal(rendered.length, 1);
    assert.equal(rendered[0].includes('<img'), false);
    assert.ok(rendered[0].includes(safe.escape(payload)));
});

test('escapes HTML text and both attribute delimiters', () => {
    assert.equal(safe.escape('<img src=x onerror="alert(1)"> & \'label\''),
        '&lt;img src=x onerror=&quot;alert(1)&quot;&gt; &amp; &#39;label&#39;');
    assert.equal(safe.escape(null), '');
    assert.equal(safe.escape('Pan & queso'), 'Pan &amp; queso');
});

test('JSON followed by HTML escaping preserves inline-handler arguments', () => {
    const payload = '\\"\'); globalThis.executed = true; // <img src=x onerror=alert(1)>';
    const entities = { '&quot;': '"', '&#39;': "'", '&lt;': '<', '&gt;': '>', '&amp;': '&' };
    const attribute = safe.escape(JSON.stringify(payload));
    assert.equal(attribute.includes('"'), false);
    const decoded = attribute.replace(/&quot;|&#39;|&lt;|&gt;|&amp;/g, entity => entities[entity]);
    const handler = vm.createContext({ received: null, executed: false });
    vm.runInContext(`function edit(value) { received = value; } edit(${decoded});`, handler);
    assert.equal(handler.received, payload);
    assert.equal(handler.executed, false);
});

test('accepts local and HTTP images and rejects executable URLs or credentials', () => {
    assert.equal(safe.imageUrl('/storage/producto.png'), 'https://erp.example.test/storage/producto.png');
    assert.equal(safe.imageUrl('https://images.example.test/a.png'), 'https://images.example.test/a.png');
    for (const value of ['', null, 'javascript:alert(1)', 'data:image/svg+xml,<svg onload=alert(1)>',
        'file:///secret', 'https://user:password@example.test/image']) {
        assert.equal(safe.imageUrl(value), '');
    }
});

test('supplier rows escape stored fields and preserve quoted names in real action handlers', () => {
    const source = fs.readFileSync(path.join(__dirname, '../../resources/views/proveedores/index.blade.php'), 'utf8');
    const render = source.match(/function renderProveedores\(\) \{[\s\S]*?\n    \}/)[0];
    const name = `O'Connor & "Centro" </button><img src=x onerror=alert(1)>`;
    const elements = {
        filtroSearchProvs: { value: '' },
        filtroEstadoProvs: { value: 'todos' },
        tablaProveedoresBody: { innerHTML: '' }
    };
    const supplier = { id_proveedor: 1, razon_social: name, estado: 'activo', contacto: name,
        telefono: name, email: name, direccion: name, cuit: '30123456789' };
    const browser = vm.createContext({ window: context.window,
        document: { getElementById: id => elements[id] }, proveedoresCache: [supplier] });
    vm.runInContext(`${render}\nrenderProveedores();`, browser);
    const html = elements.tablaProveedoresBody.innerHTML;
    assert.equal(html.includes('<img'), false);
    assert.ok(html.includes(safe.escape(name)));
    const encoded = html.match(/onclick="(abrirModalPreciosHistoricos[^\"]*)"/)[1];
    const entities = { '&quot;': '"', '&#39;': "'", '&lt;': '<', '&gt;': '>', '&amp;': '&' };
    const handler = encoded.replace(/&quot;|&#39;|&lt;|&gt;|&amp;/g, entity => entities[entity]);
    const action = vm.createContext({ received: null });
    vm.runInContext(`function abrirModalPreciosHistoricos(id, name) { received = name; } ${handler}`, action);
    assert.equal(action.received, name);
});
