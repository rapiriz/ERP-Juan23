const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/ventas/ventas.blade.php'), 'utf8');
const start = source.indexOf('    function descuentoLineaMonto(');
const end = source.indexOf('    function brutoLinea(', start);
assert.ok(start > 0 && end > start);
const discount = new Function(source.slice(start, end) + '; return descuentoLineaMonto;')();

test('un porcentaje se recalcula al cambiar cantidad y tarifa, sin conservar el monto anterior', () => {
    const line = {cantidad:1, precioUnit:100, descuentoInputModo:'porcentaje', descuentoInputValor:10, descuentoMonto:10};
    assert.equal(discount(line),10);
    assert.equal(discount({...line,cantidad:3,precioUnit:50}),15);
});

test('la vista previa y el descuento unitario enviado al servidor coinciden en centavos', () => {
    const line = {cantidad:3,precioUnit:100.75,descuentoInputModo:'porcentaje',descuentoInputValor:10};
    assert.equal(discount(line),30.24); // 3 x 10,08; el backend redondea por unidad.
    const fixed = discount({cantidad:3,precioUnit:100,descuentoMonto:100});
    assert.equal(fixed,99.99);
    assert.equal(Number((fixed/3).toFixed(2)),33.33);
    assert.equal(discount({cantidad:6,precioUnit:100,descuentoMonto:100}),99.96);
});

test('un regalo del cien por ciento conserva exactamente el bruto y no supera el precio', () => {
    assert.equal(discount({cantidad:3,precioUnit:100.75,descuentoInputModo:'porcentaje',descuentoInputValor:100}),302.25);
    assert.equal(discount({cantidad:1,precioUnit:80,descuentoMonto:100}),80);
    assert.equal(discount({cantidad:1,precioUnit:80,descuentoMonto:-10}),0);
});

test('el descuento global se redondea antes de restarlo, igual que el servidor', () => {
    const a = source.indexOf('    function calcularTotalConDescuento(');
    const b = source.indexOf('    function ', a + 20);
    const total = global => new Function('descuentoGlobal', source.slice(a,b) + ';return calcularTotalConDescuento;')(global);
    assert.equal(total({modo:'porcentaje',valor:33.333})(950),633.34);
    assert.equal(total({modo:'porcentaje',valor:99.999})(100),0);
    assert.equal(total({modo:'monto',valor:100})(302.25),202.25);
});
