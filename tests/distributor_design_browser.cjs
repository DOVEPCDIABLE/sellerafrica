const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 for(const width of [390,768,1440]){
  const page=await browser.newPage({viewport:{width,height:960}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  for(const mode of ['landing','guest']){
   await page.goto(`http://127.0.0.1:8098/tests/distributor_preview.php?${mode}=1`);
   await page.evaluate(()=>document.fonts.ready);
   if((await page.locator('body').innerText()).includes('Warning:'))throw Error('PHP warning');
   if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error('Overflow '+width+' '+mode);
   if(mode==='guest'){
    if(await page.locator('a[href*="buyer/register"]').count())throw Error('Buyer registration link still present');
    if(await page.locator('#distribution-account-form').getAttribute('action')!=='/distributor/register')throw Error('Wrong registration action');
    await page.locator('[name=password]').fill('Example123');await page.locator('[data-reveal="account-password"]').click();
    if(await page.locator('[name=password]').getAttribute('type')!=='text')throw Error('Password toggle failed');
    await page.locator('[name=password_confirmation]').fill('wrong');
    if(await page.locator('[name=password_confirmation]').evaluate(e=>e.validity.valid))throw Error('Password mismatch accepted');
    await page.locator('[name=password_confirmation]').fill('Example123');
    if(!await page.locator('[name=password_confirmation]').evaluate(e=>e.validity.valid))throw Error('Password correction failed');
   }
   await page.screenshot({path:`/tmp/distribution-design-${mode}-${width}.png`,fullPage:true});
   console.log('PASS',mode,width);
  }
  if(errors.length)throw Error(errors.join('\n'));await page.close();
 }
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
