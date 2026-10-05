const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const {execFileSync}=require('node:child_process');
(async()=>{
 const html=execFileSync('php',['-n','-r','echo "<!doctype html><html><head><meta name=viewport content=width=device-width,initial-scale=1></head><body><button>Page action</button>";require "app/views/partials/nigeria-independence.php";echo "</body></html>";'],{encoding:'utf8'});
 const b=await chromium.launch({channel:'chrome',headless:true});
 try{for(const width of [390,768,1440]){
  const p=await b.newPage({viewport:{width,height:800}});
  await p.clock.install({time:new Date('2026-10-01T12:00:00Z')});
  await p.route('https://popup.test/**',r=>r.fulfill({contentType:'text/html',body:html}));
  await p.goto('https://popup.test/distributors');
  if(!await p.locator('dialog').evaluate(d=>d.open))throw Error('Popup not open');
  if(!await p.locator('dialog').evaluate(d=>d.scrollWidth<=d.clientWidth+1))throw Error('Horizontal overflow');
  await p.screenshot({path:`/tmp/independence-${width}.png`});
  await p.keyboard.press('Escape');
  if(await p.locator('dialog').evaluate(d=>d.open))throw Error('Escape failed');
  await p.goto('https://popup.test/marketplace');
  if(await p.locator('dialog').evaluate(d=>d.open))throw Error('Repeated popup');
  await p.evaluate(()=>localStorage.clear());
  await p.clock.setFixedTime(new Date('2026-10-02T12:00:00Z'));
  await p.reload();
  if(await p.locator('dialog').evaluate(d=>d.open))throw Error('Expired popup showing');
  console.log('PASS popup, dismissal, once-per-visitor and expiry '+width);await p.close();
 }}finally{await b.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
