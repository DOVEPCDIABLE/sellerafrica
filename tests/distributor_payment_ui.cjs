const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const {execFileSync}=require('node:child_process');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  for(const width of [390,768,1440]){
   const page=await browser.newPage({viewport:{width,height:1000}});
   for(const state of ['normal','error','paid','cancelled']){
    await page.setContent(execFileSync('php',['-n','tests/distributor_payment_preview.php',state],{encoding:'utf8'}));
    for(const css of ['distributor.css','account-navigation.css','distributor-payment.css'])await page.addStyleTag({path:'public/assets/css/'+css});
    if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error(`Overflow ${width} ${state}`);
    if(state==='normal'){
     if(await page.locator('.retail-plan').count()!==3)throw Error('Plans missing');
     await page.locator('[name=plan][value=access]').check();
     await page.locator('[name=provider]').selectOption('paystack');
     if(await page.locator('form').evaluate(f=>f.checkValidity()))throw Error('Terms not required');
     await page.locator('[name=accept_terms]').check();
     if(!await page.locator('form').evaluate(f=>f.checkValidity()))throw Error('Valid form blocked');
     await page.screenshot({path:`/tmp/retail-pricing-${width}.png`,fullPage:true});
    }
    if(state==='error' && !await page.locator('[name=plan][value=access]').isChecked())throw Error('Selection lost');
    if(state==='paid' && await page.locator('form').count())throw Error('Paid user can pay again');
   }
   console.log(`PASS ${width}: plans, consent, recovery, paid and cancelled states`);
   await page.close();
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
