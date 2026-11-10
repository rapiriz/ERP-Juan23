const { test } = require('node:test');
const assert = require('node:assert/strict');
const { cents, total, esc } = require('../../public/js/g2-banking.js');

test('los totales de selección comparan centavos y evitan diferencias binarias visibles', () => {
    assert.equal(total([{ monto: '0.10' }, { monto: '0.20' }]), cents('0.30'));
    assert.equal(total([{ monto: '60.25' }, { monto: '40.50' }]), 10075);
});
test('referencias y nombres bancarios se escapan incluso con comillas y etiquetas', () => {
    const value = esc('<img src=x onerror="alert(1)"> & \'dato\'');
    assert.equal(value, '&lt;img src=x onerror=&quot;alert(1)&quot;&gt; &amp; &#39;dato&#39;');
});
