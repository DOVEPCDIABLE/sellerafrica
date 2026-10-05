(() => {
 const editor=document.querySelector('[data-variation-editor]');
 if(!editor) return;
 const list=editor.querySelector('[data-variation-rows]');
 let index=0;
 const add=(data={})=>{
  if(list.children.length>=50) return;
  const n=index++;
  const row=document.createElement('section'); row.className='product-variation-row';
  const heading=document.createElement('h4');heading.textContent=`Variation ${n+1}`;row.append(heading);
  const id=document.createElement('input');id.type='hidden';id.name=`variations[${n}][id]`;id.value=data.id||0;row.append(id);
  const fields=document.createElement('div');fields.className='product-variation-fields';row.append(fields);
  const inputs={};
  for(const [key,title,type,required] of [['size','Size','text',false],['colour','Colour','text',false],['length','Length option (include unit)','text',false],['other','Other option','text',false],['price','Variation price (USD) *','number',true],['stock','Stock quantity *','number',true],['shipping_weight','Shipping weight (kg), optional','number',false],['shipping_length','Package length (cm), optional','number',false],['shipping_width','Package width (cm), optional','number',false],['shipping_height','Package height (cm), optional','number',false]]) {
   const label=document.createElement('label'); label.className='vendor-field';label.textContent=title;
   const input=document.createElement('input');input.type=type;input.name=`variations[${n}][${key}]`;input.value=data[key]??'';input.required=required;
   if(type==='number'){input.min=key==='stock'?'0':'0.01';input.step=key==='stock'?'1':'0.01';}else input.maxLength=80;
   if(key.startsWith('shipping_'))input.placeholder='Use main product';
   inputs[key]=input;label.append(input);fields.append(label);
  }
  const check=()=>inputs.size.setCustomValidity(['size','colour','length','other'].some(k=>inputs[k].value.trim())?'':'Enter at least one size, colour, length or other option.');
  ['size','colour','length','other'].forEach(k=>inputs[k].addEventListener('input',check));check();
  const label=document.createElement('label');label.className='vendor-field';label.textContent='Variation image (optional, JPG/PNG/WebP, maximum 5 MB)';
  const file=document.createElement('input');file.type='file';file.name=`variation_image_${n}`;file.accept='image/jpeg,image/png,image/webp';label.append(file);row.append(label);
  const image=document.createElement('img');image.alt='Variation preview';image.hidden=!data.image;if(data.image)image.src=data.image;row.append(image);
  let preview;
  file.addEventListener('change',()=>{
   const f=file.files[0];file.setCustomValidity(f&&f.size>5242880?'Choose an image of 5 MB or smaller.':'');file.reportValidity();
   if(preview)URL.revokeObjectURL(preview);
   if(f&&file.validity.valid){preview=URL.createObjectURL(f);image.src=preview;image.hidden=false;}
  });
  const remove=document.createElement('button');remove.type='button';remove.className='vendor-link-btn';remove.textContent='Remove variation';remove.addEventListener('click',()=>{if(preview)URL.revokeObjectURL(preview);row.remove();});row.append(remove);
  list.append(row);
 };
 JSON.parse(editor.querySelector('[data-variation-initial]').textContent).forEach(add);
 editor.querySelector('[data-add-variation]').addEventListener('click',()=>add());
})();
