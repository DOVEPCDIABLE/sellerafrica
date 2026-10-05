<?php
$independenceToday = (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->format('Y-m-d');
if ($independenceToday !== '2026-10-01') return;
?>
<dialog id="sa-independence" aria-labelledby="sa-independence-title" aria-describedby="sa-independence-message">
    <button type="button" class="sa-independence-close" aria-label="Close Independence Day greeting" autofocus>&times;</button>
    <div class="sa-nigeria-flag" role="img" aria-label="Flag of Nigeria: green, white and green"></div>
    <p class="sa-independence-date">OCTOBER 1, 2026</p>
    <div class="sa-independence-years" aria-label="66 years of independence">66<span>YEARS OF INDEPENDENCE</span></div>
    <h2 id="sa-independence-title">Happy Independence Day,<br>Nigeria!</h2>
    <p id="sa-independence-message">Celebrating our people, our resilience, and the possibilities ahead. To Nigerians at home and around the world: may we keep building, creating, and growing together.</p>
    <p class="sa-independence-signoff">With love from Seller Africa<br><span>Discover. Distribute. Deliver.</span></p>
    <button type="button" class="sa-independence-continue">Continue exploring</button>
</dialog>
<style>
#sa-independence{box-sizing:border-box;width:calc(100% - 32px);max-width:500px;max-height:calc(100dvh - 32px);overflow:auto;margin:auto;padding:36px 28px 28px;border:1px solid #c9dfd1;border-radius:8px;background:#fff;color:#14372a;text-align:center;font-family:inherit;box-shadow:0 20px 70px #001d1640}
#sa-independence::backdrop{background:#071c16b8}
#sa-independence .sa-independence-close{position:absolute;right:10px;top:10px;width:44px;height:44px;padding:0;border:0;border-radius:50%;background:#eef5f0;color:#14372a;font:30px/1 Arial,sans-serif;cursor:pointer}
#sa-independence .sa-nigeria-flag{width:96px;height:48px;margin:8px auto 20px;background:linear-gradient(to right,#008751 0 33.333%,#fff 33.333% 66.666%,#008751 66.666% 100%);border:1px solid #d9e6df}
#sa-independence .sa-independence-date{font-size:12px;font-weight:700;margin:0 0 12px;letter-spacing:0;color:#4d695b}
#sa-independence .sa-independence-years{font-size:84px;font-weight:800;line-height:1;color:#008751}
#sa-independence .sa-independence-years span{display:block;margin-top:10px;font-size:12px;line-height:1.4;color:#547062}
#sa-independence h2{font-size:28px;line-height:1.2;font-weight:700;margin:24px 0 16px;letter-spacing:0;color:#14372a}
#sa-independence-message{font-size:16px;line-height:1.6;color:#4a6055;margin:0}
#sa-independence .sa-independence-signoff{font-size:14px;line-height:1.6;margin:22px 0;font-weight:600}
#sa-independence .sa-independence-signoff span{font-size:12px;font-weight:400}
#sa-independence .sa-independence-continue{width:100%;min-height:48px;border:1px solid #006d42;border-radius:6px;padding:12px 16px;background:#006d42;color:white;font:inherit;font-weight:600;cursor:pointer}
#sa-independence button:focus-visible{outline:3px solid #c29c30;outline-offset:3px}
#sa-independence button:hover{filter:brightness(.94)}
@media(max-width:400px){#sa-independence{padding:28px 20px 20px}#sa-independence h2{font-size:25px}#sa-independence .sa-independence-years{font-size:68px}}
</style>
<script>
(()=>{
 const dialog=document.getElementById('sa-independence');
 const key='sa-nigeria-independence-2026-seen';
 if(!dialog || typeof dialog.showModal!=='function')return;
 const today=new Intl.DateTimeFormat('en-CA',{timeZone:'Africa/Lagos',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
 if(today!=='2026-10-01')return;
 try{if(localStorage.getItem(key))return;}catch(e){}
 const open=()=>{
  if(document.querySelector('dialog[open]'))return;
  const previous=document.activeElement;
  dialog.showModal();
  try{localStorage.setItem(key,'1');}catch(e){}
  dialog.addEventListener('close',()=>{if(previous && typeof previous.focus==='function')previous.focus();},{once:true});
 };
 dialog.querySelectorAll('button').forEach(button=>button.addEventListener('click',()=>dialog.close()));
 dialog.addEventListener('click',event=>{if(event.target!==dialog)return;const r=dialog.getBoundingClientRect();if(event.clientX<r.left || event.clientX>r.right || event.clientY<r.top || event.clientY>r.bottom)dialog.close();});
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',open,{once:true});else open();
})();
</script>
