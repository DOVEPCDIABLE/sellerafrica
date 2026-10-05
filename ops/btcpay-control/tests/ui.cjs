const fs = require('fs');
const assert = require('assert');
const {chromium} = require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({channel:'chrome', headless:true});
  try {
    for (const mode of ['login','dashboard']) for (const width of [390,768,1440]) {
      const page = await browser.newPage({viewport:{width,height:1000}});
      await page.setContent(fs.readFileSync(`/tmp/btcpay-${mode}-preview.html`, 'utf8'));
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
      if (mode === 'dashboard') {
        assert.equal(await page.getByRole('link',{name:'Open WHM Terminal'}).getAttribute('href'), 'https://236.175.178.68.host.secureserver.net:2087/scripts12/terminal');
        assert.equal(await page.locator('#terminal-phrase').count(),1);
        assert.equal(await page.locator('#terminal-code').count(),1);
        assert.equal(await page.locator('[name=root_confirm]').count(),1);
        assert.equal(await page.getByRole('button',{name:'Unlock root terminal'}).count(),1);
      } else {
        assert.equal(await page.locator('#root-terminal-panel').count(),0);
      }
      await page.screenshot({path:`/tmp/btcpay-root-${mode}-${width}.png`,fullPage:true});
      console.log(`PASS ${mode} ${width}: no overflow and correct protected controls`);
      await page.close();
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
