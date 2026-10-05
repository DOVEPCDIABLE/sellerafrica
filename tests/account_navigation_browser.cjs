const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  for(const width of [320,390,768,1024,1440]) {
   const page=await browser.newPage({viewport:{width,height:900}});
   for(const route of ['login_preview.php','distributor_preview.php?landing=1','distributor_preview.php?guest=1','distributor_preview.php']) {
    await page.goto('http://127.0.0.1:8098/tests/'+route);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,route+' overflow '+width);
    const toggle=page.getByRole('button',{name:'Open menu'});
    if(width<=1100){
     assert.equal(await toggle.isVisible(),true);
     assert.equal(await page.locator('.account-desktop-nav').isVisible(),false);
     await toggle.click();
     assert.equal(await page.locator('dialog').isVisible(),true);
     assert.equal(await page.locator('dialog nav a').count(),6);
     assert.equal(await page.evaluate(()=>getComputedStyle(document.body).overflow),'hidden');
     await page.keyboard.press('Escape');
     await page.waitForFunction(()=>!document.querySelector('dialog').open);
     assert.equal(await toggle.evaluate(x=>x===document.activeElement),true);
     await toggle.click();
     if(width===390)await page.screenshot({path:'/tmp/menu-'+route.split('?')[0]+(route.includes('guest')?'-guest':'')+'.png'});
     await page.getByRole('button',{name:'Close menu'}).click();
     await page.waitForFunction(()=>!document.documentElement.classList.contains('account-menu-open'));
    }else{assert.equal(await toggle.isVisible(),false);assert.equal(await page.locator('.account-desktop-nav').isVisible(),true);}
   }
   await page.close();console.log('Navigation and overflow checks passed: '+width);
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
