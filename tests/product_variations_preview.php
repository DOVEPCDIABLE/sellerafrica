<?php
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../app/services/ProductVariationService.php';
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function asset($v){return '/assets/'.$v;}
function app_brand_asset_url($v){return $v;}
function db(){return new class {
 public function fetch($sql,$args=[]){return ['type'=>'variable'];}
 public function fetchAll($sql,$args=[]){return [['id'=>101,'status'=>'active','options_json'=>'{"Size":"M","Colour":"Red"}','name'=>'Test M Red','regular_price'=>15,'currency'=>'USD','stock_quantity'=>3,'weight'=>1,'length'=>2,'width'=>3,'height'=>4,'image_path'=>'https://sellerafrica.com/assets/images/product-placeholder.svg'],['id'=>102,'status'=>'active','options_json'=>'{"Size":"L","Colour":"Blue"}','name'=>'Test L Blue','regular_price'=>20,'currency'=>'USD','stock_quantity'=>0,'weight'=>2,'length'=>3,'width'=>4,'height'=>5,'image_path'=>'']];}
};}
echo '<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="font-family:Arial;padding:16px"><form>';
$variationRows=[];
require __DIR__.'/../app/views/vendor/product-variations.php';
echo '</form><hr><h2>Product options</h2>'.App\ProductVariationService::picker(1).'<div class="mt-shop-details__price"><span data-sa-price="10">$10</span></div><a href="#" data-add-cart="1">Add to cart</a><div class="sa-sticky-cart-bar"><a href="#" data-add-cart="1">Add to cart</a></div><form data-shipping-estimate data-product-id="1"><p data-shipping-estimate-result></p></form></body></html>';
