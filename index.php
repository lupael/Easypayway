<?php
/*
Plugin Name: WooCommerce EasyPayWay A Bangladeshi Payment Gateway
Plugin URI: http://www.easypayway.com/
Description: Extends WooCommerce EasyPayWay A Bangladeshi Payment Gateway.
Version: 2.0.0
Author: Jm Redwan and Lupael Email: lupaels@gmail.com




    Copyright: © 2009-2013 JMRedwan and Lupael.
    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

add_action('plugins_loaded', 'woocommerce_easypayway_init', 0);

function woocommerce_easypayway_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Localisation
     */
    load_plugin_textdomain('wc-easypayway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

    if($_GET['msg']!=''){
        add_action('the_content', 'showMessage');
    }

    function showMessage($content){
            return '<div class="box '.htmlentities($_GET['type']).'-box">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
    }
    /**
     * Gateway class
     */
    class WC_Easypayway extends WC_Payment_Gateway {
    protected $msg = array();
        public function __construct(){
            // Go wild in here
            $this -> id = 'easypayway';
            $this -> method_title = __('EasyPayWay', 'Redwan');
            $this -> icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/EasyPayWay.png';
            $this -> has_fields = false;
            $this -> init_form_fields();
            $this -> init_settings();
            $this -> title = $this -> settings['title'];
            $this -> description = $this -> settings['description'];
            $this -> merchant_id = $this -> settings['merchant_id'];
            //$this -> signature_key = '4590b0f8*******************';
            $this -> redirect_page_id = $this -> settings['redirect_page_id'];
			//$this -> fail_page_id = $this -> settings['fail_page_id'];
            //$this -> liveurl = 'https://securepay.easypayway.com/payment/index.php';
            $this -> liveurl = 'https://www.easypayway.com/secure_pay/paynow.php';
		$this -> msg['message'] = "";
            $this -> msg['class'] = "";
            add_action('init', array(&$this, 'check_easypayway_response'));
            //update for woocommerce >2.0
            add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_easypayway_response' ) );

            add_action('valid-easypayway-request', array(&$this, 'successful_request'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_easypayway', array(&$this, 'receipt_page'));
            add_action('woocommerce_thankyou_easypayway',array(&$this, 'thankyou_page'));
        }

        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'Redwan'),
                    'type' => 'checkbox',
                    'label' => __('Enable easypayway Payment Module.', 'Redwan'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'Redwan'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'Redwan'),
                    'default' => __('Credit Card / Debit Card', 'Redwan')),
                'description' => array(
                    'title' => __('Description:', 'Redwan'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'Redwan'),
                    'default' => __('Pay securely by Credit or Debit card or internet banking through easypayway Secure Servers.', 'Redwan')),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'Redwan'),
                    'type' => 'text',
                    'description' => __('This id(USER ID) available at "Generate Working Key" of "Settings and Options at easypayway https://easypayway.com/merchant/account_website.php."')),
                'fail_page_id' => array(
                    'title' => __('Return Page Fail'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL of Fail page"
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL of success page"
                )
            );


        }
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options(){
            echo '<h3>'.__('easypayway Payment Gateway', 'Redwan').'</h3>';
            echo '<p>'.__('easypayway is most popular payment gateway for online shopping in Bangladesh').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }
        /**
         *  There are no payment fields for easypayway, but we want to show the description if set.
         **/
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }
        /**
         * Receipt Page
         **/
        function receipt_page($order){
            echo '<p>'.__('Thank you for your order, please click the button below to pay with easypayway.', 'Redwan').'</p>';
            echo $this -> generate_easypayway_form($order);
        }
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
            $order = new WC_Order($order_id);
            return array(
	        	'result' => 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
	        );
        }
        /**
         * Check for valid easypayway server callback
         **/
        function check_easypayway_response(){
            global $woocommerce;
            if(isset($_REQUEST['mer_txnid']) && isset($_REQUEST['store_id'])){
                $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                //$fail_url = ($this -> fail_page_id=="" || $this -> fail_page_id==0)?get_site_url() . "/":get_permalink($this -> fail_page_id);
               // $order_id_time = $_REQUEST['Order_Id'];
                //$order_id = explode('_', $_REQUEST['Order_Id']);
                $order_id = $_REQUEST['store_id'];
                $this -> msg['class'] = 'error';
                $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";

                if($order_id != ''){
                    try{
                        $order = new WC_Order($order_id);
                        $merchant_id = $_REQUEST['store_id'];
                        $amount = $_REQUEST['amount'];
                        //$checksum = $_REQUEST['Checksum'];
                        $pay_status  = $_REQUEST['pay_status '];
                        //$Checksum = $this -> verifyCheckSum($merchant_id, $order_id_time, $amount, $AuthDesc);
                        $transauthorised = false;
                        if($order -> status !=='completed'){
                            
                                if($pay_status=="success"){
                                    $transauthorised = true;
                                    $this -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                    $this -> msg['class'] = 'success';
                                    if($order -> status == 'processing'){

                                    }else{
                                        $order -> payment_complete();
                                        $order -> add_order_note('easypayway payment successful<br/>Bank Ref Number: '.$_REQUEST['nb_bid']);
                                        $order -> add_order_note($this->msg['message']);
                                        $woocommerce -> cart -> empty_cart();

                                    }

                                }else if($pay_status=="failed"){
                                    $this -> msg['message'] = "Thank you for shopping with us. We will keep you posted regarding the status of your order through e-mail";
                                    $this -> msg['class'] = 'info';

                                    //Here you need to put in the routines/e-mail for a  "Batch Processing" order
                                    //This is only if payment for this transaction has been made by an American Express Card
                                    //since American Express authorisation status is available only after 5-6 hours by mail from easypayway and at the "View Pending Orders"
                                }
                                else{
                                    $this -> msg['class'] = 'error';
                                    $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                    //Here you need to put in the routines for a failed
                                    //transaction such as sending an email to customer
                                    //setting database status etc etc
                                }
                            
                            if($transauthorised==false){
                                $order -> update_status('failed');
                                $order -> add_order_note('Failed');
                                $order -> add_order_note($this->msg['message']);
                            }
                            //removed for WooCOmmerce 2.0
                            //add_action('the_content', array(&$this, 'showMessage'));
                        }}catch(Exception $e){
                            // $errorOccurred = true;
                            $msg = "Error";
                        }

                }
                $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
				//$fail_url = ($this -> fail_page_id=="" || $this -> fail_page_id==0)?get_site_url() . "/":get_permalink($this -> fail_page_id);
                //For wooCoomerce 2.0
                $redirect_url = add_query_arg( array('msg'=> urlencode($this -> msg['message']), 'type'=>$this -> msg['class']), $redirect_url );

                wp_redirect( $redirect_url );
                exit;



            }



        }
       /*
        //Removed For WooCommerce 2.0
       function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }*/
        /**
         * Generate easypayway button link
         **/
        public function generate_easypayway_form($order_id){
            global $woocommerce;
            $order = new WC_Order($order_id);
            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
			$fail_url = ($this -> fail_page_id=="" || $this -> fail_page_id==0)?get_site_url() . "/":get_permalink($this -> fail_page_id);
          //For wooCoomerce 2.0
		 // $fail_url = add_query_arg( 'wc-api', get_class( $this ), $fail_url );
            $redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
            $order_id = $order_id.'_'.date("ymds");
			$declineURL = $order->get_cancel_order_url();
			
			$desc = '';
			//Products Details 
			foreach ($order->get_items() as $product) {
                       
                         $desc .= " ".$product['id']." # ".$product['name']." - ".$product['qty']." - ".$price."<br/>";
                        }
			
			
			
			
           // $checksum = $this -> getCheckSum($this -> merchant_id, $order -> order_total, $order_id, $redirect_url);
            $easypayway_args = array(
                'store_id' => $this -> merchant_id,
                //'signature_key' => $this -> signature_key,
                'amount' => $order -> order_total,
				'currency' =>	get_option('woocommerce_currency'),
                'tran_id' => $order_id,
                'success_url' => $redirect_url,
				'fail_url' => $redirect_url,
				'cancel_url' => $declineURL,
                'cus_name' => $order -> billing_first_name .' '. $order -> billing_last_name,
                'cus_add1' => $order -> billing_address_1,
                'cus_country' => $order -> billing_country,
                'cus_state' => $order -> billing_state,
                'cus_city' => $order -> billing_city,
                'cus_postcode' => $order -> billing_postcode,
                'cus_phone'=> $order -> billing_phone,
                'cus_email' => $order -> billing_email,
                'ship_name' => $order -> shipping_first_name .' '. $order -> shipping_last_name,
                'ship_add1' => $order -> shipping_address_1,
                'ship_country' => $order -> shipping_country,
                'ship_state' => $order -> shipping_state,
                'delivery_cust_tel' => '',
                'desc' =>  $desc,
                'ship_city' => $order -> shipping_city,
                'ship_postcode' => $order -> shipping_postcode);
               
            $easypayway_args_array = array();
            foreach($easypayway_args as $key => $value){
                $easypayway_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }
            return '<form action="'.$this -> liveurl.'" method="post" id="easypayway_payment_form">
                ' . implode('', $easypayway_args_array) . '
                <input type="submit" class="button-alt" id="submit_easypayway_payment_form" value="'.__('Pay via easypayway', 'Redwan').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'Redwan').'</a>
                <script type="text/javascript">
jQuery(function(){
    jQuery("body").block(
            {
                message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to easypayway to make payment.', 'Redwan').'",
                    overlayCSS:
            {
                background: "#fff",
                    opacity: 0.6
        },
        css: {
            padding:        20,
                textAlign:      "center",
                color:          "#555",
                border:         "3px solid #aaa",
                backgroundColor:"#fff",
                cursor:         "wait",
                lineHeight:"32px"
        }
        });
        jQuery("#submit_easypayway_payment_form").click();

        });
                    </script>
                </form>';


        }


        /**
         *  easypayway Essential Functions
         **/
        private function getCheckSum($MerchantId,$Amount,$OrderId ,$URL)
        {
            $str ="$MerchantId|$OrderId|$Amount|$URL";
            $adler = 1;
            $adler = $this -> adler32($adler,$str);
            return $adler;
        }

        private function verifyCheckSum($MerchantId,$OrderId,$Amount,$AuthDesc,$CheckSum)
        {
            $str = "$MerchantId|$OrderId|$Amount|$AuthDesc";
            $adler = 1;
            $adler = $this -> adler32($adler,$str);

            if($adler == $CheckSum)
                return "true" ;
            else
                return "false" ;
        }

        private function adler32($adler , $str)
        {
            $BASE =  65521 ;

            $s1 = $adler & 0xffff ;
            $s2 = ($adler >> 16) & 0xffff;
            for($i = 0 ; $i < strlen($str) ; $i++)
            {
                $s1 = ($s1 + Ord($str[$i])) % $BASE ;
                $s2 = ($s2 + $s1) % $BASE ;
                //echo "s1 : $s1 <BR> s2 : $s2 <BR>";

            }
            return $this -> leftshift($s2 , 16) + $s1;
        }

        private function leftshift($str , $num)
        {

            $str = DecBin($str);

            for( $i = 0 ; $i < (64 - strlen($str)) ; $i++)
                $str = "0".$str ;

            for($i = 0 ; $i < $num ; $i++)
            {
                $str = $str."0";
                $str = substr($str , 1 ) ;
                //echo "str : $str <BR>";
            }
            return $this -> cdec($str) ;
        }

        private function cdec($num)
        {

            for ($n = 0 ; $n < strlen($num) ; $n++)
            {
                $temp = $num[$n] ;
                $dec =  $dec + $temp*pow(2 , strlen($num) - $n - 1);
            }

            return $dec;
        }
        /*
         * End easypayway Essential Functions
         **/
        // get all pages
        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_easypayway_gateway($methods) {
        $methods[] = 'WC_Easypayway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_easypayway_gateway' );
    }

?>
