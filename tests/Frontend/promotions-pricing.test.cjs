const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/promociones.js'), 'utf8');
const start = source.indexOf('    function calcularDetallePromo(');
const end = source.indexOf('    function agregarPromocionAlCarrito(', start);
assert.ok(start > 0 && end > start);
const calculate = new Function('productosDisponibles', source.slice(start, end) + '; return calcularDetallePromo;')([
    { id: 1, precioMin: 10.75 }, { id: 2, precioMin: 20.25 }, { id: 3, precioMin: 1 },
]);

test('keeps unit cents and never grants more than the authorized percentage', () => {
    const result = calculate({tipoValor: '%', valor: 10, productos: [{id_producto: 1, cantidad: 2}]});
    assert.equal(result.total, 19.36);
    assert.equal(result.descuentoTotal, 2.14);
    assert.ok(result.descuentoTotal <= 2.15);
});

test('uses the selected price list already in the cart', () => {
    const result = calculate({tipoValor: '%', valor: 10, productos: [{id_producto: 1, cantidad: 2}]}, [{id: 1, precioUnit: 8.50}]);
    assert.equal(result.total, 15.3);
    assert.equal(result.descuentoTotal, 1.7);
});

test('fixed discount distribution is independent of product display order', () => {
    const first = calculate({tipoValor: '$', valor: 3.01, productos: [{id_producto: 1, cantidad: 2}, {id_producto: 2, cantidad: 1}]});
    const second = calculate({tipoValor: '$', valor: 3.01, productos: [{id_producto: 2, cantidad: 1}, {id_producto: 1, cantidad: 2}]});
    assert.deepEqual(first, second);
    assert.ok(first.descuentoTotal <= 3.01);
});

test('an indivisible one-cent discount is not rounded up into two cents', () => {
    const result = calculate({tipoValor: '$', valor: 0.01, productos: [{id_producto: 3, cantidad: 2}]});
    assert.equal(result.descuentoTotal, 0);
    assert.equal(result.total, 2);
});
