<?php
/**
* Plugin Name: Woocommerce Conta Azul
* Plugin URI: https://penumbra.design/
* Description: ---
* Version: 1.0
* Author: Penumbra design et web.
* Author URI: https://penumbra.design/
**/

function pnmbr_contaazul_register_settings() {
   add_option( 'pnmbr_client_id', '');
   add_option( 'pnmbr_client_secret', '');
   add_option( 'pnmbr_access_token', '');
   add_option( 'pnmbr_refresh_token', '');
   add_option( 'pnmbr_expires_in', '');
   register_setting( 'pnmbr_contaazul_options_group', 'pnmbr_client_id', 'pnmbr_contaazul_callback' );
   register_setting( 'pnmbr_contaazul_options_group', 'pnmbr_client_secret', 'pnmbr_contaazul_callback' );
   register_setting( 'pnmbr_contaazul_options_group', 'pnmbr_access_token', 'pnmbr_contaazul_callback' );
   register_setting( 'pnmbr_contaazul_options_group', 'pnmbr_refresh_token', 'pnmbr_contaazul_callback' );
   register_setting( 'pnmbr_contaazul_options_group', 'pnmbr_expires_in', 'pnmbr_contaazul_callback' );
}
add_action( 'admin_init', 'pnmbr_contaazul_register_settings' );

function pnmbr_contaazul_register_options_page() {
  add_options_page('Integração Contaazul', 'ContaAzul', 'manage_options', 'pnmbr_contaazul', 'pnmbr_contaazul_options_page');
}
add_action('admin_menu', 'pnmbr_contaazul_register_options_page');

function pnmbr_contaazul_options_page()
{
  $CLIENT_ID = get_option("pnmbr_client_id");
  $CLIENT_SECRET = get_option("pnmbr_client_secret");
  $REDIRECT_URI = get_home_url()."/wp-admin/options-general.php?page=pnmbr_contaazul";
  $STATE = "oie123";
  $url = "https://api.contaazul.com/auth/authorize?redirect_uri={$REDIRECT_URI}&client_id={$CLIENT_ID}&scope=sales&state={$STATE}";

  if ($_GET['code']) {
    $authbody = ["grant_type"=> "authorization_code", "redirect_url" => $REDIRECT_URI, "code" => $_GET['code']];
    $request = wp_remote_post( "https://api.contaazul.com/oauth2/token",
				array(
					'headers'   => array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Authorization' => 'Basic '.base64_encode("{$CLIENT_ID}:{$CLIENT_SECRET}") ),
					'method'    => "POST",
					'timeout' => 600,
					'body'		=> json_encode($authbody),
				)
      );
    $response = json_decode($request['body'],true);
    
    setup_token($response);

  }
?>
  <div>
  <?php screen_icon(); ?>
  <h2>Integração ContaAzul</h2>
  <form method="post" action="options.php">
  <?php settings_fields( 'pnmbr_contaazul_options_group' ); ?>
  <h4>Configurações de API</h4>
  <table>
    <tr valign="top">
      <th scope="row"><label for="pnmbr_client_id">Client ID</label></th>
      <td><input type="text" id="pnmbr_client_id" name="pnmbr_client_id" value="<?php echo get_option('pnmbr_client_id'); ?>" /></td>
    </tr>
    <tr valign="top">
      <th scope="row"><label for="pnmbr_client_secret">Client Secret</label></th>
      <td><input type="text" id="pnmbr_client_secret" name="pnmbr_client_secret" value="<?php echo get_option('pnmbr_client_secret'); ?>" /></td>
    </tr>
    <tr>
    <td colspan=2>Ao criar sua API insira o Redirect URI como <code><?= $REDIRECT_URI ?></code>.</td>
    <tr>
    
    
   <?php if ($CLIENT_ID && $CLIENT_SECRET && get_option('pnmbr_access_token')) { ?>
      <tr valign="top">
        <th scope="row"><label for="pnmbr_access_token">Access Token</label></th>
        <td><input disabled type="text" id="pnmbr_access_token" name="pnmbr_access_token" value="<?php echo get_option('pnmbr_access_token'); ?>" /></td>
      </tr>
    <?php } else if ($CLIENT_ID && $CLIENT_SECRET) { ?>
      <tr>
      <th>Access Token</th>
        <td colspan=2>
        <a class="button" href="<?= "javascript:window.open('".$url."',
          'popUpWindow', 'toolbar=no,location=no,status=yes,menubar=no,scrollbars=yes,resizable=yes,width=430,height=500')" ?>" >
          Receber Authorization Token</a>
        </th>
      </tr>
    <?php } ?>
  </table>
  <?php  submit_button(); ?>
</form>
  </div>
<?php
}

// PLUGIN ACTIONS

// add_action( 'woocommerce_thankyou', 'create_contazul');

add_action('woocommerce_order_status_changed', 'pnmbr_update_orderstatus', 20, 4 );
function pnmbr_update_orderstatus( $order_id, $old_status, $new_status, $order ){
    if ($old_status != $new_satus) {
      create_contazul($order_id);
    }
}

add_action('wp_insert_post', function($order_id)
{
    if(!did_action('woocommerce_checkout_order_processed')
        && get_post_type($order_id) == 'shop_order'
        && validate_order($order_id)
				)
    {
         create_contazul($order_id);
    }
});

function validate_order($order_id)
{
    $order = new \WC_Order($order_id);
    $user_meta = get_user_meta($order->get_user_id());
    if($user_meta)
        return true;
    return false;
}

function create_contazul( $order_id ){

	$order = new WC_Order( $order_id );

  if (!$order) return false;

    $customer_id = get_post_meta( $order->get_user_id(), 'customer_id' )[0];
    $sale_id = get_post_meta( $order_id, 'sale_id' )[0];
      
    $res_customer = update_customer($order, $customer_id);
    if ($res_customer['id']) { 
      $res_sale = update_sale($order, $res_customer['id'], $sale_id);
    } else {
      var_dump($res_customer);
    }
    
    return true;
}

add_action( 'added_post_meta', 'sync_product_contaazul', 10, 4 );
add_action( 'updated_post_meta', 'sync_product_contaazul', 10, 4 );
function sync_product_contaazul( $meta_id, $post_id, $meta_key, $meta_value ) {
    if ( $meta_key == '_edit_lock' ) { // we've been editing the post
        if ( get_post_type( $post_id ) == 'product' ) { // we've been editing a product
            $product = wc_get_product( $post_id );
            $ca_service_id = get_post_meta($product->get_id(), 'ca_service_id', true);
            update_service($product, $ca_service_id);
        }
    }
}

function update_service($product, $service_id, $retry = true) {

  $url = 'https://api.contaazul.com/api/v1/services'.($service_id ? '/'.$service_id : '');
  $body = [
    "name" => $product->get_name(),
    "value" => $product->get_price(),
    "code" => $product->get_sku() ?: "SERV".$product->get_id()
  ];
  // var_dump($service_id);
  $req = wp_remote_post( $url,
    array(
      'headers'   => array(
        'Content-Type' => 'application/json; charset=utf-8',
        'Authorization' => 'Bearer '.get_option('pnmbr_access_token') ),
      'method'    => ( $service_id ? 'PUT' : 'POST' ),
      'timeout' => 300,
      'body'		=> json_encode($body),
    )
  );
  $res = json_decode($req['body'],true);

  if ($res['error'] === "invalid_token" && $retry) {
    return refresh_token( function(){ update_service($product, $service_id, false); } );
  }
  if ($res['id']) {
    update_post_meta($product->get_id(), 'ca_service_id', $res['id']); 
  } else {
    var_dump($res);
  }
  return $res;
}


function update_sale($order, $customer_id, $sale_id, $retry = true) {
      if (!$customer_id) {var_dump($customer_id); return false; }

      $url_sale = 'https://api.contaazul.com/api/v1/sales'.($sale_id ? '/'.$sale_id : '');

      $services = [];
      foreach ($order->get_items() as $item) {
        $product = wc_get_product($item->get_product_id());
        $ca_service_id = get_post_meta($product->get_id(), 'ca_service_id', true);
        if ($ca_service_id) {
          $services[] = [
            "description"=> $item->get_name(),
            "quantity"=> $item->get_quantity(),
            "service_id"=> $ca_service_id,
            "value"=> $product->get_price() 
          ];
        }
      };

      
      $body_sale = array(
        "number" => $order->get_id(),
        "emission" => $order->get_date_created()->format("Y-m-d\TH:i:s.vO"),
        "status" => "COMMITTED",
				"customer_id" 		=> $customer_id,
        "notes" => $order->get_customer_note(),
        "discount" => [
          "measure_unit" => "VALUE",
          "rate" => $order->get_total_discount()
        ],
        "services" => $services,
        "payment" => [
          "type" => "CASH",
          "installments" => [
            [
              "number" => 1,
              "value" => $order->get_total(),
              "due_date" => $order->get_date_created()->format("Y-m-d\TH:i:s.vO"),
              "status" => $order->get_status()
            ]
          ]
            ],
        "shipping_cost" => $order->get_shipping_total(),
      );
      
       $req = wp_remote_post( $url_sale,
				array(
					'headers'   => array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Authorization' => 'Bearer '.get_option('pnmbr_access_token') ),
					'method'    => ( $sale_id ? 'PUT' : 'POST' ),
					'timeout' => 300,
					'body'		=> json_encode($body_sale),
				)
      );

      $res = json_decode($req['body'],true);
      if ($res['code'] === 'INTEGRATION_ERROR' && $retry) return update_sale($order, $customer_id, null, false); //if it fails, it creates a new sale.
      if ($res['error'] === "invalid_token" && $retry) {
        return refresh_token( function(){ update_sale($order, $customer_id, $sale_id, false); } );
      }
      if ($res['id']) { 
        update_post_meta($order->get_id(), 'sale_id', $res['id']); 
        $order->add_order_note( 'CA: Sale ID '.$res['id'] );
        $order->save();
      } else {
        var_dump($res);
      }
      return $res;
}

function update_customer($order, $customer_id = null, $retry = true) {
          $url_customer = 'https://api.contaazul.com/api/v1/customers'.($customer_id ? '/'.$customer_id : '');

					$body_customer = array(
            "name"	=> "{$order->billing_first_name} {$order->billing_last_name}",
            "company_name" => "$order->billing_company",
            "email" => $order->billing_email,
            "mobile_phone" => $order->billing_phone,
            "person_type" => "NATURAL",  
            "document" => $order->billing_cpf,
            "date_of_birth" => "",
            "address" 		=> [
              "zip_code" => $order->shipping_postcode ?: $order->billing_postcode,
              "street" => $order->shipping_address_1 ?: $order->billing_address_1,
              "number" => $order->shipping_number ?: $order->billing_number,
              "complement" => $order->shipping_address_2 ?: $order->billing_address_2,
              "neighborhood" => $order->shipping_neighborhood ?: $order->billing_neighborhood
            ]
					);
          $req = wp_remote_post( $url_customer,
            array(
              'headers'   => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer '.get_option('pnmbr_access_token') ),
              'method'    => ( $customer_id ? 'PUT' : 'POST' ),
              'timeout' => 75,
              'body'		=> json_encode($body_customer),
            )
          );
          $res = json_decode($req['body'],true);
          if ($res['code'] === 'INTEGRATION_ERROR' && $retry) return update_customer($order, null, false); //if it fails, it creates a new client.
          if ($res['error'] === "invalid_token" && $retry) {
            return refresh_token( function(){ update_customer($order, $customer_id, false); } );
          }
          if ($res['id']) { 
            update_post_meta($order->get_user_id(), 'customer_id', $res['id']); 
            $order->save();
          } else {
            var_dump($res);
          }
          return $res;
}

function refresh_token(Callable $then) {
  $CLIENT_ID = get_option("pnmbr_client_id");
  $CLIENT_SECRET = get_option("pnmbr_client_secret");
  $REFRESH_TOKEN = get_option( 'pnmbr_refresh_token');

  $authbody = ["grant_type"=> "refresh_token", "refresh_token" => $REFRESH_TOKEN];
    $request = wp_remote_post( "https://api.contaazul.com/oauth2/token",
				array(
					'headers'   => array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Authorization' => 'Basic '.base64_encode("{$CLIENT_ID}:{$CLIENT_SECRET}") ),
					'method'    => "POST",
					'timeout' => 600,
					'body'		=> json_encode($authbody),
				)
      );
    $response = json_decode($request['body'],true);
    if ($response['access_token']) {
      setup_token($response);
    } else {
      var_dump($response);
    }
  return $then();
}

function setup_token($response) {
  update_option('pnmbr_access_token', $response['access_token']);
  update_option('pnmbr_refresh_token', $response['refresh_token']);
  update_option('pnmbr_expires_in', $response['expires_in']);
  return true;
}