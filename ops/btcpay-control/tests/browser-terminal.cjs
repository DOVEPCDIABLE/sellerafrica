const assert = require('assert');
const cp = require('child_process');
const path = require('path');
const {chromium} = require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const ssh = ['-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15','-i',process.env.HOME+'/.ssh/btcpay_admin','root@68.178.160.200'];
function bridge(action, input) {
  return JSON.parse(cp.execFileSync('ssh', [...ssh, '/usr/local/sbin/btcpay-control '+action], {input:JSON.stringify(input),encoding:'utf8'}));
}
(async () => {
  const ip = process.argv[2];
  assert(ip);
  const browser = await chromium.launch({channel:'chrome',headless:true});
  let lease;
  try {
    lease = bridge('terminal-open',{ip});
    assert(lease.ok);
    const html = cp.execFileSync('php',['-n',path.join(__dirname,'preview.php'),'terminal'],{input:lease.token,encoding:'utf8'});
    const page = await browser.newPage({viewport:{width:1440,height:1000}});
    page.on('pageerror',error=>console.error('Browser page error:',error.message));
    page.on('response',response=>{ if(response.url().includes(':8443') && response.status()>=400) console.error('Terminal HTTP:',response.status(),new URL(response.url()).pathname); });
    let output = '';
    page.on('websocket',ws => {
      ws.on('socketerror',error=>console.error('WebSocket:',error));
      ws.on('framereceived',event => {
        output += Buffer.isBuffer(event.payload) ? event.payload.toString('utf8') : event.payload;
      });
    });
    await page.route('https://sellerafrica.com/btcpay',route => route.fulfill({body:html,contentType:'text/html',headers:{'Content-Security-Policy':"default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-preview-nonce'; frame-src https://sellerafrica.com:8443; form-action 'self' https://sellerafrica.com:8443; frame-ancestors 'none'; base-uri 'none'"}}));
    await page.goto('https://sellerafrica.com/btcpay',{waitUntil:'domcontentloaded'});
    const shell = page.frameLocator('iframe');
    await shell.locator('.xterm-helper-textarea').waitFor({state:'attached',timeout:30000});
    for (let attempt=0; attempt<30 && !output.includes('#'); attempt++) await new Promise(resolve=>setTimeout(resolve,500));
    await shell.locator('.xterm-helper-textarea').focus();
    await page.keyboard.type("printf 'BROWSER_ROOT=%s\\n' \"$(whoami)\"; pwd");
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => true);
    for (let attempt=0; attempt<30 && !output.includes('BROWSER_ROOT=root'); attempt++) await new Promise(resolve=>setTimeout(resolve,500));
    if (!output.includes('BROWSER_ROOT=root')) {
      await page.screenshot({path:'/tmp/btcpay-root-browser-failure.png',fullPage:true});
      console.error('Received terminal output:',output.slice(-2000));
    }
    assert(output.includes('BROWSER_ROOT=root'),'Interactive browser root shell output missing');
    assert(output.includes('/root/btcpayserver-docker'));
    for (const width of [390,768,1440]) {
      await page.setViewportSize({width,height:1000});
      await page.locator('iframe').scrollIntoViewIfNeeded();
      assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
      await page.screenshot({path:`/tmp/btcpay-root-terminal-${width}.png`});
      console.log(`PASS Embedded browser root shell ${width}: rendered and interactive`);
    }
    assert(await page.evaluate(() => {
      try { return document.querySelector('iframe').contentWindow.document === null; }
      catch (error) { return error.name === 'SecurityError'; }
    }), 'Terminal must be isolated from the parent origin');
    console.log('PASS Browser same-origin policy isolates terminal');
    bridge('terminal-close',{token:lease.token});
    const response = await page.request.get('https://sellerafrica.com:8443/root-terminal/');
    assert.equal(response.status(),401);
    console.log('PASS Browser terminal access revoked');
  } finally {
    if (lease?.ok) bridge('terminal-close',{token:lease.token});
    await browser.close();
  }
})().catch(error=>{console.error(error);process.exit(1);});
