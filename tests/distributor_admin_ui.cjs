const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const {execFileSync}=require('node:child_process');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  for(const width of [390,768,1440]) {
   const page=await browser.newPage({viewport:{width,height:1000}});
   for(const state of ['normal','error','empty']) {
    await page.setContent(execFileSync('php',['-n','tests/distributor_admin_preview.php',state],{encoding:'utf8'}));
    await page.addStyleTag({path:'public/assets/css/admin.css'});
    await page.addStyleTag({path:'public/assets/css/distributor-admin.css'});
    await page.addStyleTag({path:'public/assets/css/admin-controls.css'});
    if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error(`Overflow ${width} ${state}`);
    if(state==='normal') {
     await page.screenshot({path:`/tmp/distributor-admin-${width}.png`});
     await page.locator('summary').click();
     if(!await page.locator('.distribution-review').isVisible())throw Error('Review form hidden');
     if(await page.locator('[name=csrf_token]').inputValue()!=='test-only-token')throw Error('CSRF lost');
     await page.locator('.distribution-review').screenshot({path:`/tmp/distributor-review-${width}.png`});
    } else if(state==='error') {
     if(!await page.locator('details').getAttribute('open').then(v=>v!==null))throw Error('Error form closed');
     if(await page.locator('textarea').inputValue()!=='Keep this feedback')throw Error('Feedback lost');
    } else if(!await page.getByText('No applications in this view',{exact:true}).isVisible())throw Error('Empty state hidden');
   }
   console.log(`${width}: application list, review, validation recovery and empty state passed`);
   await page.close();
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
