<?php

$settings = parse_ini_file('avactis-system/config.php');

$c = mysql_connect( $settings['DB_SERVER'],	$settings['DB_USER'], $settings['DB_PASSWORD'] ) or die('Cannot connect to database :(');
mysql_select_db( $settings['DB_NAME'] ) or die('Cannot select the needed database :(');

$store_settings = $settings['DB_TABLE_PREFIX'].'store_settings';
$product_attributes = $settings['DB_TABLE_PREFIX'].'product_attributes';
$products = $settings['DB_TABLE_PREFIX'].'products';
$manufacturers = $settings['DB_TABLE_PREFIX'].'manufacturers';
$products_to_categories = $settings['DB_TABLE_PREFIX'].'products_to_categories';
$categories_descr = $settings['DB_TABLE_PREFIX'].'categories_descr';

function getSetting( $setting )
{
	global $store_settings;
	$query = "SELECT variable_value FROM $store_settings WHERE variable_name = '$setting'";
	$result = mysql_query( $query ) or die(mysql_error());
	$row = mysql_fetch_assoc( $result );
	return htmlentities( trim( $row['variable_value'] ) );
}

$store_title = getSetting( 'store_owner_name' );
$store_link = getSetting( 'store_owner_website' );

echo '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
    <channel>'."
        <title>$store_title</title>
        <link>$store_link</link>
        <description>$store_title product catalog</description>";

$query = "SELECT

p.product_id AS id,
p.product_name AS title,
a1.product_attr_value AS price,
a9.product_attr_value AS image_link,
a3.product_attr_value AS quantity,
a12.product_attr_value AS description,
a23.product_attr_value AS seo_prefix,
m.manufacturer_name AS brand,
cd.category_name AS category

FROM $products AS p
LEFT JOIN $product_attributes AS a7 ON p.product_id = a7.product_id AND a7.attribute_id = 7
LEFT JOIN $product_attributes AS a1 ON p.product_id = a1.product_id AND a1.attribute_id = 1
LEFT JOIN $product_attributes AS a9 ON p.product_id = a9.product_id AND a9.attribute_id = 9
LEFT JOIN $product_attributes AS a3 ON p.product_id = a3.product_id AND a3.attribute_id = 3
LEFT JOIN $product_attributes AS a12 ON p.product_id = a12.product_id AND a12.attribute_id = 12
LEFT JOIN $product_attributes AS a23 ON p.product_id = a23.product_id AND a23.attribute_id = 23
LEFT JOIN $product_attributes AS a24 ON p.product_id = a24.product_id AND a24.attribute_id = 24
LEFT JOIN $manufacturers AS m ON a24.product_attr_value = m.manufacturer_id
LEFT JOIN ( SELECT DISTINCT product_id, category_id FROM $products_to_categories ) AS p2c ON p.product_id = p2c.product_id
LEFT JOIN $categories_descr AS cd ON p2c.category_id = cd.category_id

WHERE
a7.product_attr_value = 3

ORDER BY 1 LIMIT 3";

$list = mysql_query( $query ) or die('Cannot execute the big query: '.mysql_error());
$previous_pid = 0;

while( list( $pid, $title, $price, $image_link, $quantity, $description, $seo_prefix, $brand, $category ) = mysql_fetch_row( $list ) )
{
    if( $pid != $previous_pid )
    {
        /* Another product */
        
        /* Closing the previous product, if any */
        if( $previous_pid > 0 ) echo "        </item>\n";
        
        /* Starting the new one */
        
        if( !empty( $seo_prefix ) ) $seo_prefix .= '-';
        
        /* Google does not accept negative balance */
        if( $quantity < 0 ) $quantity = 0;
        
        /* Newer versions of Avactis save just the filename to database */
        if( parse_url( $image_link, PHP_URL_HOST ) === NULL )
            $image_link = $settings['HTTP_URL'].'avactis-images/'.$image_link;
        
        $link = $settings['HTTP_URL']."product-info.php?{$seo_prefix}pid$pid.html";
        $title = htmlentities( trim( $title ) );
        $image_link = htmlentities( $image_link );
        $description = htmlentities( trim( $description ) );
        $brand = htmlentities( trim( $brand ) );
        $category = htmlentities( trim( $category ) );
        
        echo "
        <item>
            <g:id>$pid</g:id>
            <title>$title</title>	
            <link>$link</link>
            <g:price>$price</g:price>
            <g:condition>new</g:condition>
            <description>$description</description>
            <g:brand>$brand</g:brand>
            <g:image_link>$image_link</g:image_link>
            <g:quantity>$quantity</g:quantity>
            <g:product_type>$category</g:product_type>
";
        $previous_pid = $pid;
    }
    else
    {
        /* Same product in another category */
        echo "            <g:product_type>$category</g:product_type>\n";
    }
}

echo '        </item>
    </channel>
</rss>';
