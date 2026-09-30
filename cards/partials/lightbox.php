<?php
// cards/partials/lightbox.php - Version: 260916.14
//
// Shared PhotoLightbox partial. Previously this identical 74-line script was
// pasted verbatim into cards/business.php, id.php, qr.php,
// visiting.php and cards/numerology/views/head.php (verified byte-identical, same MD5 in
// all five), so any fix had to be applied in five places or the copies would
// silently drift.
//
// USAGE - place this one line immediately before the closing body tag:
//     require __DIR__ . '/partials/lightbox.php';
//   wrapped in PHP tags. From cards/numerology/views/ use
//   dirname(__DIR__, 2) . '/partials/lightbox.php' instead.
//
// Activation is automatic: any element carrying a data-lightbox-src attribute
// becomes clickable. Optional data-lightbox-name sets the suggested download
// filename. No initialisation call is needed.
//
// v1.1 FIX: the v1.0 header showed the usage example as a literal
// '<' + '?php ... ?' + '>' snippet inside a // comment. A closing PHP tag
// terminates PHP mode even inside a single-line comment, so the rest of the
// header was emitted to the page as raw text. Never put a closing PHP tag
// inside a // or # comment.
//
// A direct HTTP request to this file returns an empty response rather than
// leaking the script source.
if (!defined('BASE_PATH')) exit;
?>
<script>
/* ── PhotoLightbox V1.0 ─────────────────────────────────────────
   Auto-activates on elements with data-lightbox-src attribute.
   Supports same-origin images with Save As + suggested filename.
───────────────────────────────────────────────────────────────*/
(function(w){'use strict';
var LB={_ov:null,_kh:null,
init:function(){
  document.querySelectorAll('[data-lightbox-src]').forEach(function(el){
    el.style.cursor='zoom-in';
    el.removeEventListener('click',el._lbh||function(){});
    el._lbh=function(e){e.preventDefault();e.stopPropagation();
      LB.open(el.dataset.lightboxSrc,el.dataset.lightboxName||'photo');};
    el.addEventListener('click',el._lbh);
  });
},
open:function(src,name){
  if(LB._ov)LB.close();
  var ext=(src.match(/\.(\w{2,5})(?:\?.*)?$/)||[,'jpg'])[1].toLowerCase();
  var safe=name.trim().replace(/[^\w\s]/g,'').trim().replace(/\s+/g,'_')||'photo';
  var fname=safe+'.'+ext;
  var ov=document.createElement('div');
  ov.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.88);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;padding:20px;';
  var img=document.createElement('img');
  img.src=src;img.alt=name;
  img.style.cssText='max-height:78vh;max-width:90vw;border-radius:12px;box-shadow:0 30px 70px rgba(0,0,0,.7);object-fit:contain;transition:opacity .2s;';
  var row=document.createElement('div');row.style.cssText='display:flex;gap:10px;align-items:center;';
  var sb=document.createElement('button');
  sb.innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Save As <small style="opacity:.7;font-weight:500;font-size:10px">('+fname+')</small>';
  sb.style.cssText='padding:9px 18px;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;';
  sb.onmouseover=function(){this.style.background='#4338ca';};
  sb.onmouseout=function(){this.style.background='#4f46e5';};
  sb.onclick=function(){LB._save(src,fname,sb);};
  var cb=document.createElement('button');
  cb.innerHTML='&#10005; Close';
  cb.style.cssText='padding:9px 18px;background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;';
  cb.onmouseover=function(){this.style.background='rgba(255,255,255,.2)';};
  cb.onmouseout=function(){this.style.background='rgba(255,255,255,.1)';};
  cb.onclick=function(){LB.close();};
  var lbl=document.createElement('span');
  lbl.textContent=name;
  lbl.style.cssText='color:rgba(255,255,255,.5);font-size:11px;font-weight:600;letter-spacing:.05em;max-width:90vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
  row.appendChild(sb);row.appendChild(cb);
  ov.appendChild(img);ov.appendChild(row);ov.appendChild(lbl);
  document.body.appendChild(ov);LB._ov=ov;
  ov.addEventListener('click',function(e){if(e.target===ov)LB.close();});
  LB._kh=function(e){if(e.key==='Escape')LB.close();};
  document.addEventListener('keydown',LB._kh);
  document.body.style.overflow='hidden';
},
_save:async function(src,fname,btn){
  var orig=btn.innerHTML;btn.textContent='Saving\u2026';btn.disabled=true;
  try{
    var r=await fetch(src,{mode:'cors',credentials:'same-origin'});
    if(!r.ok)throw new Error('fetch');
    var blob=await r.blob();
    var u=URL.createObjectURL(blob);
    var a=document.createElement('a');a.href=u;a.download=fname;
    document.body.appendChild(a);a.click();document.body.removeChild(a);
    URL.revokeObjectURL(u);
    btn.innerHTML='\u2713 Saved!';btn.style.background='#059669';
    setTimeout(function(){btn.innerHTML=orig;btn.style.background='#4f46e5';btn.disabled=false;},2500);
  }catch(e){
    var a2=document.createElement('a');a2.href=src;a2.download=fname;a2.target='_blank';
    document.body.appendChild(a2);a2.click();document.body.removeChild(a2);
    btn.innerHTML=orig;btn.disabled=false;
  }
},
close:function(){
  if(LB._ov){LB._ov.remove();LB._ov=null;document.body.style.overflow='';
  if(LB._kh)document.removeEventListener('keydown',LB._kh);}
}};
w.PhotoLightbox=LB;
document.addEventListener('DOMContentLoaded',function(){LB.init();});
})(window);
</script>
