// Real POS -> HTTPS gateway -> Napay login, using only the dedicated local fixture account.
const fs=require('node:fs'); const path=require('node:path');
const cp=require('node:child_process'); const crypto=require('node:crypto');
const {chromium}=require(process.env.PLAYWRIGHT_PACKAGE || 'playwright');
const {localState}=require('./local-state.cjs'); const state=localState('NAPAY_LOCAL_STATE'); const origin='https://pos.localhost';
let checks=0;let stage='TLS';
function check(value,label){if(!value)throw new Error(label);++checks;console.log('PASS: '+label);}
(async()=>{
 const ca=path.join(state,'caddy-root.crt');
 cp.execFileSync('curl',['--noproxy','*','--cacert',ca,'--resolve','pos.localhost:443:127.0.0.1','--fail','--silent',origin+'/health']);
 const certificate=cp.execFileSync('openssl',['s_client','-connect','127.0.0.1:443','-servername','pos.localhost','-showcerts'],{input:'',stdio:['pipe','pipe','ignore']});
 const pub=cp.execFileSync('openssl',['x509','-pubkey','-noout'],{input:certificate});
 const der=cp.execFileSync('openssl',['pkey','-pubin','-outform','der'],{input:pub});
 const pin=crypto.createHash('sha256').update(der).digest('base64');
 const password=fs.readFileSync(path.join(state,'browser-password'),'utf8').trim();
 const key=fs.readFileSync(path.join(state,'pos-client-key'),'utf8').trim();
 const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'/usr/bin/google-chrome',headless:true,args:['--ignore-certificate-errors-spki-list='+pin]});
 try{
  const context=await browser.newContext({serviceWorkers:'block'});
  await context.route('**/*',route=>new URL(route.request().url()).origin===origin?route.continue():route.abort());
  const page=await context.newPage(); const errors=[];page.on('pageerror',error=>errors.push(error.name));
  page.setDefaultTimeout(15000);
  stage='anonymous';await page.goto(origin+'/site/account');
  check(new URL(page.url()).pathname==='/site/login','Anonymous account page requires login');
  for(const width of[390,1280]){await page.setViewportSize({width,height:950});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Login responsive at '+width);}
  const before=await context.cookies(); const oldId=before.find(item=>item.name==='POSSESSID');
  const csrf=await page.locator('[name="_pos_csrf"]').inputValue();
  async function post(values){return page.evaluate(async values=>{const r=await fetch('/site/login',{method:'POST',body:new URLSearchParams(values)});return{status:r.status,body:await r.text()};},values);}
  check((await post([['OperatorLoginForm[email]','x@example.invalid'],['OperatorLoginForm[password]','x']])).status===400,'Browser login requires CSRF');
  check((await post([['_pos_csrf',csrf],['OperatorLoginForm[email][]','bad'],['OperatorLoginForm[password][]','bad']])).status===200,'Array login fields rejected without 500');
  const malformed=await post([['_pos_csrf',csrf],['OperatorLoginForm','bad']]);
  check(malformed.status===400,'Malformed login structure rejected');
  stage='invalid credentials';
  await page.locator('#operator-email').fill('browser-admin@example.invalid');
  const wrong='wrong-'+crypto.randomBytes(12).toString('hex');await page.locator('#operator-password').fill(wrong);
  await Promise.all([page.waitForNavigation(),page.locator('#operator-login button[type=submit]').click()]);
  check(await page.locator('.error').innerText().then(text=>text.includes('Email o password non validi')),'Real backend invalid credentials shown generically');
  check(await page.locator('#operator-password').inputValue()==='','Password field cleared after failure');
  check(!(await page.content()).includes(wrong),'Failed password never echoed');
  stage='valid credentials';
  await page.locator('#operator-email').fill('browser-admin@example.invalid');await page.locator('#operator-password').fill(password);
  await Promise.all([page.waitForNavigation(),page.locator('#operator-login button[type=submit]').click()]);
  check(new URL(page.url()).pathname==='/site/account','Real Napay API login succeeds');
  const html=await page.content();
  check(!html.includes(password)&&!html.includes(key)&&!/napos_[A-Za-z0-9_-]{43}/.test(html),'No password, client key or backend token in HTML');
  check(html.includes('Nessun POS aperto'),'Login does not claim terminal grants or opening');
  check(html.includes('Il logout revoca la sessione Napay'),'Central logout behavior is explicit');
  const cookies=await context.cookies();const session=cookies.find(item=>item.name==='POSSESSID');
  check(session&&session.value!==oldId.value,'Browser session ID rotated after login');
  check(session.secure&&session.httpOnly&&session.sameSite==='Strict','Operator cookie secure and host isolated');
  check(!JSON.stringify(cookies).includes('napos_')&&!JSON.stringify(cookies).includes(key),'Backend credentials absent from browser cookies');
  const oldContext=await browser.newContext();await oldContext.addCookies(before);const oldPage=await oldContext.newPage();
  await oldPage.goto(origin+'/site/account');check(new URL(oldPage.url()).pathname==='/site/login','Pre-login session cannot access authenticated account');await oldContext.close();
  await page.goto(origin+'/site/logout');check((await page.goto(origin+'/site/account')).status()===200&&new URL(page.url()).pathname==='/site/account','GET logout does not change authenticated state');
  stage='central logout';
  const missingLogoutCsrf=await page.evaluate(async()=>{const r=await fetch('/site/logout',{method:'POST'});return r.status;});
  check(missingLogoutCsrf===400,'Logout without CSRF rejected');
  await Promise.all([page.waitForNavigation(),page.locator('#operator-logout button[type=submit]').click()]);
  check(new URL(page.url()).pathname==='/site/login','CSRF-protected central logout completes');
  await page.goto(origin+'/site/account');check(new URL(page.url()).pathname==='/site/login','Local account inaccessible after logout');
  check(errors.length===0,'No uncaught login JavaScript errors');
  await context.close();console.log('PASS '+checks+' real POS/Napay login browser checks');
 }finally{await browser.close();}
})().catch(()=>{console.error('FAIL POS login stage '+stage+'; credentials and raw diagnostics suppressed');process.exitCode=1;});
