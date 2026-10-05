const {chromium} = require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const live = process.argv.includes('--live');
(async () => {
  const browser = await chromium.launch({channel:'chrome',headless:true});
  try {
    for (const route of ['vendors','about','contact']) {
      for (const width of [390,768,1440]) {
        const page = await browser.newPage({viewport:{width,height:1000}});
        const response = await page.goto(`https://sellerafrica.com/${route}`,{waitUntil:'domcontentloaded'});
        if (response.status() !== 200) throw Error(`${route}: HTTP ${response.status()}`);
        const images = () => page.locator('main img').evaluateAll(els=>els.map(el=>el.getAttribute('src')));
        const before = await images();
        if (!live) {
          await page.addStyleTag({url:'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap'});
          const style = await page.addStyleTag({path:'public/assets/css/public-pages-refresh.css'});
          await style.evaluate(el=>document.body.append(el));
        }
        await page.evaluate(()=>document.fonts.ready);
        if (JSON.stringify(before)!==JSON.stringify(await images())) throw Error('Image selection changed');
        if (await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1)) throw Error(`${route} ${width}: overflow`);
        const font = await page.locator(`.sa-${route}`).evaluate(el=>getComputedStyle(el).fontFamily);
        if (!font.includes('Manrope')) throw Error(`${route}: wrong font ${font}`);
        if (width<1100) {
          await page.getByRole('button',{name:'Toggle navigation menu'}).click();
          if (!await page.locator(`.sa-${route}-links`).isVisible()) throw Error('Mobile navigation failed');
          await page.getByRole('button',{name:'Toggle navigation menu'}).click();
        }
        await page.screenshot({path:`/tmp/public-${route}-${width}.png`});
        console.log(`${route} ${width}: typography, images, overflow and navigation passed`);
        await page.close();
      }
    }
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
