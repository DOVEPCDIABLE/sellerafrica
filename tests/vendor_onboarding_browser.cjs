const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async () => {
 const browser = await chromium.launch({headless:true,channel:'chrome'});
 const errors=[];
 for (const width of [390,768,1440]) {
  const page=await browser.newPage({viewport:{width,height:960}});
  page.on('pageerror',e=>errors.push(e.message));
  for (const query of ['signup=1','step=0','step=1','step=2','state=pending','state=rejected','state=payment']) {
   await page.goto('http://127.0.0.1:8097/tests/vendor_onboarding_preview.php?'+query,{waitUntil:'domcontentloaded'});
   if ((await page.locator('body').innerText()).includes('Fatal error')) throw Error('PHP render failed '+query);
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);
   if(overflow)throw Error('Horizontal overflow '+width+' '+query);
   if(query==='signup=1') {
    if(await page.locator('[name=product_weight],[name=store_name],[type=file]').count())throw Error('Verification fields on signup');
    if(await page.locator('[name=password],[name=password_confirmation]').count()!==2)throw Error('Password fields missing');
   }
   if(query==='step=1' || query==='signup=1')await page.screenshot({path:'/tmp/vendor-onboarding-'+width+'-'+(query==='step=1'?'product':'signup')+'.png',fullPage:true});
   console.log('PASS',width,query);
  }
  await page.close();
 }
 await browser.close();
 if(errors.length)throw Error(errors.join('\n'));
})().catch(e=>{console.error(e);process.exit(1)});
