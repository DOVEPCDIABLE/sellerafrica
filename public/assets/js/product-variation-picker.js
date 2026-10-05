(() => {
 const picker=document.querySelector('[data-variation-picker]');if(!picker)return;
 const select=picker.querySelector('select');
 const options=JSON.parse(picker.querySelector('[data-variation-options]').textContent);
 const buttons=[...document.querySelectorAll(`[data-add-cart="${picker.dataset.parent}"],[data-buy-now="${picker.dataset.parent}"]`)];
 const status=picker.querySelector('[data-variation-status]');
 let chosen=null;
 buttons.forEach(button=>button.setAttribute('aria-disabled','true'));
 document.addEventListener('click',event=>{
  const button=event.target.closest('[data-add-cart],[data-buy-now]');
  if(!buttons.includes(button))return;
  if(!chosen){event.preventDefault();event.stopImmediatePropagation();select.reportValidity();select.focus();return;}
 },true);
 select.addEventListener('change',()=>{
  chosen=options.find(option=>String(option.id)===select.value && option.stock>0)||null;
  buttons.forEach(button=>{
   button.setAttribute('aria-disabled',String(!chosen));
   if(button.hasAttribute('data-add-cart'))button.dataset.addCart=chosen?.id||picker.dataset.parent;
   if(button.hasAttribute('data-buy-now'))button.dataset.buyNow=chosen?.id||picker.dataset.parent;
  });
  if(!chosen){status.textContent='Select an available variation before adding to your basket.';return;}
  status.textContent=`${chosen.stock} available. Shipping weight: ${chosen.weight} kg. Package: ${chosen.length} x ${chosen.width} x ${chosen.height} cm.`;
  document.querySelectorAll('.mt-shop-details__price span[data-sa-price],.sa-sticky-cart-bar__price span[data-sa-price]').forEach(el=>{
   el.dataset.saPrice=chosen.price;el.dataset.saCurrency=chosen.currency;el.textContent=new Intl.NumberFormat(undefined,{style:'currency',currency:chosen.currency}).format(chosen.price);
  });
  document.querySelectorAll('.mt-shop-details__price del,.sa-sticky-cart-bar__price del').forEach(el=>el.hidden=true);
  document.querySelectorAll('.mt-shop-details__right-warp>.mt-shop-details__product-info-2,.sa-product-shipping-meta').forEach(el=>el.hidden=true);
  if(chosen.image)document.querySelectorAll('.mt-shop-details__tab-content-box .tab-pane.active img,.sa-sticky-cart-bar__inner>img').forEach(img=>{img.src=chosen.image;img.removeAttribute('srcset');img.alt=chosen.label;});
  const shipping=document.querySelector('[data-shipping-estimate]');
  if(shipping){shipping.dataset.productId=chosen.id;const result=shipping.querySelector('[data-shipping-estimate-result]');if(result)result.textContent='Recalculate shipping for the selected variation.';}
 });
})();
