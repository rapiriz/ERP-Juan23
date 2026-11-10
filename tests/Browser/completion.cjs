const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {createRequire} = require('node:module');
const root = path.resolve(__dirname,'../..');
const requireBrowser = createRequire(path.join(root,'tests/Browser/package.json'));
const {chromium} = requireBrowser('playwright');
const {default:AxeBuilder} = requireBrowser('@axe-core/playwright');
const base='http://127.0.0.1:8015';
const credentials=JSON.parse(fs.readFileSync(path.join(root,'storage/app/private/qa-browser/credentials.json')));
const output=process.env.ERP_EXTENDED_RESULTS||path.join(__dirname,'results/completion'); fs.mkdirSync(output,{recursive:true});
const results=[], errors=[];
const productCode=credentials.product_code || 'QA-BROWSER';
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.ERP_BROWSER_EXECUTABLE ? {executablePath:process.env.ERP_BROWSER_EXECUTABLE}:{})});
 const guest=await browser.newContext({viewport:{width:1440,height:1000}});
 const page=await guest.newPage();page.on('pageerror',e=>errors.push(e.message));
 async function audit(label, active=page) {
  const scan=await new AxeBuilder({page:active}).withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
  const violations=scan.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>({target:n.target,html:n.html.slice(0,350)}))}));
  results.push({label,violations});await active.screenshot({path:path.join(output,label+'.png'),fullPage:true});
 }
 try {
  const response=await guest.request.get(base+'/api/v1/catalogo?q='+encodeURIComponent(productCode));assert.equal(response.status(),200);
  const product=(await response.json()).data[0];assert.ok(product?.imagen);
  await page.goto(base+'/catalogo?q='+encodeURIComponent(productCode),{waitUntil:'networkidle'});
  await page.locator('[data-cat-image]').first().waitFor();assert.ok(await page.locator('[data-cat-image]').first().evaluate(e=>e.getBoundingClientRect().height<=e.parentElement.getBoundingClientRect().height+1),'foto contenida sin recortar');
  assert.ok(await page.locator('[data-cat-image]').first().evaluate(el=>el.complete&&el.naturalWidth>0),'Foto real cargada');
  await audit('catalogo-foto-real');
  await page.locator(`[data-add-cart="${product.id}"]`).click();
  await page.getByRole('link',{name:/Tu selección/}).click();
  await page.locator('.cat-cart-row').waitFor();
  await page.locator('.cat-cart-row input[type=number]').fill('2');await page.locator('.cat-cart-row input[type=number]').press('Tab');
  await page.waitForFunction(()=>document.querySelector('[data-cart-checkout] button')?.disabled===false);
  await audit('carrito-publico');
  await page.setViewportSize({width:390,height:844});await audit('carrito-movil');
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),'Carrito móvil sin desborde');
  await page.locator('#cart-name').fill('Comercio QA solicitud pública');await page.locator('#cart-email').fill('qa.catalogo@example.test');await page.locator('#cart-phone').fill('2923456789');
  await page.locator('[name=acepta_contacto]').check();
  const submitted=page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname==='/catalogo/pedidos');
  await page.getByRole('button',{name:'Enviar solicitud',exact:true}).click();
  const submittedResponse=await submitted;if(submittedResponse.status()!==201)assert.fail('Envío HTTP '+submittedResponse.status()+': '+await submittedResponse.text());
  await page.waitForURL('**/catalogo/pedido/*');
  await audit('solicitud-recibida');
  assert.ok(await page.getByText('Recibimos tu selección').count());
  const receipt=page.url();
  const admin=await browser.newContext({viewport:{width:1440,height:1000}});const staff=await admin.newPage();staff.on('pageerror',e=>errors.push(e.message));staff.on('response',r=>{if(r.status()>=500)errors.push(r.status()+' '+r.url());});
  await staff.goto(base+'/login');await staff.locator('#usuario').fill('qa.browser.admin');await staff.locator('#password').fill(credentials.password);
  await Promise.all([staff.waitForURL('**/dashboard'),staff.locator('button[type=submit]').click()]);
  const hostile='<img src=x onerror="window.__erpXss=true">';
  await staff.route(/\/api(?:\/v1)?\/compras(?:\?|$)/,async route=>{
    const response=await route.fetch();const data=await response.json();
    if(Array.isArray(data.data)&&data.data.length)data.data[0].proveedor=hostile;
    await route.fulfill({response,json:data});
  });
  await staff.goto(base+'/compras',{waitUntil:'networkidle'});
  await staff.getByText(hostile,{exact:true}).first().waitFor();
  assert.equal(await staff.evaluate(()=>window.__erpXss),undefined);
  await audit('compras-texto-escapado',staff);
  await staff.unroute(/\/api(?:\/v1)?\/compras(?:\?|$)/);
  // Pago real exclusivamente en la copia QA; anulación por el control de la interfaz.
  const purchases=await (await admin.request.get(base+'/api/compras')).json();
  const purchase=purchases.data.find(p=>p.numero_compra==='QA-BROWSER-COMPRA');assert.ok(purchase);
  const csrf=await staff.locator('meta[name="csrf-token"]').getAttribute('content');
  const paymentResponse=await admin.request.post(base+'/api/compras/'+purchase.id_compra+'/pagos',
      {headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},data:{monto:5,metodo_pago:'transferencia'}});
  assert.equal(paymentResponse.status(),201);const payment=(await paymentResponse.json()).data.pago;
  await staff.locator(`button[onclick="abrirModalDetalleCompra(${purchase.id_compra})"]`).first().click();
  const reverse=staff.getByRole('button',{name:'Anular pago '+payment.id_pago,exact:true});await reverse.waitFor();
  const reason='Pago QA duplicado <img src=x onerror="window.__erpXss=true">';
  const dialog=async dialog=>{await dialog.accept(dialog.type()==='prompt'?reason:undefined);};
  staff.on('dialog',dialog);
  const reversed=staff.waitForResponse(r=>r.request().method()==='DELETE'&&r.url().includes('/pagos/'+payment.id_pago));
  await reverse.click();assert.equal((await reversed).status(),200);staff.off('dialog',dialog);
  await staff.locator('#detallePagosHistorial').getByText(reason,{exact:true}).waitFor();
  assert.equal(await staff.locator('#detallePagosHistorial img').count(),0);
  assert.equal(await staff.evaluate(()=>window.__erpXss),undefined);
  await audit('anulacion-pago-proveedor',staff);
  await staff.keyboard.press('Escape');await staff.locator('#modalDetalleCompra').waitFor({state:'hidden'});

  for(const [url,label] of [['/inventario/lotes','lotes'],['/proveedores/estadisticas','cumplimiento-proveedores'],['/operaciones/fiscal','preparacion-fiscal'],['/pedidos-catalogo','solicitudes-administracion']]) {
    const res=await staff.goto(base+url,{waitUntil:'networkidle'});assert.equal(res.status(),200,url);if(url==='/inventario/lotes'){await staff.locator('[data-lot-rows] tr').first().waitFor();assert.ok(!await staff.getByText('Server Error',{exact:true}).count());}await audit(label,staff);
  }
  await staff.locator('a[href*="/pedidos-catalogo/"]').filter({hasText:'Revisar solicitud'}).first().click();await audit('revision-solicitud',staff);
  await staff.locator('#order-client').selectOption(String(credentials.client));await staff.locator('[name=confirmar_revision]').check();
  await staff.getByRole('button',{name:'Convertir a venta',exact:true}).click();
  await staff.getByText(/Venta interna creada:/).waitFor();await audit('solicitud-convertida',staff);
  await page.goto(receipt);assert.ok(await page.getByText('convertido',{exact:false}).count());
  for(const [url,label] of [['/inventario/lotes','lotes-movil'],['/proveedores/estadisticas','proveedores-movil']]) {
    await staff.setViewportSize({width:390,height:844});await staff.goto(base+url,{waitUntil:'networkidle'});await audit(label,staff);
    assert.ok(await staff.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),label+' sin desborde');
  }
  await admin.close();
 } finally {
  fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({results,errors},null,2));await browser.close();
 }
 const violations=results.reduce((n,r)=>n+r.violations.length,0);
 console.log(JSON.stringify({checks:results.length,violations,runtimeErrors:errors.length}));
 if(violations||errors.length)process.exitCode=1;
})().catch(error=>{console.error(error);process.exitCode=1;});
