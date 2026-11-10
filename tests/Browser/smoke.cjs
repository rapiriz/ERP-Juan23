const {chromium} = require('playwright');
const {default:AxeBuilder} = require('@axe-core/playwright');
const fs = require('node:fs'); const path = require('node:path'); const assert = require('node:assert/strict');
const root = path.resolve(__dirname,'../..');
const base = process.env.ERP_BROWSER_URL || 'http://127.0.0.1:8015';
const url = new URL(base);
assert.ok(['127.0.0.1','localhost'].includes(url.hostname) && url.port === '8015', 'Sólo servidor QA 8015');
const credentials = JSON.parse(fs.readFileSync(path.join(root,'storage/app/private/qa-browser/credentials.json')));
const output = process.env.ERP_BROWSER_RESULTS || path.join(__dirname,'results'); fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser = await chromium.launch({headless:true,...(process.env.ERP_BROWSER_EXECUTABLE?{executablePath:process.env.ERP_BROWSER_EXECUTABLE}:{})});
 const context = await browser.newContext({viewport:{width:1440,height:1000}}); const page = await context.newPage();
 const errors=[];let route=''; page.on('pageerror',error=>errors.push({route,message:error.message,stack:error.stack}));
 page.on('response',response=>{if(response.status()>=500) errors.push({route,status:response.status(),url:response.url()});});
 const results=[];
 try {
  await page.goto(base+'/login');
  await page.locator('button[type=submit]').click(); assert.equal(await page.locator('#usuario').getAttribute('aria-invalid'),'true');
  assert.equal(await page.evaluate(()=>document.activeElement.id),'usuario');
  const loginAudit=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa']).analyze();
  results.push({route:'/login',violations:loginAudit.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>({target:n.target,html:n.html.slice(0,300)}))}))});
  await page.locator('#usuario').fill('qa.browser.admin'); await page.locator('#password').fill(credentials.password);
  await Promise.all([page.waitForURL('**/dashboard'),page.locator('button[type=submit]').click()]);
  for (const target of ['/dashboard','/clientes','/clientes/nuevo','/reclamos','/ventas','/historial-ventas','/saldo','/devoluciones','/inventario','/compras','/proveedores','/entregas','/rendiciones','/caja','/conciliacion','/reportes','/usuarios','/operaciones/stock','/operaciones/historicas','/catalogo']) {
   route=target;const response=await page.goto(base+target,{waitUntil:'networkidle'}); await page.waitForTimeout(100);
   const audit=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
   const violations=audit.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>({target:n.target,html:n.html.slice(0,300)}))}));
   results.push({route,status:response.status(),violations});
   if(target!=='/catalogo') assert.equal(await page.locator('[data-erp-sidebar]').count(),1,target);
   await page.screenshot({path:path.join(output,target.replaceAll('/','_')+'.png'),fullPage:true});
  }

  // Abrir por los controles reales y comprobar foco, diálogo y cierre; sin enviar operaciones.
  for(const [target,button] of [['/inventario','Nuevo Producto'],['/compras','Nueva Compra'],['/ventas','% DESCUENTOS'],['/rendiciones','Nueva Rendición']]) {
   route=target;await page.goto(base+target,{waitUntil:'networkidle'});
   if(target==='/ventas') { await page.locator('#buscadorProductos').fill('QA-BROWSER'); await page.locator('#sugerencias .sugerencia-item').first().click(); }
   const trigger=page.getByRole('button',{name:button,exact:false}).first();
   if(await trigger.count()) {
    await trigger.click();await page.waitForTimeout(200);
    const audit=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
    results.push({route:target,modal:button,violations:audit.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>({target:n.target,html:n.html.slice(0,300)}))}))});
    await page.screenshot({path:path.join(output,target.slice(1)+'-modal.png'),fullPage:true});
    const dialog=page.locator('[role="dialog"]:visible').last();assert.ok(await dialog.count(),target+' diálogo visible');
    assert.ok(await dialog.evaluate(el=>el.contains(document.activeElement)),target+' foco dentro del diálogo');
    await page.keyboard.press('Tab');assert.ok(await dialog.evaluate(el=>el.contains(document.activeElement)),target+' Tab dentro del diálogo');
    await page.keyboard.press('Escape');assert.equal(await dialog.isVisible(),false,target+' Escape cierra el diálogo');

   } else throw new Error('Control de modal ausente: '+target+' '+button);
  }
  const guest = await browser.newContext();
  for (const api of ['/api/v1/clientes','/api/v1/health','/api/ventas']) {
   const response=await guest.request.get(base+api,{headers:{Accept:'application/json'}}); assert.equal(response.status(),401,api);
  }
  assert.equal((await guest.request.get(base+'/api/v1/catalogo')).status(),200);
  await guest.close();
  const counter = await browser.newContext();const counterPage=await counter.newPage();
  await counterPage.goto(base+'/login');await counterPage.locator('#usuario').fill('qa.browser.counter');await counterPage.locator('#password').fill(credentials.password);
  await Promise.all([counterPage.waitForURL('**/dashboard'),counterPage.locator('button[type=submit]').click()]);
  assert.equal((await counterPage.goto(base+'/clientes/'+credentials.client)).status(),200);
  await counterPage.goto(base+'/clientes/'+credentials.client+'/editar');assert.ok(counterPage.url().endsWith('/dashboard'));
  await counterPage.goto(base+'/operaciones/stock');assert.ok(counterPage.url().endsWith('/dashboard'));await counter.close();
  await page.setViewportSize({width:390,height:844});
  for(const target of ['/dashboard','/inventario','/operaciones/stock','/catalogo']) {
   route=target;await page.goto(base+target,{waitUntil:'networkidle'});
   if(target!=='/catalogo') {
    const toggle=page.locator('[data-erp-sidebar-toggle]');const original=await toggle.getAttribute('aria-expanded'); await toggle.click();assert.notEqual(await toggle.getAttribute('aria-expanded'),original);
    await toggle.click();assert.equal(await toggle.getAttribute('aria-expanded'),original);
   }
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2);
   results.push({route:target,mobile:true,overflow});
   await page.screenshot({path:path.join(output,target.replaceAll('/','_')+'-mobile.png'),fullPage:true});
  }
 } finally {
  fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({results,errors},null,2)); await browser.close();
 }
 console.log(JSON.stringify({pages:results.length,runtimeErrors:errors.length,violations:results.reduce((n,r)=>n+(r.violations?.length||0),0),mobileOverflow:results.filter(r=>r.mobile&&r.overflow).map(r=>r.route)}));
 if(errors.length||results.some(r=>r.status>=400||(r.violations||[]).some(v=>['critical','serious'].includes(v.impact))||r.overflow)) process.exitCode=1;
})().catch(error=>{console.error(error.message);process.exitCode=1;});
