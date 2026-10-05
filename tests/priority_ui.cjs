const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const {execFileSync}=require('node:child_process');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{
  for(const width of [390,768,1440]){
   const page=await browser.newPage({viewport:{width,height:1000}});
   for(const state of ['normal','error','paid']){
    await page.setContent(execFileSync('php',['-n','tests/priority_preview.php',state],{encoding:'utf8'}));
    for(const css of ['distributor.css','account-navigation.css','priority.css'])await page.addStyleTag({path:'public/assets/css/'+css});
    await page.waitForTimeout(700);
    if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error(`Overflow ${width} ${state}`);
    if(state==='normal'){
     const form=page.locator('.priority-form');
     if(await form.evaluate(f=>f.checkValidity()))throw Error('Required fields missing');
     await form.locator('[name=name]').fill('Preview Member');
     await form.locator('[name=email]').fill('preview@example.com');
     await form.locator('[name=terms]').check();
     await form.locator('[name=plan][value=annual]').check();
     if(!await form.evaluate(f=>f.checkValidity()))throw Error('Valid form blocked');
     await page.screenshot({path:`/tmp/priority-${width}.png`,fullPage:true});
    }
    if(state==='paid' && await page.locator('.priority-form').count())throw Error('Paid state permits duplicate checkout');
   }
   console.log(`PASS ${width}: responsive, validation, error, paid states`);
   await page.close();
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
