<?php
/*
Plugin Name: Amwaly Gateway
Description: Use this woocommerce payment gateway plugin to enable clients of your store to pay using amwaly gateway.
Version: 1.0
Author: Amwaly io
text-domain:  Amwaly Gateway
Plugin URI: https://github.com/amwaly/Amwaly_WooCommerce
*/
add_action('plugins_loaded', 'init_amwalyAPI', 0);

function init_amwalyAPI(){
if (!class_exists('WC_Payment_Gateway')) {
return;
}

/**
* @param $methods
* @return array
*/
function add_WC_amwaly($methods){
$methods[] = 'Wcamwaly';
return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_WC_amwaly');

class Wcamwaly extends WC_Payment_Gateway{
private $Amwaly_ICON;
private $Amwaly_NAME;
private $Amwaly_DESCRIPTION;
private $Amwaly_BUTTON_TEXT;

/**
* Constructor for the gateway.
*/
public function __construct(){

    $Amwaly_ICON        = 'https://amwaly.io/assets/img/Amwaly.png';
    $Amwaly_NAME        = 'Amwaly PAYMENT';
    $Amwaly_DESCRIPTION = 'Gateway support all Payment methods ( Credit Cards , Debit Cards )';
    $Amwaly_BUTTON_TEXT = 'Payment';


load_plugin_textdomain('amwaly', false, 'amwaly_wc_payment_gateway/languages');

$this->id = 'amwaly';
$this->has_fields = false;
$this->icon = apply_filters('woocommerce_amwaly_icon', $Amwaly_ICON);
$this->order_button_text = __($Amwaly_BUTTON_TEXT, 'amwaly');
$this->title = __($Amwaly_NAME, 'amwaly');
$this->method_title = $Amwaly_NAME;
$this->method_description = __($Amwaly_DESCRIPTION, 'amwaly');
$this->enabled = $this->get_option('enabled');
$this->description = __($Amwaly_DESCRIPTION, 'amwaly');

// Load the settings.
$this->init_form_fields();
$this->init_settings();


/** @noinspection PhpUndefinedConstantInspection */
if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
/* 2.0.0 */
add_action('woocommerce_update_options_payment_gateways_' . $this->id, [&$this, 'process_admin_options']);
} else {
/* 1.6.6 */
add_action('woocommerce_update_options_payment_gateways', [&$this, 'process_admin_options']);
}

/** @noinspection PhpUndefinedConstantInspection */
if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
/* 2.0.0 */
add_action('woocommerce_update_options_payment_gateways_' . $this->id, [&$this, 'process_admin_options']);
} else {
/* 1.6.6 */
add_action('woocommerce_update_options_payment_gateways', [&$this, 'process_admin_options']);
}

// This add_action for add amwaly PAYMENT to WooCommerce 
add_action('woocommerce_receipt_amwaly', [&$this, 'Receipt_Page_amwaly']);
add_action('woocommerce_receipt_WC_amwaly', [&$this, 'Receipt_Page_amwaly']);
add_action('woocommerce_thankyou_amwaly', [&$this, 'Check_Payment_amwaly']);
add_action('woocommerce_thankyou_WC_amwaly', [&$this, 'Check_Payment_amwaly']);



}


/**
* @return void
*/
public function process_admin_options()
{
parent::process_admin_options();
if ($this->settings['is_testing_env'] == 'yes') {
$this->add_error(__("<span style='color:red;font-weight: bold'>amwaly Done </b>", 'amwaly'));
$this->display_errors();
}
}


// amwaly Settings 

public function init_form_fields(){
$this->form_fields = [
'Publishable_Key' => [
'title' => __('Publishable Key', 'amwaly'),
'type' => 'text',
],
'project_id' => [
'title' => __('amwaly IDSK', 'amwaly'),
'type' => 'text',
],
'email_admin' => [
'title' => __('Email Account', 'amwaly'),
'type' => 'text',
],

];
}

/**
* 1) After click on payment, this method will be called.
* @param $order_id
* @return array
*/
public function process_payment($order_id){
$order = new WC_Order($order_id);
/** @noinspection PhpUndefinedConstantInspection */
if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>=')) {
/* 2.1.0 */
$checkout_payment_url = $order->get_checkout_payment_url(true);
} else {
/* 2.0.0 */
$checkout_payment_url = get_permalink(get_option('woocommerce_pay_page_id'));
}
return [
'result' => 'success',
'redirect' => add_query_arg(
'order-pay',
$order->get_id(),
add_query_arg(
'key',
$order->get_order_key(),
$checkout_payment_url
)
)
];
}

/**
* 2) Receipt Page be called.
* @param $order_id
*/
public function Receipt_Page_amwaly($order_id){
$url = $this->Generate_amwaly_URL($order_id);
if ($url) {
echo "<script>window.location.href = '" . esc_url($url) . "';</script>";
} else {
echo '<p>' . __('Something error. Please try again by refreshing the page', 'amwaly') . '</p>';
}
}

/**
* 3) Generate tap button link
* @param $order_id
* @return bool|mixed
*/
private function Generate_amwaly_URL($order_id){
return $this->Create_amwaly_Invoice_URL($order_id);
}

/**
* 4) Create amwaly invoice URL
* @param $order_id
* @return bool|mixed
*/
private function Create_amwaly_Invoice_URL($order_id){
$customer = new WC_Order($order_id);
$redirect_url = $customer->get_checkout_order_received_url();
if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
$redirect_url = add_query_arg('wc-api', strtolower(get_class($this)), $redirect_url);
}

// get info's from settings admin
$Publishable_Key    = $this->get_option('Publishable_Key');
$project_id         = $this->get_option('project_id');
$Email_Admin        = $this->get_option('email_admin');


// get info's customer

$Total              = $customer->get_total();
$customerEmail      = $customer->get_billing_email();
$customer_givenName = $customer->get_billing_first_name();
$customer_surname   = $customer->get_billing_last_name();
$customerPhone      = $customer->get_billing_phone();
$billing_address_1  = $customer->get_billing_address_1();
$billing_address_2  = $customer->get_billing_address_2();
$billing_city       = $customer->get_billing_city();
$billing_state      = $customer->get_billing_state();
$billing_postcode   = $customer->get_billing_postcode();
$billing_country    = $customer->get_billing_country();
foreach ( $customer->get_items() as $item_id => $item ) {
    $Product_Item    = $item->get_name();
}


$amwaly_json = [
'Total' => $Total,
'Publishable_Key' => $Publishable_Key,
'project_id' => $project_id,
'Email_Admin' => $Email_Admin,
'customerEmail' => $customerEmail,
'customer_givenName' => $customer_givenName,
'customer_surname' => $customer_surname,
'customerPhone' => $customerPhone,
'billing_address_1' => $billing_address_1,
'billing_address_2' => $billing_address_2,
'billing_city' => $billing_city,
'billing_state' => $billing_state,
'billing_postcode' => $billing_postcode,
'billing_country' => $billing_country,
'redirect_url' => $redirect_url,
];
 
$json = wp_json_encode($amwaly_json);
$parameter = [
'body' => $json,
'headers' => [
'Content-Type' => 'application/json',
'Developer' => 'amwaly CO',
],
'timeout' => 60,
'httpversion' => '1.1',
'user-agent' => '1.0',
];

$response = wp_safe_remote_post("https://api.amwaly.io/index.php", $parameter);
$dataresponse = json_decode($response['body']);
try {
return $dataresponse->url;
} catch (Exception $exception) {
return false;
}

}




/**
* 5) Check Payments 
* @param $order_id
* @return bool|mixed
*/


public function Check_Payment_amwaly($order_id) {

$order = new WC_Order( $order_id );
$order_pay_method = get_post_meta( $order->id, '_payment_method', true );
if($order_pay_method == 'amwaly'){

$Paymentid = sanitize_text_field($_REQUEST['pid']);

$checkout_url = wc_get_checkout_url();

if (!empty($Paymentid)){

$amwaly_json = [
'Payment_id' => $Paymentid,
];

$json = wp_json_encode($amwaly_json);
$parameter = [
'body' => $json,
'headers' => [
'Content-Type' => 'application/json',
'Developer' => 'amwaly CO',
],
'timeout' => 60,
'httpversion' => '1.1',
'user-agent' => '1.0',
];

$response = wp_safe_remote_post("https://api.amwaly.io/check.php", $parameter);
$dataresponse = json_decode($response['body']);
try {
$order_status = $dataresponse->STATUS;//sanitize_text_field(json_decode($response['STATUS']));
// $order_status = mb_convert_case($order_status, MB_CASE_LOWER, 'UTF-8');
// validating order status value
if ($order_status == 'Transaction succeeded') {
$checkout_url = $order->get_checkout_order_received_url();
$order->update_status( 'completed', __( 'Payment received.', 'wc-gateway-offline' ) );
echo "<script>alert('This Payment Has Been Completed...!');</script>";
// wp_redirect($checkout_url);
} else {
echo "<script>alert('This Payment have problem with Payment Try Again Please...!');</script>";
// wp_redirect($checkout_url);
}
} catch (Exception $ex) {
error_log(print_r($ex, true));
$woocommerce->add_error('Internal Server Error. Try Again Please.');
$woocommerce->set_messages();
}
} else {
wp_redirect($checkout_url);
}
}

}


}
}
