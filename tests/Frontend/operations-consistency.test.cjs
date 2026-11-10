const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const read = file => fs.readFileSync(path.join(__dirname, '../..', file), 'utf8');
const context = vm.createContext({window: {location: {origin: 'https://erp.test'}}, URL});
vm.runInContext(read('public/assets/js/safe-html.js'), context);
const safe = context.window.erpSafeHtml;

test('purchase detail, receipt and edit modal product names remain text with hostile stored data', () => {
    const source = read('resources/views/compras/index.blade.php');
    const lines = source.split('\n').filter(line => line.includes('font-bold text-slate-800') && line.includes('d.nombre'));
    assert.equal(lines.length, 3);
    for (const line of lines) {
        for (const property of ['nombre', 'descripcion']) {
            const hostile = `"><img src=x onerror=globalThis.executed=true> O'Connor & hijos`;
            const scope = vm.createContext({window: {erpSafeHtml: safe}, d: {[property]: hostile, id_producto: 1}, executed: false});
            vm.runInContext('html = `'+line.trim()+'`;', scope);
            assert.equal(scope.html.includes('<img'), false);
            assert.ok(scope.html.includes(safe.escape(hostile)));
            assert.equal(scope.executed, false);
        }
    }
});

test('cheque fields are required and submitted only when cheque is selected', () => {
    const inputs = [{}, {}, {}];
    const fields = {querySelectorAll: () => inputs};
    const events = {};
    const select = {value: 'efectivo', addEventListener: (type, action) => {events[type] = action;},
        form: {querySelector: () => fields, addEventListener: (type, action) => {events[type] = action;}}};
    vm.runInNewContext(read('public/assets/js/cheque-payment.js'), {document: {querySelectorAll: () => [select]}, setTimeout: action => action()});
    assert.equal(fields.hidden, true);
    assert.ok(inputs.every(input => input.disabled && !input.required));
    select.value = 'Cheque'; events.change();
    assert.equal(fields.hidden, false);
    assert.ok(inputs.every(input => !input.disabled && input.required));
    select.value = 'transferencia'; events.reset();
    assert.equal(fields.hidden, true);
    assert.ok(inputs.every(input => input.disabled && !input.required));
});

test('rendition incidents render persisted identifiers and hostile reasons with textContent', () => {
    function element() {
        return {children: [], textContent: '', appendChild(child) {this.children.push(child);},
            replaceChildren() {this.children = [];}, addEventListener() {}};
    }
    const box = element();
    const hostile = '<img src=x onerror=alert(1)>';
    const code = read('public/js/g2-rendiciones.js');
    const render = code.slice(code.indexOf('function renderRenditionIncidents('));
    vm.runInNewContext(render+'\nrenderRenditionIncidents(record);', {
        document: {getElementById: () => box, createElement: element}, g2Admin: false,
        record: {estado: 'aprobada', devoluciones: [{id: 45, id_pedido: 12, motivo: hostile}],
            diferencia: {tipo_diferencia: 'Faltante', diferencia: -5, diferencia_pendiente: true, motivo: hostile}},
    });
    assert.equal(box.children.length, 3);
    assert.ok(box.children[1].textContent.includes('Devolución #45'));
    assert.ok(box.children[1].textContent.includes(hostile));
    assert.equal(Object.hasOwn(box.children[1], 'innerHTML'), false);
});
