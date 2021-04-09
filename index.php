<?php
/*
Plugin Name: wp-tw-shop
Plugin URI: http://wordpress.org/plugins/
Description: wp-tw-shop 美安串接
Author: ymlin
Version: 2.0
Author URI: http://ma.tt/
*/

add_action('admin_menu','fun_shop_menu');
function fun_shop_menu(){
    add_submenu_page( 'woocommerce', '美安串接系統','美安串接系統','manage_woocommerce','shopcom_settings', 'fun_shopcom_setting_page');
}
function fun_shopcom_setting_page(){

    /*需要輸入的資料
     *offer_id：店家代號
     *Advertiser_ID：應該也是店家代號
     *Commission：佣金比例，float
    */
    ?>
    <div>
        <h2><?php _e('美安串接系統設定',''); ?></h2>

        <div>
            <?php
            if(isset($_POST['shopcom_merchant'])){
                update_option('shopcom_merchant',$_POST['shopcom_merchant']);
            }
            if(isset($_POST['shopcom_advertiser'])){
                update_option('shopcom_advertiser',$_POST['shopcom_advertiser']);
            }
            if(isset($_POST['shopcom_commission'])){
                update_option('shopcom_commission',$_POST['shopcom_commission']);
            }
            $gc_shopcom_merchant = get_option('shopcom_merchant');
             ?>
            <form id="gc_shopcom_form" method="post" action="admin.php?page=shopcom_settings">

                <fieldset>
                    <label class="gc_fields"><span><?php _e('Offer ID','')?></span>
                        <input  type="text"  id="gc_shopcom_merchant" name="shopcom_merchant" value="<?php echo get_option("shopcom_merchant");?>">
                    <br>
                    </label>
                    <label class="gc_fields"><span><?php _e('Advertiser ID','') ?></span>
                        <input  type="text"  id="gc_shopcom_advertiser" name="shopcom_advertiser" value="<?php echo get_option("shopcom_advertiser");?>">
                        <br>
                    </label>
                    <label class="gc_fields"><span><?php _e('Commission(請輸入小數點 EX:0.2)','') ?></span>
                        <input  type="text" maxlength="6" id="shopcom_commission" name="shopcom_commission" value="<?php echo get_option("shopcom_commission");?>">

                        <br>
                    </label>
                    <?php submit_button(__('儲存',''));?>
                </fieldset>
            </form>
        </div>
    </div>
    <div></div>
    <?php
}
add_action('init', 'get_shop_com_para');
function get_shop_com_para($shop_com) {
    if (!isset($shop_com)) {
        $shop_com = '';
    }
    $shop_com_RID = $_GET['RID'];
    $shop_com_Click_ID = $_GET['Click_ID'];

    if (isset($_COOKIE['shop_com']) || $shop_com != '1') { //若不是從美安首頁進入或已經設cookie就不強迫設cookie
        if (empty($shop_com_RID)) {
            return;
        }
        if (empty($shop_com_Click_ID)) {
            return;
        }
    }
    $shop_com_str = 'shopcom ' . $shop_com_RID . ' ' . $shop_com_Click_ID;
    setcookie('shop_com', $shop_com_str, time() + (3600247), "/", $_SERVER['SERVER_NAME']); //24 x 7小時有效
}

function shop_com_shipping_attr() {
    return (
    array('url' => 'https://api.hasoffers.com/Api',
        'Offer_ID' =>   get_option("shopcom_merchant"),
        'Advertiser_ID' =>  get_option("shopcom_advertiser"),
        'revenue_rate' => get_option("shopcom_commission")
    )
    );
}

add_action('woocommerce_checkout_update_order_meta', 'save_rid_to_order_meta', 10, 2);
function save_rid_to_order_meta($order_id, $posted) {
    if (!isset($_COOKIE['shop_com'])) {
        return;
    }


    update_post_meta($order_id, 'shop_com', $_COOKIE['shop_com']);


    unset($_COOKIE['shop_com']);

    setcookie('shop_com', '', time() - 3600, "/", $_SERVER['SERVER_NAME']);
    return; //clear cookie

}


add_action('woocommerce_thankyou', 'report_shop_com_shipping_order', 20);
function report_shop_com_shipping_order($order_id) {
    $shipping_attr = shop_com_shipping_attr();
    $shop_com_str = get_post_meta($order_id, 'shop_com', true);
	if (empty($shop_com_str)) {
        return;
    } //若非美安連結進來不處理
    $shop_com = explode(' ', $shop_com_str);
     if (!is_array($shop_com)) {
        return;
    }
    if (empty($shop_com[1])) {
        return;
    }
    if (empty($shop_com[2])) {
        return;
    }
	$Order_Amount = 0;

    $order = wc_get_order($order_id);
// Iterating through each WC_Order_Item_Product objects
    foreach ($order->get_items() as $item_key => $item_values):
//## Access Order Items data properties (in an array of values) ##
        $item_data = $item_values->get_data();
        $Order_Amount = $Order_Amount + (int)$item_data['total'];
    endforeach;
    $Commission_Amount = round($Order_Amount * $shipping_attr['revenue_rate'], 2); //廣告佣金比率
    $time = strtotime(get_the_date("Y-m-d H:i:s", $order_id)); // -3600*12; //GMT-4(標準時間-4小時)(台灣時間-12小時)美國東岸時間
    $order = new WC_Order($order_id);
    $order_date = $order->order_date;
    $order_date = date("Y-m-d", strtotime($order_date));
//  unset($_COOKIE['shop_com']);
//  setcookie('shop_com', '', time() - 3600, '/');
//setcookie('shop_com', '', time()-3600, "/", $_SERVER['SERVER_NAME']);    return; //clear cookie
    $data = array(
        'Format' => 'json',
        'Target' => 'Conversion',
        'Method' => 'create',
        'Service' => 'HasOffers',
        'Version' => '2',
        'NetworkId' => 'marktamerica',
        'NetworkToken' => 'NETPYKNAYOswzsboApxaL6GPQRiY2s',
        'data[offer_id]' => $shipping_attr['Offer_ID'],
        'data[advertiser_id]' => $shipping_attr['Advertiser_ID'],
        'data[sale_amount]' => 1 * $Order_Amount,
        'data[affiliate_id]' => '12',
        'data[payout]' => $Commission_Amount,
        'data[revenue]' => $Commission_Amount,
        'data[advertiser_info]' => $order_id,
        'data[affiliate_info1]' => $shop_com[1],
        'data[ad_id]' => $shop_com[2],
        'data[session_datetime]' => $order_date
    );

    try {

        $handle = curl_init();

        if (FALSE === $handle)
            throw new Exception('failed to initialize');

        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($data));
// set url
        curl_setopt($handle, CURLOPT_URL, "https://api.hasoffers.com/Api");

//return the transfer as a string
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);


        $output = curl_exec($handle);

        update_post_meta($order_id, 'shop_com_output', $output);


// close curl resource to free up system resources


        if (FALSE === $output)
            throw new Exception(curl_error($handle), curl_errno($handle));
        curl_close($handle);
// ...process $content now
    } catch (Exception $e) {
//  echo $e->getMessage();

        trigger_error(sprintf(

            'Curl failed with error #%d: %s',
            $e->getCode(), $e->getMessage()),
            E_USER_ERROR);

    }

    curl_close($handle);

}

add_action('woocommerce_order_status_cancelled', 'report_shop_com_shipping_order_cancelled', 21, 1);
function report_shop_com_shipping_order_cancelled($order_id) {
    $shop_com_str = get_post_meta($order_id, 'shop_com', true);
    if (empty($shop_com_str)) {
        return;
    } //若非美安連結進來不處理
    $shipping_attr = shop_com_shipping_attr();
    $shop_com = explode(' ', $shop_com_str);
    if (!is_array($shop_com)) {
        return;
    }
    if (empty($shop_com[1])) {
        return;
    }
    if (empty($shop_com[2])) {
        return;
    }
//$order_id :訂單編號
    $Order_Amount = 0;
    $order = wc_get_order($order_id);
// Iterating through each WC_Order_Item_Product objects
    foreach ($order->get_items() as $item_key => $item_values):
//## Access Order Items data properties (in an array of values) ##
        $item_data = $item_values->get_data();
        $Order_Amount = $Order_Amount + (int)$item_data['total'];
    endforeach;

//$Order_Amount=(int)get_post_meta($order_id, '_order_total', true);
    $Order_Amount = (-1) * $Order_Amount; //退貨x-1
    $Commission_Amount = round($Order_Amount * $shipping_attr['revenue_rate'], 2); //廣告佣金比率
    $time = strtotime(get_the_date("Y-m-d H:i:s", $order_id)); //-3600*12; //GMT-4(標準時間-4小時)(台灣時間-12小時)美國東岸時間
    $session_datetime = gmdate("Y/m/d", $time) . '%C2%A0' . gmdate("H:i:s", $time);

    $order = new WC_Order($order_id);
    $order_date = $order->order_date;
    $order_date = date("Y-m-d", strtotime($order_date));
    $data = array(
        'Format' => 'json',
        'Target' => 'Conversion',
        'Method' => 'create',
        'Service' => 'HasOffers',
        'Version' => '2',
        'NetworkId' => 'marktamerica',
        'NetworkToken' => 'NETPYKNAYOswzsboApxaL6GPQRiY2s',
        'data[offer_id]' => $shipping_attr['Offer_ID'],
        'data[advertiser_id]' => $shipping_attr['Advertiser_ID'],
        'data[sale_amount]' => $Order_Amount,
        'data[affiliate_id]' => '12',
        'data[payout]' => $Commission_Amount,
        'data[revenue]' => $Commission_Amount,
        'data[advertiser_info]' => $order_id,
        'data[affiliate_info1]' => $shop_com[1],
        'data[ad_id]' => $shop_com[2],
        'data[is_adjustment]' => '1',
        'data[session_datetime]' => $order_date
    );

        try {

            $handle = curl_init();


            if (FALSE === $handle)
                throw new Exception('failed to initialize');

            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($data));
// set url
            curl_setopt($handle, CURLOPT_URL, "https://api.hasoffers.com/Api");

//return the transfer as a string
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);


            $output = curl_exec($handle);
            update_post_meta($order_id, 'shop_com_cancel', $output);

// close curl resource to free up system resources


            if (FALSE === $output)
                throw new Exception(curl_error($handle), curl_errno($handle));
            curl_close($handle);
// ...process $content now
        } catch (Exception $e) {
            echo $e->getMessage();
            trigger_error(sprintf(
                'Curl failed with error #%d: %s',
                $e->getCode(), $e->getMessage()),
                E_USER_ERROR);

        }

        curl_close($handle);


}

/**
 * 在後台訂單明細顯示
 */
add_action('woocommerce_admin_order_data_after_billing_address', 'show_myopia_glasses_shipping_order_after_billing', 10, 1);
function show_myopia_glasses_shipping_order_after_billing($order) {
    $shop_com_str = get_post_meta($order->id, 'shop_com', true); //若由美安連結進來顯示屬性
    if (!empty($shop_com_str)) {
        $shop_com = explode(' ', $shop_com_str);
        if (is_array($shop_com)) {
            echo '<p><strong>美安RID:</strong>' . $shop_com[1] . '<br/><strong>Click_ID:</strong>' . $shop_com[2] . '</p>';

        }
    }
}
add_filter( 'manage_edit-shop_order_columns', 'custom_shop_order_column',11);
add_action( 'manage_shop_order_posts_custom_column' , 'cbsp_credit_details', 10, 2 );
function custom_shop_order_column($columns){
    //add columns
    $columns['order_shopcom'] = '美安訂單';
    return $columns;
}

// adding the data for each orders by column (example)
function cbsp_credit_details( $column ){
    global $post, $woocommerce, $the_order;
    $order_id = $the_order->id;

    switch ( $column )
    {
        case 'order_shopcom' :
            $myVarOne = get_post_meta( $order_id, 'shop_com', true );
            if( $myVarOne != '' ){
                echo '<p>YES</p>';
            }
            break;
    }
}

//add_action( 'woocommerce_update_product', 'mp_sync_on_product_save', 10, 1 );
function mp_sync_on_product_save( $product_id ) {
     $product = wc_get_product( $product_id );
      $i = 0;
            $args = array('post_type' => 'product', 'posts_per_page' => -1);

            $loop = new WP_Query($args);

            while ($loop->have_posts()) : $loop->the_post();
                global $product;
                $p_id = get_the_ID();//產品編號

                $p_title = get_the_title();//商品名稱
                $g_url = get_permalink();//產品網址
                $g_category = get_cat_name($p_id);
                $term = get_the_terms($p_id, 'product_cat');
                $product_cat_name = $term->term_id;

                $product_DI = $p_id; //Product ID
                $pro = new WC_Product($product_DI);
                echo "<b>產品編號: </b>" . $p_id . "<br>";
                echo "<b>商品名稱: </b>" . $p_title . "<br>";
                echo "<b>產品敘述: </b>" . $pro->get_post_data()->post_excerpt . "<br>";  //Get description
                echo "<b>產品網址: </b>" . $g_url . "<br>";  //Get description
                echo "<b>售價: </b>" . $pro->get_price() . "<br>";      /// Get price

                $product = new WC_product($p_id);
                $attachmentIds = $product->get_gallery_attachment_ids();
                $imgUrls = array();
                foreach ($attachmentIds as $attachmentId) {
                    $imgUrls = wp_get_attachment_url($attachmentId);
                    echo "<b>圖片網址: </b>" . $imgUrls . "<br>";
                    echo "\n";
                }
                $arr = wp_get_post_terms($p_id, 'product_cat', array('fields' => 'ids'));
                foreach ($arr as $arrs) {
                    $category = get_term($arrs, 'product_cat');
                    echo "<b>分類: </b>" . $category->name . "<br>";
                }
                echo "<br>";
                $descr = $pro->get_post_data()->post_excerpt;
                //  $product_data = $pro->get_post_data();
                //$descr = $product_data->post_excerpt;
                $price = $pro->get_price();
                $g_url = htmlspecialchars($g_url);
                $p_title = htmlspecialchars($p_title);
                $descr = htmlspecialchars($descr);
                $imgUrls = wp_get_attachment_url($product->get_image_id());
                // $descr= strip_tags($descr);
                $strXML1 .= "<Product>
             <SKU>$p_id</SKU>
            <Name>$p_title</Name>
            <Description>$descr</Description>
            <URL>$g_url</URL>
            <Price>$price</Price>
            <LargeImage>$imgUrls</LargeImage>
            <Category>$category->name</Category>
            </Product>
            ";

                $i++;
            endwhile;
            $strXML0 = "<?xml version='1.0' encoding='UTF-8'?>
	<Products>
";
            $strXML2 = "
</Products>";
            echo "<b>數量: </b>" . $i . "<br>";
            $strXML = $strXML0 . $strXML1 . $strXML2;
            $path = get_home_path();
            $filename = $path."nicesource.xml";
            $file = fopen($filename, "w");
            fwrite($file, $strXML);

          //  wp_reset_query();
}
add_action( 'transition_post_status', 'my_product_update' ,10 ,3);
function my_product_update( $new_status, $old_status, $post ) {
        global $post;

        if($post->post_type == 'product'){
            $i = 0;
            $args = array('post_type' => 'product', 'posts_per_page' => -1);

            $loop = new WP_Query($args);

            while ($loop->have_posts()) : $loop->the_post();
                global $product;
                $p_id = get_the_ID();//產品編號

                $p_title = get_the_title();//商品名稱
                $g_url = get_permalink();//產品網址
                $g_category = get_cat_name($p_id);
                $term = get_the_terms($p_id, 'product_cat');
                $product_cat_name = $term->term_id;

                $product_DI = $p_id; //Product ID
                $pro = new WC_Product($product_DI);
                echo "<b>產品編號: </b>" . $p_id . "<br>";
                echo "<b>商品名稱: </b>" . $p_title . "<br>";
                echo "<b>產品敘述: </b>" . $pro->get_post_data()->post_excerpt . "<br>";  //Get description
                echo "<b>產品網址: </b>" . $g_url . "<br>";  //Get description
                echo "<b>售價: </b>" . $pro->get_price() . "<br>";      /// Get price

                $product = new WC_product($p_id);
                $attachmentIds = $product->get_gallery_attachment_ids();
                $imgUrls = array();
                foreach ($attachmentIds as $attachmentId) {
                    $imgUrls = wp_get_attachment_url($attachmentId);
                    echo "<b>圖片網址: </b>" . $imgUrls . "<br>";
                    echo "\n";
                }
                $arr = wp_get_post_terms($p_id, 'product_cat', array('fields' => 'ids'));
                foreach ($arr as $arrs) {
                    $category = get_term($arrs, 'product_cat');
                    echo "<b>分類: </b>" . $category->name . "<br>";
                }
                echo "<br>";
                $descr = $pro->get_post_data()->post_excerpt;
                //  $product_data = $pro->get_post_data();
                //$descr = $product_data->post_excerpt;
                $price = $pro->get_price();
                $g_url = htmlspecialchars($g_url);
                $p_title = htmlspecialchars($p_title);
                $descr = htmlspecialchars($descr);
                $imgUrls = wp_get_attachment_url($product->get_image_id());
                // $descr= strip_tags($descr);
                $strXML1 .= "<Product>
             <SKU>$p_id</SKU>
            <Name>$p_title</Name>
            <Description>$descr</Description>
            <URL>$g_url</URL>
            <Price>$price</Price>
            <LargeImage>$imgUrls</LargeImage>
            <Category>$category->name</Category>
            </Product>
            ";

                $i++;
            endwhile;
            $strXML0 = "<?xml version='1.0' encoding='UTF-8'?>
	<Products>
";
            $strXML2 = "
</Products>";
            echo "<b>數量: </b>" . $i . "<br>";
            $strXML = $strXML0 . $strXML1 . $strXML2;
            $path = get_home_path();
            $filename = $path."nicesource.xml";
            $file = fopen($filename, "w");
            fwrite($file, $strXML);

            wp_reset_query();
        }


    }

?>