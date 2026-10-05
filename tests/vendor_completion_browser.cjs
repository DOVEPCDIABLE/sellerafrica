const { chromium } = require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const { execFileSync } = require('node:child_process');
(async () => {
  const html = execFileSync('php', ['-n', 'tests/vendor_completion_preview.php'], {encoding:'utf8'});
  const browser = await chromium.launch({channel:'chrome',headless:true});
  try {
    for (const width of [390, 768, 1440]) {
      const page = await browser.newPage({viewport:{width,height:1000}});
      await page.setContent(html);
      await page.addStyleTag({path:'public/assets/css/admin.css'});
      if (await page.locator('#vendor-completion').inputValue() !== '100') throw Error('Filter lost');
      if (!(await page.getByText('Next', {exact:true}).getAttribute('href')).includes('completion=100')) throw Error('Pagination lost filter');
      if (await page.locator('.pagination-size [name=completion]').inputValue() !== '100') throw Error('Page size lost filter');
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
      if (overflow) throw Error(`Overflow at ${width}`);
      await page.screenshot({path:`/tmp/vendor-completion-${width}.png`,fullPage:true});
      await page.close();
    }
    console.log('Completion layout and filter persistence passed: 390, 768, 1440');
  } finally { await browser.close(); }
})();
