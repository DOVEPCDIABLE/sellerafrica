const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 for(const width of [390,768,1440]){
  const page=await browser.newPage({viewport:{width,height:960}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8098/tests/distributor_preview.php');
  await page.locator('[data-next]').click();
  if(await page.locator('[data-panel="0"]').isHidden())throw Error('Missing details allowed');
  for(let step=0;step<5;step++){
   const panel=page.locator(`[data-panel="${step}"]`);
   if(await panel.isHidden())throw Error('Step hidden '+step);
   const inputs=panel.locator('input,select,textarea');
   for(let i=0;i<await inputs.count();i++){
    const input=inputs.nth(i);if(await input.getAttribute('readonly')!==null)continue;
    const tag=await input.evaluate(el=>el.tagName);const type=await input.getAttribute('type');const name=await input.getAttribute('name');
    if(tag==='SELECT')await input.selectOption({index:1});
    else if(type==='checkbox')await input.check();
    else await input.fill(type==='number'?(name==='year_founded'?'2020':'100'):type==='url'?'https://example.com':type==='tel'?'+12025550123':'Sample business');
   }
   if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error('Overflow '+width);
   await page.screenshot({path:`/tmp/distributor-${width}-step${step}.png`,fullPage:true});
   if(step<4)await page.locator('[data-next]').click();
  }
  await page.locator('.application-review summary').click();
  if(!await page.locator('#answer-summary').innerText())throw Error('Missing summary');
  if(errors.length)throw Error(errors.join('\n'));console.log('PASS five steps, validation, review, responsive '+width);await page.close();
 }
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
