<?php
declare(strict_types=1);
namespace App;

final class ProductVariationService
{
    public static function rows(int $parentId): array
    {
        return \db()->fetchAll("SELECT p.*, a.custom_value AS options_json, f.path AS image_path
            FROM products p
            LEFT JOIN product_attribute_assignments a ON a.product_id=p.id AND a.attribute_id=(SELECT id FROM product_attributes WHERE slug='sac-variation-options')
            LEFT JOIN product_media pm ON pm.id=(SELECT id FROM product_media WHERE product_id=p.id AND role='primary' ORDER BY id DESC LIMIT 1)
            LEFT JOIN files f ON f.id=pm.file_id
            WHERE p.parent_product_id=? AND p.type='variation' AND a.id IS NOT NULL AND p.status<>'archived' ORDER BY p.id", [$parentId]);
    }

    public static function validate(array $rows): array
    {
        if (count($rows)>50) throw new \RuntimeException('A product can have up to 50 variations.');
        $result=[]; $seen=[];
        foreach ($rows as $index=>$row) {
            if (!is_array($row) || !ctype_digit((string)$index)) throw new \RuntimeException('Invalid variation data.');
            foreach ($row as $value) {
                if (!is_scalar($value) && $value!==null) throw new \RuntimeException('Invalid variation field.');
            }
            $options=[];
            foreach (['Size','Colour','Length','Other'] as $label) {
                $value=trim((string)($row[strtolower($label)] ?? ''));
                if (strlen($value)>80) throw new \RuntimeException('Variation options must be 80 characters or shorter.');
                if ($value!=='') $options[$label]=$value;
            }
            $json=json_encode($options, JSON_THROW_ON_ERROR);
            if (!$options || strlen($json)>255) throw new \RuntimeException('Each variation needs at least one short size, colour, length or other option.');
            $key=strtolower($json);
            if (isset($seen[$key])) throw new \RuntimeException('Each variation must have a different combination of options.');
            $seen[$key]=true;
            $price=$row['price'] ?? '';
            $stock=$row['stock'] ?? '';
            if (!is_numeric($price) || !is_finite((float)$price) || (float)$price<=0 || (float)$price>99999999) throw new \RuntimeException('Every variation needs a valid price greater than zero.');
            if (!preg_match('/^\d{1,9}$/', (string)$stock)) throw new \RuntimeException('Every variation needs a whole-number stock quantity of zero or more.');
            $dimensions=[];
            foreach (['weight','length','width','height'] as $dimension) {
                $value=trim((string)($row['shipping_'.$dimension] ?? ''));
                if ($value!=='' && (!is_numeric($value) || !is_finite((float)$value) || (float)$value<=0)) throw new \RuntimeException('Variation shipping measurements must be greater than zero, or blank to use the main product.');
                $dimensions[$dimension]=$value==='' ? null : (float)$value;
            }
            $result[]=['index'=>(int)$index,'id'=>max(0,(int)($row['id'] ?? 0)),'options'=>$options,'json'=>$json,'price'=>(float)$price,'stock'=>(int)$stock,'dimensions'=>$dimensions];
        }
        if (array_sum(array_column($result,'stock'))>2147483647) throw new \RuntimeException('Total variation stock is too large.');
        return $result;
    }

    public static function posted(): ?array
    {
        if (!isset($_POST['variation_editor'])) return null;
        $rows=$_POST['variations'] ?? [];
        if (!is_array($rows)) throw new \RuntimeException('Invalid variations.');
        $validated=self::validate($rows);
        foreach ($validated as $row) {
            $file=$_FILES['variation_image_'.$row['index']] ?? null;
            if (!$file || (int)$file['error']===UPLOAD_ERR_NO_FILE) continue;
            if ((int)$file['error']!==UPLOAD_ERR_OK || (int)$file['size']>5242880 || !is_uploaded_file($file['tmp_name']) || !in_array(mime_content_type($file['tmp_name']),['image/jpeg','image/png','image/webp'],true)) {
                throw new \RuntimeException('Variation images must be JPG, PNG or WebP, up to 5 MB.');
            }
        }
        return $validated;
    }

    // Called inside the parent product transaction. Child IDs are retained for cart/order history.
    public static function save(int $parentId, int $vendorId, ?array $rows, callable $upload): void
    {
        if ($rows===null) return;
        $parent=\db()->fetch('SELECT * FROM products WHERE id=? AND vendor_id=? FOR UPDATE',[$parentId,$vendorId]);
        if (!$parent || $parent['type']==='variation') throw new \RuntimeException('Invalid parent product.');
        $existing=self::rows($parentId);
        if (!$existing && !$rows) return;
        $owned=array_fill_keys(array_column($existing,'id'),true);
        $used=[];
        foreach ($rows as $row) {
            if ($row['id'] && (!isset($owned[$row['id']]) || isset($used[$row['id']]))) throw new \RuntimeException('Invalid variation ownership.');
            if ($row['id']) $used[$row['id']]=true;
        }
        if ($rows) {
            \db()->query("INSERT IGNORE INTO product_attributes (name,slug,type,is_global) VALUES ('Variation options','sac-variation-options','text',0)");
            $attribute=(int)\db()->fetch("SELECT id FROM product_attributes WHERE slug='sac-variation-options'")['id'];
        }
        $kept=[];
        foreach ($rows as $row) {
            $parts=[];
            foreach ($row['options'] as $key=>$value) $parts[]=$key.': '.$value;
            $name=substr($parent['name'].' - '.implode(' / ',$parts),0,255);
            $params=[$name,$parent['description'],$row['price'],$row['stock'],$row['stock']>0?'in_stock':'out_of_stock'];
            foreach ($row['dimensions'] as $key=>$value) $params[]=$value ?? $parent[$key];
            $id=$row['id'];
            if ($id) {
                \db()->query("UPDATE products SET name=?,description=?,regular_price=?,stock_quantity=?,stock_status=?,weight=?,length=?,width=?,height=?,sale_price=NULL,updated_at=NOW() WHERE id=? AND parent_product_id=? AND vendor_id=?",[...$params,$id,$parentId,$vendorId]);
            } else {
                $slug='variation-'.$parentId.'-'.bin2hex(random_bytes(8));
                \db()->query("INSERT INTO products (name,description,regular_price,stock_quantity,stock_status,weight,length,width,height,vendor_id,parent_product_id,sku,slug,currency,type,status,visibility,manage_stock) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'variation','active','hidden',1)",[...$params,$vendorId,$parentId,$slug,$slug,$parent['currency']]);
                $id=(int)\db()->lastInsertId();
            }
            $kept[]=$id;
            \db()->query('UPDATE products SET shipping_class=?, ships_from_us_warehouse=?, tax_status=?, currency=? WHERE id=?',[$parent['shipping_class'],$parent['ships_from_us_warehouse'],$parent['tax_status'],$parent['currency'],$id]);
            \db()->query('DELETE FROM product_attribute_assignments WHERE product_id=? AND attribute_id=?',[$id,$attribute]);
            \db()->query('INSERT INTO product_attribute_assignments (product_id,attribute_id,custom_value,is_variation) VALUES (?,?,?,1)',[$id,$attribute,$row['json']]);
            $field='variation_image_'.$row['index'];
            $fileId=null;
            if (isset($_FILES[$field]) && (int)$_FILES[$field]['error']!==UPLOAD_ERR_NO_FILE) $fileId=$upload($field);
            if (!$fileId && !\db()->fetch("SELECT id FROM product_media WHERE product_id=? AND role='primary'",[$id])) {
                $fileId=\db()->fetch("SELECT file_id FROM product_media WHERE product_id=? AND role='primary' ORDER BY id DESC LIMIT 1",[$parentId])['file_id'] ?? null;
            }
            if ($fileId) {
                \db()->query("DELETE FROM product_media WHERE product_id=? AND role='primary'",[$id]);
                \db()->query("INSERT INTO product_media (product_id,file_id,role) VALUES (?,?,'primary')",[$id,$fileId]);
            }
        }
        foreach ($existing as $old) {
            if (!in_array((int)$old['id'],$kept,true)) \db()->query("UPDATE products SET status='archived',updated_at=NOW() WHERE id=?",[$old['id']]);
        }
        \db()->query('UPDATE products SET type=? WHERE id=?',[$rows?'variable':'simple',$parentId]);
        if ($rows) {
            $stock=array_sum(array_column($rows,'stock'));
            $price=min(array_column($rows,'price'));
            \db()->query('UPDATE products SET regular_price=?,sale_price=NULL,stock_quantity=?,stock_status=? WHERE id=?',[$price,$stock,$stock>0?'in_stock':'out_of_stock',$parentId]);
        }
    }

    public static function assertPurchasable(int $id, int $quantity): void
    {
        $p=\db()->fetch('SELECT * FROM products WHERE id=?',[$id]);
        if (!$p) throw new \DomainException('This product is no longer available.');
        if ($p['type']==='variable' && self::rows($id)) throw new \DomainException('Choose a product variation before adding it to your basket.');
        if ($p['type']!=='variation') return;
        if (!\db()->fetch("SELECT a.id FROM product_attribute_assignments a JOIN product_attributes t ON t.id=a.attribute_id WHERE a.product_id=? AND t.slug='sac-variation-options'",[$id])) return;
        $parent=\db()->fetch('SELECT p.status,p.type,p.vendor_id,v.status AS vendor_status,v.kyc_status FROM products p JOIN vendors v ON v.id=p.vendor_id WHERE p.id=?',[$p['parent_product_id']]);
        if (!$parent || $parent['type']!=='variable' || $parent['status']!=='active' || (int)$parent['vendor_id']!==(int)$p['vendor_id'] || $parent['vendor_status']!=='active' || $parent['kyc_status']!=='approved' || $p['status']!=='active') throw new \DomainException('This variation is not available for purchase.');
        if ($p['stock_status']==='out_of_stock' || $quantity>(int)$p['stock_quantity']) throw new \DomainException('The selected variation does not have enough stock.');
    }

    public static function picker(int $parentId): string
    {
        $parent=\db()->fetch('SELECT type FROM products WHERE id=?',[$parentId]);
        if (($parent['type'] ?? '')!=='variable') return '';
        $rows=self::rows($parentId);
        if (!$rows) return '';
        $options=[];
        foreach ($rows as $row) {
            if ($row['status']!=='active') continue;
            $labels=json_decode((string)$row['options_json'],true) ?: [];
            $parts=[]; foreach ($labels as $key=>$value) $parts[]=$key.': '.$value;
            $options[]=['id'=>(int)$row['id'],'label'=>implode(' / ',$parts) ?: $row['name'],'price'=>(float)$row['regular_price'],'currency'=>$row['currency'],'stock'=>(int)$row['stock_quantity'],'weight'=>$row['weight'],'length'=>$row['length'],'width'=>$row['width'],'height'=>$row['height'],'image'=>$row['image_path'] ? \app_brand_asset_url($row['image_path']) : ''];
        }
        $html='<div class="sa-variation-picker" data-variation-picker data-parent="'.$parentId.'"><label for="sa-variation-choice">Choose a variation</label><select id="sa-variation-choice" required><option value="">Select size, colour or other options</option>';
        foreach ($options as $option) $html.='<option value="'.$option['id'].'"'.($option['stock']<=0?' disabled':'').'>'.\e($option['label'].' - '.$option['currency'].' '.number_format($option['price'],2).($option['stock']<=0?' (Sold out)':'')).'</option>';
        $html.='</select><p data-variation-status role="status">Select an available variation before adding to your basket.</p><script type="application/json" data-variation-options>'.json_encode($options,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script></div>';
        return $html.'<style>.sa-variation-picker{margin:20px 0}.sa-variation-picker label{display:block;font-weight:600;margin-bottom:8px}.sa-variation-picker select{width:100%;max-width:100%;min-height:48px;border:1px solid #dceae2;border-radius:6px;padding:10px;background:white;color:#16372a}.sa-variation-picker p{font-size:14px}.sa-variation-picker :focus-visible{outline:2px solid #177d51;outline-offset:2px}</style><script src="'.\e(\asset('js/product-variation-picker.js?v=1')).'" defer></script>';
    }
}
