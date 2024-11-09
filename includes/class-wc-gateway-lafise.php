<?php
/**
 * WC_Gateway_LAFISE class
 *
 * @author   JARS Costa Rica <info@jarscr.com>
 * @package  WooCommerce LAFISE Payments Gateway
 * @since    1.6.10
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LAFISE Gateway.
 *
 * @class    WC_Gateway_LAFISE
 * @version  1.10.0
 */
class WC_Gateway_LAFISE extends WC_Payment_Gateway {

	/**
	 * Payment gateway instructions.
	 * @var string
	 *
	 */
	protected $instructions;

	/**
	 * Whether the gateway is visible for non-admin users.
	 * @var boolean
	 *
	 */
	protected $hide_for_non_admin_users;

	/**
	 * Unique id for the gateway.
	 * @var string
	 *
	 */
	public $id = 'lafise';

	public $lafise;

	public $liveurl;

	public $liveurl_token;

	public $devurl_token;

	public $devurl;
	public $success_message;

	public $failed_message;

	public $description;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		
		$this->icon               = apply_filters( 'woocommerce_lafise_gateway_icon', WC_LAFISE_Payments::plugin_abspath() . 'assets/images/logo.png' );
		$this->has_fields         = true;
		$this->supports           = array(
			'products'
		);

		$this->method_title       = _x( 'Banco LAFISE', 'Banco LAFISE Payment method', 'woocommerce-gateway-lafise' );
		$this->method_description = __( 'Procesa pago en linea con Banco LAFISE.', 'woocommerce-gateway-lafise' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title                    = $this->get_option( 'title' );
		$this->description              = $this->get_option( 'description' );
		$this->instructions             = $this->get_option( 'instructions', $this->description );
		$this->hide_for_non_admin_users = $this->get_option( 'hide_for_non_admin_users' );
		$this->lafise['user']     		= $this->get_option('lafise_user');
        $this->lafise['pass']     		= $this->get_option('lafise_pass');
        $this->lafise['merchant'] 		= $this->get_option('lafise_merchant');
        $this->lafise['terminal'] 		= $this->get_option('lafise_terminal');
        $this->lafise['mode']     		= $this->get_option('devmode');
        $this->lafise['apikey']   		= $this->get_option('lafise_apikey');
        $this->lafise['status']   		= $this->get_option('lafise_status');
        $this->success_message    		= $this->get_option('success_message');
        $this->failed_message     		= $this->get_option('failed_message');

		$this->liveurl            = 'https://lafise-cr.portalpos.com/Interfaces/api/Pay/1/0/Sale';
		$this->liveurl_token      = 'https: //lafise-cr.portalpos.com/Interfaces/api/Auth/1/0/tokenEx';
		$this->devurl_token       = 'https://apololab.kinposlabs.com/Interfaces/api/Auth/1/0/tokenEx';
		$this->devurl             ='https://apololab.kinposlabs.com/Interfaces/api/Pay/1/0/Sale';

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_valid_request_payment_lafise', array( $this, 'successful_request' ), 10, 2 );
		add_action ( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ), 10 );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-lafise' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Banco LAFISE Payments', 'woocommerce-gateway-lafise' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-gateway-lafise' ),
				'type'        => 'text',
				'description' => __( 'Ingrese el nombre que desea vean sus clientes.', 'woocommerce-gateway-lafise' ),
				'default'     => _x( 'Banco LAFISE', 'Tarjeta de Crédito/Débito', 'woocommerce-gateway-lafise' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-gateway-lafise' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce-gateway-lafise' ),
				'default'     => __( 'The goods are yours. No money needed.', 'woocommerce-gateway-lafise' ),
				'desc_tip'    => true,
			),
			'orden_url'       => array(
				'title'       => __('URL de Retorno', 'woocommerce-gateway-lafise'),
				'type'        => 'text',
				'description' => __('Ingrese el URL de retorno para WooCommerce'),
				'default'     => __('/checkout/order-received/', 'woocommerce-gateway-lafise')
			),
			'success_message' => array(
				'title'       => __('Mensaje de Pago Exitoso', 'woocommerce-gateway-lafise'),
				'type'        => 'textarea',
				'description' => __('Ingrese el mensaje que se muestra al realizarse un pago exitoso.', 'woocommerce-gateway-lafise'),
				'default'     => __('Su pago se ha realizado exitosamente', 'woocommerce-gateway-lafise')
			),
			'failed_message'  => array(
				'title'       => __('Mensaje de Pago Fallido', 'woocommerce-gateway-lafise'),
				'type'        => 'textarea',
				'description' => __('Ingrese el mensaje que se muestra al realizase un pago fallido.', 'woocommerce-gateway-lafise'),
				'default'     => __('Su transacción ha presentado problemas.', 'woocommerce-gateway-lafise')
			),
			'lafise_status'     => array(
				'title'       => __('Estado de Pedido', 'woocommerce-gateway-lafise'),
				'type'        => 'select',
				'default'     => 'processing',
				'options'     => array('processing'=>'Procesando','completed'=>'Completado'),
				'description' => __('Seleccione el estado al completar la compra.', 'woocommerce-gateway-lafise')
			),
			'lafise_user'     => array(
				'title'       => __('Usuario', 'woocommerce-gateway-lafise'),
				'type'        => 'text',
				'description' => __('Ingrese el usuario de API LAFISE.', 'woocommerce-gateway-lafise')),
			'lafise_pass'     => array(
				'title'       => __('Contraseña', 'woocommerce-gateway-lafise'),
				'type'        => 'text',
				'description' => __('Ingrese la contraseña de API LAFISE.', 'woocommerce-gateway-lafise')
			),
			'lafise_merchant' => array(
				'title'       => __('Comercio', 'woocommerce-gateway-lafise'),
				'type'        => 'text',
				'description' => __('Merchant ID LAFISE.', 'woocommerce-gateway-lafise')
			),
			'lafise_terminal' => array(
				'title'       => __('Terminal', 'woocommerce-gateway-lafise'),
				'type'        => 'text',
				'description' => __('Terminal ID LAFISE.', 'woocommerce-gateway-lafise')
			),
			'lafise_apikey'   => array(
				'title'       => __('API Key', 'woocommerce-gateway-lafise'),
				'type'        => 'text',
				'description' => __('API Key LAFISE.', 'woocommerce-gateway-lafise')
			),
			'devmode'         => array(
				'title'   => __('Desarrollo', 'woocommerce-gateway-lafise'),
				'type'    => 'checkbox',
				'label'   => __('Activar modo Desarrollo', 'woocommerce-gateway-lafise'),
				'default' => 'no')
			
		);
	}

	// /**
	//  * Process the payment and return the result.
	//  *
	//  * @param  int  $order_id
	//  * @return array
	//  */
	// public function process_payment( $order_id ) {

	// 	$payment_result = $this->get_option( 'result' );
	// 	$order = wc_get_order( $order_id );

	// 	if ( 'success' === $payment_result ) {
	// 		// Handle pre-orders charged upon release.
	// 		if (
	// 				class_exists( 'WC_Pre_Orders_Order' )
	// 				&& WC_Pre_Orders_Order::order_contains_pre_order( $order )
	// 				&& WC_Pre_Orders_Order::order_will_be_charged_upon_release( $order )
	// 		) {
	// 			// Mark order as tokenized (no token is saved for the lafise gateway).
	// 			$order->update_meta_data( '_wc_pre_orders_has_payment_token', '1' );
	// 			$order->save_meta_data();
	// 			WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
	// 		} else {
	// 			$order->payment_complete();
	// 		}

	// 		// Remove cart
	// 		WC()->cart->empty_cart();

	// 		// Return thankyou redirect
	// 		return array(
	// 			'result' 	=> 'success',
	// 			'redirect'	=> $this->get_return_url( $order )
	// 		);
	// 	} else {
	// 		$message = __( 'Order payment failed. To make a successful payment using LAFISE Payments, please review the gateway settings.', 'woocommerce-gateway-lafise' );
	// 		$order->update_status( 'failed', $message );
	// 		throw new Exception( $message );
	// 	}
	// }

	function convertUrlQuery($query)
	{
		$queryParts = explode('?', $query);

		$params = array();
		foreach ($queryParts as $param) {
			$item             = explode('=', $param);
			$params[$item[0]] = $item[1];
		}

		return $params;
	}

	public function payment_fields()
        {


            

            if ($this->description) {
                echo '<p style="font-size:16px; margin-left: 15px; font-weight:bold;">' . $this->description . '';
            }

            $var_info = print_r($_POST, true);
            if (strpos($var_info, 'error_mensaje') !== false) {
                echo '<hr><span style="color:red; font-size:12px; font-weight:bold">';
                echo 'Se ha presentado un problema: ';

                $varr = $_POST['post_data'];

                $varr = preg_replace("/%u([0-9a-f]{3,4})/i", "&#x\\1;", urldecode($varr));
                $varr = html_entity_decode($varr, null, 'UTF-8');
                $varr = str_replace("#lafise", "", $varr);

                parse_str($varr, $myArray);
                $parts = $this->convertUrlQuery($myArray['_wp_http_referer']);
                echo $parts['error_mensaje'];
                echo '</span><hr>
                <script>
                (function($) {
                    $("html, body").animate({ scrollTop: $("#payment").offset().top }, 2000);
                })(jQuery);
                </script>';
            }

			?>


			<section class="jarslafise-wrapper">
				<div class="credit-card-wrapper">
					<div class="first-row form-group">
						<div class="col-sm-8 controls">
							<label class="control-label"><?php echo __('Numero de Tarjeta', 'woocommerce') ?>
							</label>
							<input class="number credit-card-number form-control" type="text" name="ccNoCR1" id="ccNoCR1"
								inputmode="numeric" autocomplete="cc-number" autocompletetype="cc-number"
								x-autocompletetype="cc-number" onkeyup="cc_format(event);"
								placeholder="&#149;&#149;&#149;&#149; &#149;&#149;&#149;&#149; &#149;&#149;&#149;&#149; &#149;&#149;&#149;&#149;">
						</div>
						<div class="col-sm-4"><img
								src="<?php echo WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)); ?>/assets/images/credit-card-48.png"
								id="card-type">
							<input type="hidden" name="cardtypeCR1" id="cardtypeCR1" value="">
						</div>

					</div>
					<div class="second-row form-group">

						<div class="col-sm-4 controls">
							<label class="control-label"><?php echo __('Vencimiento', 'woocommerce') ?>
							</label>
							<input class="expiration-month-and-year form-control" type="text" name="expMonthYearCR1"
								id="expMonthYearCR1" placeholder="MM/YY" onkeyup="formatString(event);" maxlength='5'>
						</div>
						<div class="col-sm-4 controls">
							<label class="control-label"><?php _e('Codigo CVV', 'woocommerce')?>
							</label>
							<input class="security-code form-control" inputmode="numeric" type="text" name="cvvNoCR1" id="cvvNoCR1"
								placeholder="&#149;&#149;&#149;">
						</div>
					</div>

				</div>
			</section>

			<?php
        
        }


		/**
         * Proceso de Pago
         **/
        function process_payment($order_id)
        {
            global $woocommerce;
            $locale = apply_filters('plugin_locale', is_admin() ? get_user_locale() : get_locale(), $domain);
            $mofile = $domain . '-' . $locale . '.mo';


            $order = new WC_Order($order_id);

           
            $data_to_send = array("grant_type" => "client_credentials",
                                    "username"  => $this->lafise['user'],
                                    "password"  => $this->lafise['pass']);

            if (empty($_POST['ccNoCR1']) || empty($_POST['cvvNoCR1']) || empty($_POST['expMonthYearCR1'])) {
            if ($mofile != '-es_CR.mo') {
                throw new Exception(__('Card number, expiration date and CVC are required fields', 'jarscrlafise'));
            } else {
                throw new Exception(__('N&#250;mero de Tarjeta, Fecha de Expiraci&#243;n y CVC son requeridos', 'jarscrlafise'));
            }
        }
            if($this->lafise['mode']=="no"){
                 $api_auth_url = $this->liveurl_token;
                 $api_url      = $this->liveurl;
            }else{
                $api_auth_url = $this->devurl_token;
                $api_url      = $this->devurl;
            } 
           
            $response_token = wp_remote_post($api_auth_url, array(
                'method'   => 'POST',
                'timeout'  => 90,
                'blocking' => true,
                'headers'  => array('content-type' => 'application/json'),
                'body'     => json_encode($data_to_send, true),
            ));

            $api_token     = json_decode(wp_remote_retrieve_body($response_token), true)['access_token'];
            $transport_key = json_decode(wp_remote_retrieve_body($response_token), true)['serverTransportKey'];


           // error_log($api_token, 3, plugin_dir_path(__FILE__) . 'log_token.txt');

                $TarjetaJSON = array("accountNumber" => str_replace(array(' ', '-'), '', $_POST['ccNoCR1']),
                    "name"                               => $order->billing_first_name . ' ' . $order->billing_last_name,
                    "expirationDate"                     => array(
                        "month" => substr($_POST['expMonthYearCR1'], 0, 2),
                        "year"  => substr($_POST['expMonthYearCR1'], -2),
                    ),
                    "cvv2"                               => str_replace(array(' ', '-'), '', $_POST['cvvNoCR1']),
                );


          //  error_log(print_r($TarjetaJSON,true), 3, plugin_dir_path(__FILE__) . 'log_card.txt');   
            $EncodeCard = $this->EncodeCard($TarjetaJSON, $transport_key);

            $Tax = 0;

            $OrderTotal = number_format($order->get_total(),2,'.','');

            $Pass      = $this->lafise['apikey'] . $order_id . $this->lafise['merchant'] . str_replace('.', '', $OrderTotal) . $Tax . get_woocommerce_currency();
            $Signature = hash('sha256', $Pass);
            $Signature = strtoupper($Signature);

            $date = new DateTime();
            $date->setTimezone(new DateTimeZone('America/Costa_Rica'));
            $RequestDate = $date->format('dmYGis');

            $payload = array("PurchaseData" => array("Terminal_Id" => $this->lafise['terminal'],
            "Merchant_Id"                                          => $this->lafise['merchant'],
            "Amount"                                               => str_replace('.', '', $OrderTotal),
            "Taxes"                                                => array(array("Name" => "TotalTax", "Amount" => 0, "IsIncluded" => "false", "ReturnBase" => "0")),
            "Description"                                          => "Orden de Compra No. " . $order_id,
            "Ref_1"                                                => "".$order_id."",
            "Ref_2"                                                => "".rand(1, 1000)."",
            "Ref_3"                                                => "".time()."",
            "Currency_code"                                        => get_woocommerce_currency(),
            "Request_Date"                                         => $RequestDate),
            "UserInformation"               => array(
                "Payer_id"        =>  "".str_pad($order->get_customer_id(),6, "0", STR_PAD_LEFT)."",
                "Payer_firstname" => $order->billing_first_name,
                "Payer_lastname"  => $order->billing_last_name,
                "Payer_mobile"    => $order->billing_phone,
                "Payer_phone"     => $order->billing_phone,
                "Payer_email"     => $order->billing_email,
                "Payer_region"    => 'SJ',
                "Payer_city"      => 'SAN JOSE',
                "Payer_address"   => 'SAN JOSE',
                "Payer_country"   => 'CR',
                "Payer_zip"       => "10100",
                "Payer_ip"        => str_replace('.', '', $_SERVER['REMOTE_ADDR']),
            ),
            "ApiSecurity"                   => array(
                "Message_signature" => $Signature,
                "UserName"          =>$this->lafise['user'],
            ),
            "ResponseUrl"                   => $woocommerce->cart->get_checkout_url(),
            "EncryptedCardData"             => $EncodeCard,
            "Typerequest"                   => "Production",
            "PaymentType"                   => 4);

         //   error_log(print_r($payload,true), 3, plugin_dir_path(__FILE__) . 'log_send.txt');

            // Send this payload to 4GP for processing
            $response = wp_remote_post($api_url, array(
                'method'   => 'POST',
                'body'     => json_encode($payload),
                'timeout'  => 90,
                'blocking' => true,
                'headers'  => array('authorization' => 'bearer ' . $api_token, 'content-type' => 'application/json'),
            ));

            $BodyResponse = json_decode(wp_remote_retrieve_body($response), true);
            error_log(print_r($BodyResponse,true), 3, plugin_dir_path(__FILE__) . 'log_response.txt');    

            $Status            = json_decode(wp_remote_retrieve_body($response), true)['Status'];
            $ResponseCode      = json_decode(wp_remote_retrieve_body($response), true)['ResponseCode'];
            $ResponseMessage   = json_decode(wp_remote_retrieve_body($response), true)['ResponseMessage'];
            $AuthorizationCode = json_decode(wp_remote_retrieve_body($response), true)['AuthorizationCode'];
            $Referencia        = json_decode(wp_remote_retrieve_body($response), true)['PaymentData']['Ref_1'];
            $Referencia2 = json_decode(wp_remote_retrieve_body($response), true)['PaymentData']['Ref_2'];
            $Referencia3   = json_decode(wp_remote_retrieve_body($response), true)['PaymentData']['Ref_3'];

            if (is_wp_error($response)) {
                throw new Exception(__('Hubo un problema para comunicarse con el procesador de pagos...', 'jarscrlafise'));
            }

            if (empty($response['body'])) {
                throw new Exception(__('No se puede realizar la comunicación con LAFISE. Intente luego.', 'jarscrlafise'));
            }

            // 1 or 4 means the transaction was a success
            if (($Status == 200) && ($ResponseCode == '00')) {
                // Payment successful
                $order->add_order_note(__('Pago completo.', 'jarscrlafise'));
                $order->add_order_note(__('Autorización: ' . $AuthorizationCode, 'jarscrlafise'));
                $order->add_order_note(__('Transacción LAFISE: <br>REF 1: ' . $Referencia.'<br>REF 2: '.$Referencia2.'<br>REF 3: '.$Referencia3, 'jarscrlafise'));

                // paid order marked
                $order->payment_complete();
                $OrderStatus = $this->lafise['status'];
                $order->update_status($OrderStatus);


                // this is important part for empty cart
                $woocommerce->cart->empty_cart();

                // Redirect to thank you page
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            } else {
                //transiction fail
                wc_add_notice($ResponseMessage, 'error');
                $order->add_order_note('Error: ' . $ResponseMessage.'<br>RNN:'.$Referencia.'<br>Codigo:'.$ResponseCode);


            }

        }
        /**
         * Validacion del Proceso de Pago.
         **/

        public function web_redirect($url)
        {

            echo "<html><head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";

        }

	public function EncodeCard($Tarjeta, $Pem)
    {
        $key_pem = base64_decode($Pem);
        $set     = new SimpleJWT\Keys\KeySet();
        $key     = new SimpleJWT\Keys\RSAKey($key_pem, 'pem');
        $set->add($key);

        $headers = ['alg' => 'RSA-OAEP-256', 'enc' => 'A256GCM'];
        $jwt     = new SimpleJWT\JWE($headers, json_encode($Tarjeta));

        try {
            return $jwt->encrypt($set);
        } catch (\RuntimeException $e) {

            throw new Exception(__('Se presento un problema codificando los datos. Intente de nuevo ' . $Pem, 'jarscrlafise'));
            exit();
        }

    }
}
