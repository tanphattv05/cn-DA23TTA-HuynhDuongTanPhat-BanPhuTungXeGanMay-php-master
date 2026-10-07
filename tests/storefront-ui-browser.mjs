// Optional read-only visual smoke test. Node 22+ and installed Edge; no npm packages.
import {spawn} from 'node:child_process';
import {mkdtemp,readFile,writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
const base=process.env.MOTOPARTS_UI_BASE_URL || 'http://localhost/cn-DA23TTA-HuynhDuongTanPhat-BanPhuTungXeGanMay-php-master/scr/';
const profile=await mkdtemp(join(tmpdir(),'motoparts-ui-browser-'));
const edge=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',[
  '--headless=new','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1',
  '--no-first-run',`--user-data-dir=${profile}`,'about:blank'
],{stdio:'ignore',windowsHide:true});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
let ws; let count=0;
const diagnose=process.argv.includes('--diagnose');
const check=(ok,label)=>{if(!ok){if(diagnose){console.log('DIAGNOSTIC FAIL: '+label);return;}throw Error(label);}count++;console.log('PASS: '+label);};
try {
  let port;
  for(let i=0;i<100;i++){try{port=(await readFile(join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0];break;}catch{await delay(100);}}
  if(!port)throw Error('Edge debugging port unavailable');
  const target=await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`,{method:'PUT'})).json();
  ws=new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
  let id=0; const pending=new Map();
  ws.onmessage=event=>{const message=JSON.parse(event.data);if(pending.has(message.id)){const p=pending.get(message.id);pending.delete(message.id);clearTimeout(p.timer);message.error?p.reject(Error(message.error.message)):p.resolve(message.result);}};
  const send=(method,params={})=>new Promise((resolve,reject)=>{const key=++id;const timer=setTimeout(()=>{pending.delete(key);reject(Error('CDP timeout '+method));},15000);pending.set(key,{resolve,reject,timer});ws.send(JSON.stringify({id:key,method,params}));});
  const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.text+': '+expression);return r.result.value;};
  await send('Page.enable');
  await send('Emulation.setFocusEmulationEnabled',{enabled:true});
  for(const width of [390,768,1440]) {
    await send('Emulation.setDeviceMetricsOverride',{width,height:960,deviceScaleFactor:1,mobile:false});
    for(const path of ['', 'pages/products.php','pages/product-detail.php?id=1','pages/cart.php','pages/login.php','pages/register.php']) {
      await send('Page.navigate',{url:base+path});
      let ready=false;
      for(let i=0;i<300;i++) {if(await evaluate('document.readyState === "complete" && !!document.querySelector(".storefront-body")')){ready=true;break;}await delay(100);}
      check(ready,`Loaded ${width} ${path||'home'}`);
      await evaluate('document.fonts.ready.then(()=>true)');
      if(process.argv.includes('--stale-css')) {
        // Controlled reproduction of the pre-phase16 stylesheet; browser DOM only.
        await evaluate(`document.querySelector('link[href*="assets/css/style.css"]').sheet.disabled=true;`);
        await evaluate('(()=>{const e=document.createElement("style");e.textContent="body { background-color: #f8f9fa; } .navbar-brand { letter-spacing: 0.5px; } .card-img-top { background-color: #fff; }";document.head.append(e);})()');
      }
      const styles=await evaluate(`(() => {
        const q=s=>document.querySelector(s), rect=e=>{const r=e.getBoundingClientRect();return {top:r.top,bottom:r.bottom,height:r.height};};
        const bg=e=>getComputedStyle(e).backgroundColor,color=e=>getComputedStyle(e).color;
        return {body:q('body').className,ink:getComputedStyle(q('body')).getPropertyValue('--mp-ink').trim(),skip:rect(q('.skip-link')),skipDisplay:getComputedStyle(q('.skip-link')).display,header:rect(q('header')),main:rect(q('main')),navBg:bg(q('.navbar')),headerBg:bg(q('header')),stripBg:bg(q('.brand-strip')),navColor:color(q('.nav-link')),footerBg:bg(q('footer')),footerColor:color(q('footer')),css:q('link[href*="assets/css/style.css"]').href,links:[...document.querySelectorAll('link[rel="stylesheet"]')].map(e=>e.href)};
      })()`);
      console.log('COMPUTED '+JSON.stringify({width,path,...styles}));
      check(styles.body.split(' ').includes('storefront-body') && styles.ink==='#202328',`Body namespace/variables ${width} ${path}`);
      check(styles.skip.bottom<=0 && styles.skipDisplay!=='none',`Skip offscreen ${width} ${path}`);
      await evaluate('document.querySelector(".skip-link").focus()');
      await delay(100);
      check(await evaluate('(()=>{const e=document.querySelector(".skip-link"),r=e.getBoundingClientRect();return r.top>=0 && r.bottom<=innerHeight && Number(getComputedStyle(e).zIndex)>=1000 && document.activeElement===e;})()'),`Skip visible on focus ${width} ${path}`);
      await evaluate('document.querySelector(".skip-link").blur();scrollTo(0,0)');
      check(styles.navBg==='rgb(29, 32, 37)' && styles.headerBg==='rgb(29, 32, 37)' && styles.stripBg==='rgb(18, 20, 24)',`Opaque navbar/header ${width} ${path}`);
      const luminance=c=>{const a=c.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return a[0]*.2126+a[1]*.7152+a[2]*.0722;};
      const contrast=(a,b)=>{const x=luminance(a),y=luminance(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);};
      check(contrast(styles.navColor,styles.headerBg)>=4.5,`Navbar contrast ${width} ${path}`);
      check(styles.header.top===0 && styles.header.height>=80 && styles.header.height<=190 && Math.abs(styles.main.top-styles.header.bottom)<=1,`Header/main geometry ${width} ${path}`);
      check(styles.footerBg==='rgb(29, 32, 37)' && contrast(styles.footerColor,styles.footerBg)>=4.5,`Footer contrast ${width} ${path}`);
      const cssResponse=await fetch(styles.css);
      check(cssResponse.status===200 && /^text\/css(?:;|$)/i.test(cssResponse.headers.get('content-type')||''),`CSS HTTP/MIME ${width} ${path}`);
      check(await cssResponse.text()===await readFile(new URL('../scr/assets/css/style.css',import.meta.url),'utf8'),`CSS matches source ${width} ${path}`);
      check(styles.links.findIndex(s=>s.includes('bootstrap.min.css'))<styles.links.indexOf(styles.css),`CSS order ${width} ${path}`);
      check(await evaluate('getComputedStyle(document.querySelector(".navbar")).display === "flex"'),`Bootstrap loaded ${width} ${path}`);
      check(await evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'),`No page overflow ${width} ${path}`);
      check(await evaluate('Array.from(document.querySelectorAll("a.btn-danger,a.btn-dark")).every(button=>getComputedStyle(button).color === "rgb(255, 255, 255)")'),`Readable solid button links ${width} ${path}`);
      if(width!==768) {
        const metrics=await send('Page.getLayoutMetrics');
        const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width,height:metrics.cssContentSize.height,scale:1}});
        await writeFile(join(profile,`${path?path.split('/').pop().split('.')[0]:'home'}-${width}.png`),Buffer.from(shot.data,'base64'));
      }
      if(width===390) {
        await evaluate('document.querySelector(".navbar-toggler").click()'); await delay(500);
        check(await evaluate('document.querySelector(".navbar-toggler").getAttribute("aria-expanded")==="true" && document.querySelector("#mainNavbar").classList.contains("show")'),`Menu opens ${path}`);
        await evaluate('document.querySelector(".navbar-toggler").click()'); await delay(500);
        check(await evaluate('document.querySelector(".navbar-toggler").getAttribute("aria-expanded")==="false" && !document.querySelector("#mainNavbar").classList.contains("show")'),`Menu closes ${path}`);
      }
    }
  }
  // Real GET navigation through the catalog, without submitting any write form.
  for(const width of [390,768,1440]) {
    await send('Emulation.setDeviceMetricsOverride',{width,height:960,deviceScaleFactor:1,mobile:false});
    const navigate=async path=>{const url=base+path;await send('Page.navigate',{url});for(let n=0;n<300;n++){if(await evaluate(`location.href === ${JSON.stringify(url)} && document.readyState === 'complete' && !!document.querySelector('#catalog-filters')`))return;await delay(100);}throw Error('Catalog navigation timeout');};
    await navigate('pages/products.php');
    check(await evaluate('(()=>{const f=document.querySelector("#catalog-filters");return f.method==="get" && !f.querySelector("[name=page]") && [...f.querySelectorAll("input,select")].every(e=>e.id && document.querySelector(`label[for="${e.id}"]`));})()'),`Catalog GET labels ${width}`);
    const q=process.env.MOTOPARTS_CATALOG_QUERY || await evaluate('document.querySelector(".product-card .card-title")?.textContent.trim().slice(0,60) || ""');
    await navigate('pages/products.php?'+new URLSearchParams({q,sort:'price_asc',page:'999999999'}));
    check(await evaluate(`document.querySelector('[name="q"]').value === ${JSON.stringify(q)} && document.querySelector('[name="sort"]').value === 'price_asc'`),`Catalog URL state ${width}`);
    check(await evaluate('Number(document.querySelector("#catalog-results").dataset.total)>0 && [...document.querySelectorAll(".product-card .card-title")].every(e=>e.textContent.toLowerCase().includes(document.querySelector("[name=q]").value.toLowerCase()))'),`Catalog matching results ${width}`);
    check(await evaluate('(()=>{const r=document.querySelector("#catalog-results");return r.dataset.page===r.dataset.pages && (Number(r.dataset.pages)<=1 || document.querySelectorAll(".catalog-pagination [aria-current=page]").length===1);})()'),`Catalog clamp/pagination aria ${width}`);
    if(process.env.MOTOPARTS_CATALOG_QUERY) check(await evaluate('Number(document.querySelector("#catalog-results").dataset.pages)>1 && document.querySelectorAll(".product-card").length===5'),`Catalog isolated last page ${width}`);
    check(await evaluate('document.documentElement.scrollWidth<=document.documentElement.clientWidth'),`Catalog filtered no overflow ${width}`);
    if(process.env.MOTOPARTS_CATALOG_QUERY) {
      const combined={q,min_price:'0',max_price:'9999999999',stock:'all',sort:'price_asc'};
      await navigate('pages/products.php?'+new URLSearchParams(combined));
      for(const page of [1,2,3]) {
        check(await evaluate(`Number(document.querySelector('#catalog-results').dataset.page)===${page} && document.querySelectorAll('.product-card').length===${page===3?5:12}`),`Catalog clicked page ${page} ${width}`);
        check(await evaluate(`(()=>{const u=new URL(location.href);return Object.entries(${JSON.stringify(combined)}).every(([k,v])=>(u.searchParams.get(k)??(k==="stock"?"all":null))===v);})()`),`Catalog clicked filters ${page} ${width}`);
        check(await evaluate('(()=>{const f=document.querySelector("#catalog-filters").getBoundingClientRect();return document.documentElement.scrollWidth<=innerWidth && [...document.querySelectorAll(".product-card,.catalog-pagination")].every(e=>{const r=e.getBoundingClientRect();return r.left>=0 && r.right<=innerWidth+1 && r.top>=f.bottom && getComputedStyle(e).display!=="none";});})()'),`Catalog geometry page ${page} ${width}`);
        if(page<3) {
          const target=await evaluate(`(()=>{const a=[...document.querySelectorAll('.catalog-pagination a')].find(a=>a.textContent.trim()===String(${page+1}));const u=a.href;a.click();return u;})()`);
          for(let n=0;n<300;n++){if(await evaluate(`location.href===${JSON.stringify(target)} && document.readyState==='complete' && Number(document.querySelector('#catalog-results')?.dataset.page)===${page+1}`))break;await delay(100);}
        }
      }
      await evaluate('document.querySelector("#catalog-q").focus()');
      await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
      await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
      check(await evaluate('document.activeElement.id==="catalog-category" && getComputedStyle(document.activeElement).boxShadow!=="none"'),`Catalog keyboard focus ${width}`);
    }
    await navigate('pages/products.php?q=NoSuchCatalog17DefinitelyAbsent');
    check(await evaluate('document.querySelector(".empty-state")!==null && document.querySelector("#catalog-results").dataset.total === "0"'),`Catalog empty results ${width}`);
    const cleared=await evaluate('document.querySelector("#catalog-filters a").getAttribute("href")');
    check(cleared==='products.php',`Catalog clear URL ${width}`);
    await navigate('pages/'+cleared);
    check(await evaluate('document.querySelector("[name=q]").value === "" && location.search === ""'),`Catalog clear navigation ${width}`);
    check(await evaluate(`document.querySelector('link[href*="assets/css/style.css"]').href.includes('?v=')`),`Catalog CSS version retained ${width}`);
  }
  console.log(`Completed ${count} browser checks. Screenshots and isolated profile: ${profile}`);
} finally {
  if(ws?.readyState===WebSocket.OPEN) {ws.send(JSON.stringify({id:999999,method:'Browser.close'}));ws.close();}
  // The headless process/profile belong only to this test; no real browser profile.
  edge.kill();
}
