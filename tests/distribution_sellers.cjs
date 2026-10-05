const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  for(const width of [390,768,1440]) {
   const page=await browser.newPage({viewport:{width,height:1000},reducedMotion:width===768?'reduce':'no-preference'});
   await page.goto('https://sellerafrica.com/distributors',{waitUntil:'domcontentloaded'});
   const slider=page.locator('[data-seller-slider]');
   await slider.scrollIntoViewIfNeeded();
   if(!await slider.evaluate(el=>{let prev=el.previousElementSibling;while(prev&&prev.tagName!=='SECTION')prev=prev.previousElementSibling;return prev?.getAttribute('aria-labelledby')==='dist-retail-ready-title';}))throw Error('Wrong position');
   if(await slider.locator('img').count()!==15)throw Error('Missing logos');
   await slider.locator('img').evaluateAll(imgs=>Promise.all(imgs.map(img=>{img.loading='eager';return img.decode();})));
   const track=slider.locator('.dist-sellers-track');
   if(width===768) {
    if(await slider.locator('[data-sellers-pause]').getAttribute('aria-label')!=='Play slider')throw Error('Reduced motion ignored');
   }else{
    await page.waitForFunction(()=>document.querySelector('.dist-sellers-track').scrollLeft>0,{},{timeout:7000});
    await slider.locator('[data-sellers-pause]').click();
   }
   await track.evaluate(el=>el.scrollTo({left:0,behavior:'instant'}));
   await slider.locator('[data-sellers-next]').click();
   await page.waitForFunction(()=>document.querySelector('.dist-sellers-track').scrollLeft>10);
   await slider.locator('[data-sellers-prev]').click();
   await page.waitForFunction(()=>document.querySelector('.dist-sellers-track').scrollLeft<2);
   if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Page overflow');
   await slider.screenshot({path:`/tmp/our-sellers-${width}.png`});
   console.log(`${width}: all logos loaded, placement, controls and motion checks passed`);
   await page.close();
  }
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
