const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const {execFileSync}=require('node:child_process');
(async()=>{const b=await chromium.launch({channel:'chrome',headless:true});try{
 for(const width of [390,768,1440]){
  const p=await b.newPage({viewport:{width,height:1050}});
  await p.setContent(execFileSync('php',['-n','tests/admin_controls_preview.php'],{encoding:'utf8'}));
  for(const css of ['admin.css','admin-controls.css'])await p.addStyleTag({path:'public/assets/css/'+css});
  if(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error('Page overflow '+width);
  const checkbox=await p.locator('[type=checkbox]').boundingBox();if(checkbox.width!==20||checkbox.height!==20)throw Error('Checkbox distorted');
  if(await p.locator('.vendor-payment-form [name=email]').inputValue()!=='vendor@example.com')throw Error('Wrong vendor');
  if(await p.locator('.vendor-payment-form [name=provider]').inputValue()!=='paystack')throw Error('Wrong provider');
  if(await p.locator('.vendor-payment-form [name=csrf_token]').inputValue()!=='test-token')throw Error('CSRF missing');
  for(const s of ['manage','setup','rank','priority_monthly','priority_annual'])await p.locator('.vendor-payment-form select').selectOption(s);
  await p.locator('.settings-form input').first().focus();
  const focus=await p.locator('.settings-form input').first().evaluate(e=>getComputedStyle(e).outlineStyle);if(focus==='none')throw Error('No focus indicator');
  await p.screenshot({path:'/tmp/admin-controls-'+width+'.png',fullPage:true});
  console.log('PASS controls and vendor payment form '+width);await p.close();
 }
 const p=await b.newPage();await p.setContent(execFileSync('php',['-n','tests/admin_controls_preview.php','restricted'],{encoding:'utf8'}));if(await p.locator('.vendor-payment-form').count())throw Error('Payment permission not preserved');console.log('PASS restricted role');
}finally{await b.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
