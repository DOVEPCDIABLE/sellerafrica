const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const assert=require('node:assert/strict');
(async()=>{
 const b=await chromium.launch({channel:'chrome',headless:true});
 try{for(const width of [390,768,1440]){
  const p=await b.newPage({viewport:{width,height:1000}});
  await p.route('https://js.klasha.com/pay.js',r=>r.fulfill({body:'',contentType:'application/javascript'}));
  await p.goto('http://127.0.0.1:8098/tests/manage_store_preview.php');
  assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  assert.equal(await p.locator('#manageStoreCheckout').getAttribute('action'),'/manage-store');
  assert.equal(await p.locator('[name=csrf_token]').inputValue(),'preview-token');
  await p.locator('#paystackManageStoreBtn').click();
  assert.match(await p.locator('#manageStoreStatus').innerText(),/name and email/);
  await p.locator('#manageStoreName').fill('Preview User');
  await p.locator('#manageStoreEmail').fill('preview@example.com');
  let calls=[];
  await p.route('**/api/payments/manage-store',r=>{const body=r.request().postData();calls.push(body);return r.fulfill({status:400,contentType:'application/json',body:JSON.stringify({ok:false,message:'Fixture payment response'})});});
  for(const id of ['paystackManageStoreBtn','klashaManageStoreBtn']){
   await p.locator('#'+id).click();
   await p.waitForFunction(()=>document.querySelector('#manageStoreStatus').textContent==='Fixture payment response');
   await p.waitForFunction(id=>!document.getElementById(id).disabled,id);
  }
  assert.match(calls[0],/create_paystack/);assert.match(calls[1],/create_klasha/);
  assert.match(calls[0],/preview@example.com/);
  await p.screenshot({path:'/tmp/manage-store-'+width+'.png',fullPage:true});
  await p.locator('[name=plan][value=setup]').check();
  assert.equal(await p.locator('#servicePrice').innerText(),'$5');
  assert.match(await p.locator('#stripeManageStoreBtn').innerText(),/\$5 once/);
  await p.locator('#paystackManageStoreBtn').click();
  await p.waitForFunction(()=>document.querySelector('#manageStoreStatus').textContent==='Fixture payment response');
  assert.match(calls[2],/name="plan"\r\n\r\nsetup/);
  await p.route('**/manage-store',r=>{assert.match(r.request().postData(),/plan=setup/);return r.fulfill({body:'Stripe form submitted'});});
  await p.locator('#stripeManageStoreBtn').click();await p.getByText('Stripe form submitted').waitFor();
  console.log('PASS layout and mocked Stripe/Paystack/Klasha: '+width);await p.close();
 }}finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1)});
