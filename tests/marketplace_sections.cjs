const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  for(const width of [390,1440]) {
   const page=await browser.newPage({viewport:{width,height:1000}});
   const response=await page.goto('https://sellerafrica.com/marketplace',{waitUntil:'domcontentloaded'});
   if(response.status()!==200)throw Error('Marketplace failed');
   for(const selector of ['.sa-shop-hero','[data-shop-slider]','.sa-shop-intersection-banner']) {
    if(selector==='.sa-shop-intersection-banner') {
     await page.locator(selector+' img').first().evaluate(el=>el.scrollIntoView({block:'center'}));
     await page.waitForFunction(()=>document.querySelector('.sa-shop-intersection-banner img')?.naturalWidth>0);
    }
    if(!await page.locator(selector).first().isVisible())throw Error(`Missing ${selector}`);
   }
   for(const title of ['Sales & Deals','New Arrivals','Featured Products','Best Sellers','Suggested for You','Deals and Savings']) {
    if(!await page.getByRole('heading',{name:title,exact:true}).isVisible())throw Error(`Missing ${title}`);
   }
   if(await page.locator('.sa-shop-focused-page').count())throw Error('Default page still focused');
   if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error('Horizontal overflow');
   await page.evaluate(()=>scrollTo(0,0));
   await page.screenshot({path:`/tmp/marketplace-full-${width}.png`});
   await page.goto('https://sellerafrica.com/marketplace?q=rice',{waitUntil:'domcontentloaded'});
   if(!await page.locator('.sa-shop-focused-page').count())throw Error('Search no longer focused');
   console.log(`${width}: full marketplace sections, banners and focused search passed`);
   await page.close();
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
