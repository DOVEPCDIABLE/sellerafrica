<?php
$variationEditor = [];
foreach ($variationRows as $row) {
    $options = json_decode((string)($row['options_json'] ?? '{}'), true) ?: [];
    $variationEditor[] = ['id'=>(int)$row['id'],'size'=>$options['Size'] ?? '', 'colour'=>$options['Colour'] ?? '', 'length'=>$options['Length'] ?? '', 'other'=>$options['Other'] ?? '', 'price'=>$row['regular_price'],'stock'=>$row['stock_quantity'],'shipping_weight'=>$row['weight'],'shipping_length'=>$row['length'],'shipping_width'=>$row['width'],'shipping_height'=>$row['height'],'image'=>$row['image_path'] ? app_brand_asset_url($row['image_path']) : ''];
}
?>
<fieldset class="product-variations" data-variation-editor>
 <legend>Product variations (optional)</legend>
 <input type="hidden" name="variation_editor" value="1">
 <div data-variation-rows></div>
 <button type="button" class="vendor-link-btn" data-add-variation><span aria-hidden="true">+</span> Add variation</button>
 <script type="application/json" data-variation-initial><?= json_encode($variationEditor, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
</fieldset>
<style>
.product-variations { min-width:0; border:1px solid #dceae2; padding:16px; border-radius:8px; }
.product-variations legend { font-weight:700; }
.product-variation-row { border-bottom:1px solid #dceae2; padding:16px 0; margin-bottom:16px; }
.product-variation-row h4 { margin:0 0 12px; }
.product-variation-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.product-variation-fields label { min-width:0; }
.product-variation-fields input { width:100%; min-width:0; }
.product-variation-row img { width:72px; height:72px; object-fit:contain; }
@media(max-width:600px){.product-variation-fields{grid-template-columns:1fr}}
</style>
<script src="<?= e(asset('js/vendor-product-variations.js?v=2')) ?>" defer></script>
