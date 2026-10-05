const {chromium} = require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const fs = require('node:fs');
(async () => {
  const browser = await chromium.launch({channel:'chrome',headless:true});
  try {
    for (const route of ['marketplace','shop']) {
      for (const width of [390,768,1440]) {
        const page = await browser.newPage({viewport:{width,height:1000}});
        await page.goto(`https://sellerafrica.com/${route}`,{waitUntil:'domcontentloaded',timeout:60000});
        if (await page.locator('link[href*="storefront-refresh.css"]').count() !== 1) throw Error('Live refresh stylesheet missing');
        const before = await page.locator('h1,h2').allTextContents();
        const grids = await page.locator('.sa-shop-products,.sa-shop-grid,.sa-home-product-grid').evaluateAll(els=>els.map(el=>getComputedStyle(el).gridTemplateColumns));
        await page.addStyleTag({content:"@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap');"});
        await page.addStyleTag({content:fs.readFileSync('public/assets/css/storefront-refresh.css','utf8')});
        await page.evaluate(()=>document.fonts.ready);
        if(JSON.stringify(before)!==JSON.stringify(await page.locator('h1,h2').allTextContents())) throw Error('Section content changed');
        if(JSON.stringify(grids)!==JSON.stringify(await page.locator('.sa-shop-products,.sa-shop-grid,.sa-home-product-grid').evaluateAll(els=>els.map(el=>getComputedStyle(el).gridTemplateColumns)))) throw Error('Grid changed');
        if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)) throw Error(`Overflow: ${route} ${width}`);
        await page.screenshot({path:`/tmp/storefront-refresh-${route}-${width}.png`});
        console.log(route,width,'PASS',await page.locator('.sa-shop,.sa-home').first().evaluate(el=>getComputedStyle(el).fontFamily));
        await page.close();
      }
    }
  } finally { await browser.close(); }
})();
