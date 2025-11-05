/**
 * Consent Banner for CookieJar
 * Keeps: Do Not Sell category (opt-out), backdrop tint, GA Consent Mode, hash style exclusion.
 */
(function(){
  if(window.DWIC_BANNER_LOADED) return; window.DWIC_BANNER_LOADED=1;

  const CONFIG = window.DWIC_CONFIG || {};
  const TXT = window.DWIC_I18N || {};
  const COOKIE_NAME = CONFIG.cookieName || 'dwic_consent_v2';
  const CONFIG_VERSION = CONFIG.configVersion || '';
  const CONSENT_LIFETIME_DAYS = parseInt(CONFIG.duration||180,10);
  const REQUIRED_KEY='necessary';
  const OPT_OUT_SLUG='donotsell';

  function setCookie(name,value,days){
    try{
      const d=new Date(); d.setTime(d.getTime()+days*864e5);
      let c=`${name}=${encodeURIComponent(value)}; expires=${d.toUTCString()}; path=/; SameSite=Lax;`;
      if(location.protocol==='https:') c+=' secure;';
      document.cookie=c;
    }catch(e){}
  }
  function getCookie(name){
    const m=document.cookie.match(new RegExp('(?:^|; )'+name+'=([^;]*)'));
    return m?decodeURIComponent(m[1]):null;
  }
  function safeParse(j){ try{return JSON.parse(j);}catch(e){return null;} }

  function templateCategories(){
    const map={};
    (CONFIG.categories||[]).forEach(c=>{
      if(c.slug===OPT_OUT_SLUG) {
        map[c.slug]=false; // opt-out default (unchecked)
      } else {
        map[c.slug]=!!c.required;
      }
    });
    return map;
  }

  function computeType(cats){
    const keys = Object.keys(cats).filter(k=>k!==REQUIRED_KEY && k!==OPT_OUT_SLUG);
    const on = keys.filter(k=>cats[k]);
    if(on.length===0) return 'none';
    if(on.length===keys.length) return 'full';
    return 'partial';
  }

  const consentStore={
    readRaw(){ return getCookie(COOKIE_NAME); },
    read(){
      const raw=this.readRaw(); if(!raw) return null;
      const p=safeParse(raw); if(!p || !p.categories) return null;
      if(p.dns && !p.categories[OPT_OUT_SLUG]) p.categories[OPT_OUT_SLUG]=true; // legacy dns -> donotsell
      return p;
    },
    write(categories){
      const obj={version:CONFIG_VERSION,ts:Date.now(),categories,type:computeType(categories)};
      setCookie(COOKIE_NAME, JSON.stringify(obj), CONSENT_LIFETIME_DAYS);
      setCookie('dwic_analytics', categories.analytics?'1':'0', CONSENT_LIFETIME_DAYS);
      return obj;
    }
  };

  const existing = consentStore.read();
  const needsReconsent = !existing || existing.version !== CONFIG_VERSION;

  function bannerLogoHTML(){
    const icon = CONFIG.icon || '';
    return `<span class="dwic-logo" aria-hidden="true">
      <img src="${icon}" alt="">
    </span>`;
  }

  function categoryHTML(cat){
    return `<div class="dwic-cat" data-catwrap="${cat.slug}">
      <label>
        <input type="checkbox" data-cat="${cat.slug}" ${cat.required?'checked disabled':''}>
        <span class="dwic-toggle"></span>
        <b>${cat.name}</b>
      </label>
      <span class="dwic-desc">${cat.desc}</span>
    </div>`;
  }

  function buildCategoryPanel(){
    return (CONFIG.categories||[]).map(categoryHTML).join('');
  }

  function bannerHTML(){
    const scheme=CONFIG.style?.scheme || 'light';
    const position=CONFIG.style?.position || 'bottom';
    const font=CONFIG.style?.font || 'inherit';
    const branding = (typeof CONFIG.brandingHtml === 'string') ? CONFIG.brandingHtml : '';
    return `<div class="dwic-bar dwic-${scheme} dwic-${position}" role="dialog" aria-labelledby="dwic-banner-title" aria-modal="true">
      <div class="dwic-inner" style="font-family:${font}">
        ${bannerLogoHTML()}
        <span id="dwic-banner-title" class="dwic-msg">${CONFIG.message}</span>
        <div class="dwic-actions">
          <button type="button" class="dwic-btn dwic-accept">${TXT.accept||'Accept All'}</button>
          <button type="button" class="dwic-btn dwic-prefs">${TXT.preferences||'Preferences'}</button>
          <button type="button" class="dwic-btn dwic-reject">${TXT.reject||'Reject'}</button>
        </div>
        <div class="dwic-prefs-panel" style="display:none">
          <div class="dwic-always-on-note">${TXT.always_on||'Always On'}</div>
          ${buildCategoryPanel()}
          <button type="button" class="dwic-btn dwic-save">${TXT.save||'Save Preferences'}</button>
        </div>
        ${branding}
      </div>
    </div>`;
  }

  function ensureBackdrop(){
    if(document.getElementById('dwic-consent-backdrop')) return;
    const bd=document.createElement('div');
    bd.id='dwic-consent-backdrop';
    bd.className='dwic-backdrop';
    document.body.appendChild(bd);
  }

  function updateGtmConsent(cats){
    if(!CONFIG.enableConsentMode) return;
    if(typeof gtag!=='function'){ window.dataLayer=window.dataLayer||[]; function gtag(){ dataLayer.push(arguments); } }
    const analytics = !!cats.analytics;
    const functional= !!cats.functional;
    const adsBase   = !!cats.ads;
    const optedOut  = !!cats[OPT_OUT_SLUG];
    const adsAllowed = adsBase && !optedOut;
    gtag('consent','update',{
      'ad_storage': adsAllowed?'granted':'denied',
      'ad_user_data': adsAllowed?'granted':'denied',
      'ad_personalization': adsAllowed?'granted':'denied',
      'analytics_storage': analytics?'granted':'denied',
      'functionality_storage': functional?'granted':'denied',
      'personalization_storage': functional?'granted':'denied',
      'security_storage':'granted'
    });
  }

  function trap(modal){
    modal.addEventListener('keydown', e=>{
      if(e.key==='Escape'){ doReject(); return; }
      if(e.key!=='Tab') return;
      const f=modal.querySelectorAll('button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])');
      if(!f.length) return;
      const first=f[0], last=f[f.length-1];
      if(e.shiftKey && document.activeElement===first){ last.focus(); e.preventDefault(); }
      else if(!e.shiftKey && document.activeElement===last){ first.focus(); e.preventDefault(); }
    });
  }

  let lastFocused=null;
  function showBanner(){
    if(document.getElementById('dwic-consent-banner')) return;
    const root=document.getElementById('dwic-consent-banner-root');
    if(!root) return;
    lastFocused=document.activeElement;
    ensureBackdrop();
    root.innerHTML=bannerHTML();
    document.body.classList.add('dwic-consent-active');

    const banner=root.querySelector('.dwic-bar');
    banner.id='dwic-consent-banner';
    trap(banner);
    wireActions();

    const existing = consentStore.read();
    if(existing && existing.categories){
      Object.entries(existing.categories).forEach(([slug,on])=>{
        const input=banner.querySelector(`input[data-cat="${slug}"]`);
        if(input && !input.disabled) input.checked=!!on;
      });
    }
    const first=banner.querySelector('.dwic-btn.dwic-accept'); first && first.focus();
  }

  function hideBanner(){
    const root=document.getElementById('dwic-consent-banner-root');
    if(root) root.innerHTML='';
    const bd=document.getElementById('dwic-consent-backdrop');
    if(bd) bd.remove();
    document.body.classList.remove('dwic-consent-active');
    if(lastFocused){ try{ lastFocused.focus(); }catch(e){} }
  }

  function showRevisitBtn(){
    const btn=document.getElementById('dwic-revisit-btn');
    if(btn){
      btn.style.display='flex';
      btn.onclick=showBanner;
    }
  }

  function collectPartial(){
    const base=templateCategories();
    document.querySelectorAll('.dwic-cat input[type=checkbox]').forEach(cb=>{
      const slug=cb.getAttribute('data-cat');
      base[slug] = slug===REQUIRED_KEY ? true : cb.checked;
    });
    base[REQUIRED_KEY]=true;
    return base;
  }
  function fullCats(){
    const b=templateCategories();
    Object.keys(b).forEach(k=>{
      if(k===REQUIRED_KEY) b[k]=true;
      else if(k===OPT_OUT_SLUG) b[k]=false;
      else b[k]=true;
    });
    return b;
  }
  function noneCats(){
    const b=templateCategories();
    Object.keys(b).forEach(k=>{
      if(k===REQUIRED_KEY) b[k]=true;
      else if(k===OPT_OUT_SLUG) b[k]=true; // reject => opt-out
      else b[k]=false;
    });
    return b;
  }

  function logConsent(obj){
    try{
      fetch(CONFIG.ajaxurl,{
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=dwic_log&consent='+encodeURIComponent(obj.type)+
             '&categories='+encodeURIComponent(Object.keys(obj.categories).filter(k=>obj.categories[k]).join(','))+
             '&config_version='+encodeURIComponent(obj.version)
      });
    }catch(e){}
  }

  function finalize(cats){
    const obj=consentStore.write(cats);
    logConsent(obj);
    hideBanner();
    showRevisitBtn();
    updateGtmConsent(cats);
    document.dispatchEvent(new CustomEvent('dwic_consent_update',{detail:obj}));
  }

  function doAccept(){ finalize(fullCats()); }
  function doReject(){ finalize(noneCats()); }
  function doSave(){ finalize(collectPartial()); }

  function wireActions(){
    const accept=document.querySelector('.dwic-btn.dwic-accept');
    const reject=document.querySelector('.dwic-btn.dwic-reject');
    const prefs=document.querySelector('.dwic-btn.dwic-prefs');
    const save=document.querySelector('.dwic-btn.dwic-save');
    const panel=document.querySelector('.dwic-prefs-panel');
    const actions=document.querySelector('.dwic-actions');

    accept && (accept.onclick=doAccept);
    reject && (reject.onclick=doReject);
    prefs && (prefs.onclick=()=>{
      panel.style.display='';
      actions.style.display='none';
      const first=panel.querySelector('.dwic-cat input:not([disabled])');
      first && first.focus();
    });
    save && (save.onclick=doSave);
  }

  function init(){
    const existing = consentStore.read();
    document.addEventListener('DOMContentLoaded', function(){
      document.dispatchEvent(new CustomEvent('dwic_consent_loaded',{detail:existing}));
      if(!existing || existing.version !== CONFIG_VERSION){
        showBanner();
      } else {
        showRevisitBtn();
        if(existing) updateGtmConsent(existing.categories||{});
      }
    });
  }

  init();

  window.dwicConsent = {
    get: ()=>consentStore.read(),
    has: cat=>{
      const c=consentStore.read();
      return !!(c && c.categories && c.categories[cat]);
    },
    optedOutOfSale: ()=>{
      const c=consentStore.read();
      return !!(c && c.categories && c.categories[OPT_OUT_SLUG]);
    },
    revoke: ()=>{ doReject(); },
    open: ()=>{ showBanner(); },
    onChange: cb => document.addEventListener('dwic_consent_update', e=>cb(e.detail))
  };
})();