const {chromium}=require('/Users/abayomidaniel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');
const {execFileSync}=require('node:child_process');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try{for(const width of [390,768,1440]){
  const p=await browser.newPage({viewport:{width,height:900}});
  await p.setContent(execFileSync('php',['-n','tests/admin_payment_links_preview.php'],{encoding:'utf8'}));
  await p.addStyleTag({content:'*{box-sizing:border-box}body{font:16px Arial;margin:20px}.panel{padding:20px;max-width:100%;border:1px solid #ddd;margin-bottom:20px}input,select,button{font:inherit}td,th{padding:12px;text-align:left}button{padding:12px}'});
  await p.locator('#link-service').selectOption('priority_monthly');
  if(await p.locator('#link-provider').inputValue()!=='stripe')throw Error('Provider not switched');
  if(!await p.locator('#link-provider option[value=paystack]').evaluate(o=>o.disabled && o.hidden))throw Error('Unsupported provider selectable');
  if(await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1))throw Error('Overflow '+width);
  await p.screenshot({path:'/tmp/payment-links-'+width+'.png',fullPage:true});
  console.log('PASS responsive and provider selection '+width);await p.close();
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
