const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const {execFileSync}=require('node:child_process');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  for(const width of [390,1440]) {
   const page=await browser.newPage({viewport:{width,height:900}});
   await page.setContent(execFileSync('php',['-n','tests/vendor_verification_preview.php'],{encoding:'utf8'}));
   for(const name of ['store_name','store_banner','product_name','product_category_id','product_image','product_regular_price','product_weight','product_length','product_width','product_height','product_description','fulfillment_method','monthly_shipment','terms_consent']) {
    const input=page.locator(`[name="${name}"]`);
    if(!await input.isVisible() || !await input.evaluate(el=>el.required)) throw Error('Missing required field '+name);
   }
   await page.locator('button[value=submit]').click();
   if(await page.locator('#verification-form').evaluate(el=>el.checkValidity())) throw Error('Empty submission allowed');
   if(!await page.locator('[name=store_name]').getAttribute('aria-invalid')) throw Error('Missing inline validation');
   await page.locator('[name=product_category_id]').selectOption('4');
   if(!await page.locator('[name=product_category_id]').evaluate(el=>el.validity.valid)) throw Error('Valid category refused');
   for(const name of ['product_sale_price','business_registration','identity','social_page']) {
    if(await page.locator(`[name="${name}"]`).evaluate(el=>el.required)) throw Error('Optional field required');
   }
   if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)) throw Error('Overflow');
   await page.screenshot({path:`/tmp/verification-required-${width}.png`,fullPage:true});
   await page.close();
  }
  console.log('Required-field, optional-field, inline-error and responsive tests passed');
 } finally {await browser.close();}
})();
