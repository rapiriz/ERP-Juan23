const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const read = file => fs.readFileSync(path.join(__dirname, '../..', file), 'utf8');

test('receipt form preserves distinct lines and units when the product is repeated', async () => {
    const source = read('resources/views/compras/index.blade.php');
    const start = source.indexOf('    async function guardarRecepcionCompra(');
    const end = source.indexOf('    let saldoActualModalPago', start);
    let body;
    const fields = {recepcionIdCompra: {value: 7}, recepcionFecha: {value: '2026-11-09'}, rec_cant_0: {value: 1}, rec_cant_1: {value: 2}};
    const scope = vm.createContext({document: {getElementById: id => fields[id], querySelector: () => ({content:'csrf'})}, API_BASE:'/api',
        itemsRecepcionModal: [{id_producto:5,id_detalle_compra:10,id_unidad:3},{id_producto:5,id_detalle_compra:11,id_unidad:4}],
        fetch: async (url, request) => {body = JSON.parse(request.body);return {json:async()=>({status:'success'})};},
        cerrarModalRecepcion(){},mostrarNotificacion(){},cargarCompras(){},cargarOrdenes(){},cargarDeudas(){}});
    vm.runInContext(source.slice(start,end),scope);
    await vm.runInContext('guardarRecepcionCompra({preventDefault(){}})',scope);
    assert.deepEqual(body.items,[{id_detalle_compra:10,id_unidad:3,id_producto:5,cantidad_recibida:1},{id_detalle_compra:11,id_unidad:4,id_producto:5,cantidad_recibida:2}]);
});

test('period totals retain opening debt, subtract reversals, and do not count credit notes as payments', () => {
    const window = {};
    vm.runInNewContext(read('public/assets/js/account-summary.js'), {window});
    const entries = [{fecha:'2026-10-01', monto:-100,tipo:'venta'}, {fecha:'2026-11-01',monto:30,tipo:'pago'},
        {fecha:'2026-11-02',monto:10,tipo:'nota_credito'}, {fecha:'2026-11-03',monto:-5,tipo:'ajuste',referencia_externa:'reversion_pago:1'},
        {fecha:'2026-11-10',monto:20,tipo:'pago'}];
    const result = window.erpAccountSummary(entries,{modoFecha:'periodo',fechaDesde:'2026-11-01',fechaHasta:'2026-11-03'});
    assert.deepEqual(JSON.parse(JSON.stringify(result)),{pagos_netos:25,deuda_al_corte:65,saldo_a_favor_al_corte:0,deuda_actual:45});
    const single = window.erpAccountSummary(entries,{modoFecha:'especifica',fecha:'2026-11-03'});
    assert.equal(single.pagos_netos,-5); assert.equal(single.deuda_al_corte,65);
});

test('credit preview warns before confirming when the cart exceeds available credit', () => {
    const source = read('resources/views/ventas/ventas.blade.php');
    const start = source.indexOf('    function actualizarCreditoVisual('), end = source.indexOf('</script>',start);
    const box = {style:{}};
    const scope = vm.createContext({creditSnapshot:{cliente_id:1,limite_credito:10,credito_disponible:10,excedido:false},
        $:id=>id==='clienteVenta'?{value:'1'}:box,fmt:n=>`$${n}`,calcularTotalConDescuento:n=>n,getSubtotalConDescLineas:()=>21.5});
    vm.runInContext(source.slice(start,end)+'\nactualizarCreditoVisual();',scope);
    assert.ok(box.textContent.includes('no podrá confirmarse'));
    scope.creditSnapshot.credito_disponible=25;
    vm.runInContext('actualizarCreditoVisual();',scope);
    assert.equal(box.textContent.includes('no podrá confirmarse'),false);
});
