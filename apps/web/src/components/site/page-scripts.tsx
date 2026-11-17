import { jsonLd } from '@/lib/site/page';

/*
 * The two scripts the academy's page writes itself, kept apart from
 * components/site/page (whose test refuses any raw HTML there). Both carry
 * the request's nonce and are built from data, never from markup.
 */

/** Structured data for search engines (6.7): JSON with "<" escaped, so no text can close the script. */
export function JsonLd({ data, nonce }: { data: object[]; nonce?: string }) {
  return data.map((item, i) => (
    <script key={i} type="application/ld+json" nonce={nonce} dangerouslySetInnerHTML={{ __html: jsonLd(item) }} />
  ));
}

/**
 * The builder's preview (3.3): keeps the scroll position across reloads
 * (sessionStorage), or, with #b-{id} in the address, scrolls to that block
 * and outlines it a moment; then tells the builder its revision and height.
 */
const PREVIEW_SCRIPT = `(function(){var K='lms-preview-scroll';var s=document.currentScript;var rev=Number(s&&s.getAttribute('data-revision'))||0;
function post(){try{window.parent.postMessage({type:'lms-site-preview',revision:rev,height:document.documentElement.scrollHeight},'*')}catch(e){}}
window.addEventListener('pagehide',function(){try{sessionStorage.setItem(K,String(window.scrollY))}catch(e){}});
function go(){var h=location.hash;var el=h&&/^#[a-z][a-z0-9-]{0,40}$/.test(h)?document.getElementById(h.slice(1)):null;
if(el){el.scrollIntoView({block:'start'});el.setAttribute('data-preview-focus','');setTimeout(function(){el.removeAttribute('data-preview-focus')},1200)}
else{try{var y=Number(sessionStorage.getItem(K));if(y>0)window.scrollTo(0,y)}catch(e){}}post()}
if(document.readyState==='complete')go();else window.addEventListener('load',go);})();`;

export function PreviewScript({ revision, nonce }: { revision: number; nonce?: string }) {
  return <script nonce={nonce} data-revision={revision} dangerouslySetInnerHTML={{ __html: PREVIEW_SCRIPT }} />;
}
