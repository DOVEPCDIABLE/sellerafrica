const { chromium } = require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [390, 768, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      for (const variant of ['', '?error=1&captcha=1', '?mfa=1', '?as=distributor']) {
        await page.goto('http://127.0.0.1:8098/tests/login_preview.php' + variant);
        await page.evaluate(() => document.fonts.ready);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        assert.equal(await page.locator('[name=csrf_token]').inputValue(), 'preview-token');
        await page.locator('[type=submit]').click();
        assert.equal(await page.locator('[type=submit]').isEnabled(), true);
        if (variant.includes('mfa')) {
          await page.locator('#mfa_code').fill('123');
          assert.equal(await page.locator('form').evaluate(f => f.checkValidity()), false);
          await page.locator('#mfa_code').fill('123456');
          assert.equal(await page.locator('form').evaluate(f => f.checkValidity()), true);
        } else {
          await page.locator('[data-sa-password-toggle]').click();
          assert.equal(await page.locator('#password').getAttribute('type'), 'text');
          await page.locator('[data-sa-password-toggle]').click();
          assert.equal(await page.locator('#password').getAttribute('type'), 'password');
        }
        if (variant.includes('error')) assert.equal(await page.locator('[role=alert]').count(), 1);
        if (variant.includes('distributor')) assert.equal(await page.locator('.login-signup a').getAttribute('href'), '/distributor/register');
        if (!variant) await page.screenshot({ path: `/tmp/login-${width}.png`, fullPage: true });
      }
      await page.goto('http://127.0.0.1:8098/tests/login_preview.php');
      await page.locator('#email').fill('preview@example.com');
      await page.locator('#password').fill('fixture-password');
      await page.route('**/tests/login_preview.php', async route => {
        const data = new URLSearchParams(route.request().postData());
        assert.equal(data.get('csrf_token'), 'preview-token');
        assert.equal(data.get('intent'), 'password');
        assert.equal(data.get('password'), 'fixture-password');
        await route.fulfill({ body: 'Fixture submission passed' });
      });
      await page.locator('[type=submit]').click();
      await page.getByText('Fixture submission passed').waitFor();
      assert.deepEqual(errors, []);
      await page.close();
      console.log(`Login layout, validation, and POST checks passed: ${width}px`);
    }
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
