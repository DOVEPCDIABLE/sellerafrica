const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const {execFileSync}=require('node:child_process');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  for(const width of [390,768,1440]) {
   const page=await browser.newPage({viewport:{width,height:1000}});
   await page.setContent(execFileSync('php',['-n','tests/product_variations_preview.php'],{encoding:'utf8'}));
   await page.addStyleTag({path:'public/assets/css/role-dashboard.bp.css'});
   await page.addScriptTag({path:'public/assets/js/vendor-product-variations.js'});
   await page.addScriptTag({path:'public/assets/js/product-variation-picker.js'});
   await page.locator('[data-add-variation]').click();
   if(await page.locator('input[name="variations[0][size]"]').evaluate(el=>el.validity.valid))throw Error('Empty options accepted');
   await page.locator('input[name="variations[0][size]"]').fill('M');
   await page.locator('input[name="variations[0][colour]"]').fill('Red');
   await page.locator('input[name="variations[0][price]"]').fill('15');
   await page.locator('input[name="variations[0][stock]"]').fill('3');
   if(!await page.locator('input[name="variations[0][size]"]').evaluate(el=>el.validity.valid))throw Error('Valid options blocked');
   await page.locator('[data-add-cart]').first().dispatchEvent('click');
   if(await page.locator('[data-add-cart]').first().getAttribute('data-add-cart')!=='1')throw Error('Unselected variant changed');
   await page.locator('#sa-variation-choice').selectOption('101');
   if(await page.locator('[data-add-cart="101"]').count()!==2)throw Error('Cart IDs not updated');
   if(!await page.locator('#sa-variation-choice option[value="102"]').isDisabled())throw Error('Sold-out option enabled');
   if(await page.locator('[data-shipping-estimate]').getAttribute('data-product-id')!=='101')throw Error('Shipping ID incorrect');
   if(!await page.locator('.mt-shop-details__price').innerText().then(t=>t.includes('15')))throw Error('Price not updated');
   if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Overflow');
   await page.screenshot({path:`/tmp/variations-${width}.png`,fullPage:true});
   await page.getByText('Remove variation',{exact:true}).click();
   if(await page.locator('.product-variation-row').count())throw Error('Remove failed');
   await page.close();
  }
  console.log('Variation editor, picker, price, stock and responsive checks passed');
 } finally {await browser.close();}
})();
