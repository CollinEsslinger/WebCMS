// Run against the isolated fixture server: php -d extension=pdo_sqlite -S 127.0.0.1:8099 tests/router.php
import assert from 'node:assert/strict';
const base = process.argv[2] || 'http://127.0.0.1:8099';
assert.ok(['127.0.0.1','localhost'].includes(new URL(base).hostname), 'Tests must target localhost');
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }
function client() {
  const jar = new Map();
  return async (path, data, headers = {}) => {
    const response = await fetch(base + path, { method: data ? 'POST':'GET', redirect:'manual', headers: { Cookie:[...jar].map(([k,v]) => `${k}=${v}`).join('; '), ...headers }, body: data ? new URLSearchParams(data) : undefined });
    for (const cookie of response.headers.getSetCookie()) { const pair = cookie.split(';')[0]; const i = pair.indexOf('='); jar.set(pair.slice(0,i),pair.slice(i+1)); }
    return { status:response.status, location:response.headers.get('location'), type:response.headers.get('content-type'), text:await response.text() };
  };
}
const csrf = html => html.match(/name="_csrf" value="([^"]+)"/)?.[1] || html.match(/name="csrf-token" content="([^"]+)"/)?.[1];
const version = html => html.match(/name="version" value="([^"]+)"/)?.[1];
async function login(request, name) { const page = await request('/admin/login.php'); const result = await request('/admin/login.php',{_csrf:csrf(page.text),username:name,password:'local-test-only'}); check(result.status===302,'login succeeds: '+name); const dashboard=await request('/admin/dashboard.php'); return csrf(dashboard.text); }
const admin=client(), author=client(), member=client(), scoped=client(), anon=client();
const token=await login(admin,'test-admin');
for (const path of ['/admin/dashboard.php','/admin/','/admin/content.php','/admin/content.php?kind=event&new=1','/admin/forms.php?new=1','/admin/workspace.php?view=tasks','/admin/workspace.php?view=templates','/admin/workspace.php?view=inbox','/admin/workspace.php?view=seo','/admin/workspace.php?view=analytics','/admin/workspace.php?view=system','/admin/workspace.php?view=trash','/admin/history.php?id=1','/admin/permissions.php?id=1','/admin/editor.php?id=1','/admin/preview.php?id=1','/admin/media.php','/admin/users.php','/admin/settings.php']) {
  const r=await admin(path); check(r.status===200 && !/Fatal error|Warning:|Parse error|Deprecated:/.test(r.text),'admin screen renders: '+path);
}
for (const path of ['/assets/css/site.css','/assets/css/admin.css','/assets/css/workspace.css','/assets/js/editor.js']) { const r=await anon(path); check(r.status===200 && r.text.length>100,'asset served: '+path); }
check((await anon('/admin/dashboard.php')).status===302,'anonymous admin redirected');
for (const path of ['/config.php','/core/cms.php','/tests/config.php','/tests/.runtime/database.sqlite','/storage/database.sqlite','/.git/config']) check((await anon(path)).status===404,'private path blocked: '+path);
check((await admin('/admin/page_save.php',{title:'No CSRF'})).status===403,'write requires CSRF');
const stamp=Date.now();
const pageData={_csrf:token,title:'HTTP '+stamp,slug_part:'http-'+stamp,status:'published',language:'de',blocks_json:JSON.stringify([{id:'contact',type:'form',settings:{heading:'Testkontakt',fields:[{label:'E-Mail',type:'email',required:true}]}}])};
let save=await admin('/admin/page_save.php',pageData,{Accept:'application/json'}); const saved=JSON.parse(save.text); check(save.status===200 && saved.id>0,'page save endpoint persists'); const id=saved.id;
let edit=await admin('/admin/editor.php?id='+id); const v=version(edit.text);
save=await admin('/admin/page_save.php',{...pageData,id,version:v,title:'Arbeitsentwurf '+stamp,status:'review'},{Accept:'application/json'}); check(JSON.parse(save.text).ok,'page submitted for review');
let publicPage=await anon('/http-'+stamp); check(publicPage.status===200 && publicPage.text.includes('HTTP '+stamp) && !publicPage.text.includes('Arbeitsentwurf '+stamp),'public endpoint retains approved title');
save=await admin('/admin/page_save.php',{...pageData,id,version:v,title:'Veralteter Stand'},{Accept:'application/json'}); check(save.status===400 && JSON.parse(save.text).message.includes('inzwischen'),'optimistic save conflict returned');
check((await anon('/admin/preview.php?id='+id)).status===302,'draft preview requires login');
let invalid=await anon('/submit.php',{_csrf:csrf(publicPage.text),action:'block_form',page_id:id,block_id:'contact',field_0:'invalid'}); check(invalid.status===422,'public form validates server-side');
let valid=await anon('/submit.php',{_csrf:csrf(publicPage.text),action:'block_form',page_id:id,block_id:'contact',field_0:'test@example.org'}); check(valid.status===302,'legacy form saves a real submission');
let inbox=await admin('/admin/workspace.php?view=inbox'); check(inbox.text.includes('test@example.org'),'submission visible in inbox');
let form=await admin('/admin/action.php',{_csrf:token,action:'form.save',return_to:'/admin/forms.php?new=1',title:'HTTP Form '+stamp,status:'published',success_message:'Angekommen', 'fields[0][label]':'Anfrage','fields[0][type]':'text','fields[0][required]':'1'});
const formId=new URL(form.location).searchParams.get('id'); check(Number(formId)>0,'form builder endpoint persists fields');
let formPage=await anon('/modules.php?form='+formId); check(formPage.status===200 && formPage.text.includes('Anfrage'),'managed form rendered');
valid=await anon('/submit.php',{_csrf:csrf(formPage.text),action:'form',form_id:formId,field_0:'Integrationstest'}); check(valid.status===302,'managed form submits');
check((await anon('/submit_result.php')).text.includes('Angekommen'),'custom confirmation rendered after redirect');
const authorToken=await login(author,'test-author');
check((await author('/admin/forms.php')).status===403,'author cannot manage forms');
save=await author('/admin/page_save.php',{...pageData,_csrf:authorToken,title:'Author publish'},{Accept:'application/json'}); check(save.status===400,'author cannot publish');
save=await author('/admin/page_save.php',{...pageData,_csrf:authorToken,title:'Author draft',status:'draft'},{Accept:'application/json'}); check(save.status===200,'author can save drafts');
const memberToken=await login(member,'test-member');
check((await member('/admin/editor.php?id=1')).status===403,'member cannot edit');
check((await member('/admin/media_delete.php',{_csrf:memberToken,id:1})).status===403,'member cannot mutate media');
await login(scoped,'test-scoped'); check((await scoped('/admin/editor.php?id=1')).status===403,'scoped editor cannot open another area'); check((await scoped('/admin/editor.php?id=2')).status===200,'scoped editor can open permitted area');
const privateData={...pageData,title:'Private '+stamp,slug_part:'private-'+stamp,access_role:'members'};
save=await admin('/admin/page_save.php',privateData,{Accept:'application/json'}); check(save.status===200,'protected page created');
check((await anon('/private-'+stamp)).status===404,'anonymous cannot read protected page'); check((await member('/private-'+stamp)).status===200,'member can read protected page');
for (const path of ['/api.php?resource=pages','/sitemap.php','/search.php?q=Private']) check(!(await member(path)).text.includes('Private '+stamp) || path.startsWith('/search.php'),'protected page excluded from anonymous feeds even with session');
const api=JSON.parse((await anon('/api.php?resource=pages')).text); check(api.items.every(p=>!('blocks_json' in p) && !('published_json' in p)),'public API projects approved fields');
check(!(await anon('/search.php?q=Private')).text.includes('Private '+stamp),'anonymous search cannot leak protected page');
check((await anon('/feed.php?format=ics')).text.includes('BEGIN:VCALENDAR'),'calendar endpoint works'); check((await anon('/feed.php?format=rss')).text.includes('<rss'),'RSS endpoint works');
const contentExport=await admin('/admin/export.php?type=content'); check(JSON.parse(contentExport.text).version==='2.0.0','content export works'); check(!contentExport.text.includes('password_hash'),'content export excludes credentials');
check((await member('/admin/export.php?type=content')).status===403,'content export requires admin');
console.log(`PASS: ${checks} HTTP integration assertions.`);
