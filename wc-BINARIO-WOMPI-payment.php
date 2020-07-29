<?php
class wompi_Payment_Gateway extends WC_Payment_Gateway
{
    function __construct()
    {
        global $woocommerce;
        $this->id = "binario-wompi-payment";
        $this->method_title = __("WOMPI - El Salvador - Integracion transparente", 'wompi-payment');
        $this->method_description = __("WOMPI - El Salvador Payment Gateway Plug-in para WooCommerce Integracion transparente", 'wompi-payment');
        $this->title = __("WOMPI - El Salvador", 'wompi-payment');
        $this->icon = apply_filters('woocommerce_wompi_icon', $woocommerce->plugin_url() . '/../woocommerce-binario-wompi-sv-plugin/assets/images/payment-methods.png');
        $this->has_fields = true;
        $this->init_form_fields();
        $this->init_settings();
        $this->supports = array('products', 'refunds', 'default_credit_card_form');
        foreach ($this->settings as $setting_key => $value)
        {
            $this->$setting_key = $value;
        }

        add_action('admin_notices', array(
            $this,
            'do_ssl_check'
        ));
        add_action('woocommerce_api_wc_gateway_wompi', array(
            $this,
            'validate_wompi_return'
        ));
        add_action('woocommerce_api_wc_webhook_wompi', array(
            $this,
            'validate_wompi_webhook'
        ));

        if (is_admin())
        {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
        }
    }
    public function validate_wompi_webhook()
    {
        global $woocommerce;
        $headers = getallheaders();
        if (!function_exists('write_log'))
        {
            function write_log($log)
            {
                if (true === WP_DEBUG)
                {
                    if (is_array($log) || is_object($log))
                    {
                        error_log(print_r($log, true));
                    }
                    else
                    {
                        error_log($log);
                    }
                }
            }

        }
        $entityBody = @file_get_contents('php://input');
        write_log('entra en el validate_wompi_webhook ************************************ ' . json_encode($headers) . ' ************');

        write_log('entra en el validate_wompi_webhook ************************************ BODY: ' . $entityBody . ' ************');
        $arrayResult = json_decode($entityBody);
        $order_id = $arrayResult->{'EnlacePago'}->{'IdentificadorEnlaceComercio'};
        $customer_order = new WC_Order($order_id);
        write_log('entra en el validate_wompi_webhook ********** ORDER ID: ' . json_encode($order_id) . ' *****');

        $sig = hash_hmac('sha256', $entityBody, $this->client_secret);
        $hash = $headers['Wompi_Hash'];
        update_post_meta($order_id, '_wc_order_wompi_Hash', $hash);
        update_post_meta($order_id, '_wc_order_wompi_cadena', $entityBody);
        write_log('entra en el validate_wompi_webhook ********** HASH: ' . $hash . ' *****');

        if ($sig == $hash)
        {
            write_log('entra en el validate_wompi_webhook ********** HASH VALIDO  *****');
            update_post_meta($order_id, '_wc_order_wompi_StatusHash', $sig . ' valido:');
            $customer_order->add_order_note(__('wompi pago completado WH.', 'wompi-payment'));

            $customer_order->payment_complete();
            update_post_meta($order_id, '_wc_order_wompi_transactionid', $arrayResult->{'idTransaccion'}, true);
            $woocommerce
                ->cart
                ->empty_cart();
            header('HTTP/1.1 200 OK');
        }
        else
        {
            write_log('entra en el validate_wompi_webhook ********** HASH NO VALIDO  *****');

            update_post_meta($order_id, '_wc_order_wompi_StatusHash', $sig . ' No valido:');
            $customer_order->add_order_note(__('wompi hash no valido WH.', 'wompi-payment'));
            header('HTTP/1.1 200 OK');
        }

    }
    public function validate_wompi_return()
    {
        global $woocommerce;
        $order_id = sanitize_text_field($_GET['identificadorEnlaceComercio']);
        $customer_order = new WC_Order($order_id);
        $idTransaccion = sanitize_text_field($_GET['idTransaccion']);
        $idEnlace = sanitize_text_field($_GET['idEnlace']);
        $monto = sanitize_text_field($_GET['monto']);
        $hash = sanitize_text_field($_GET['hash']);
        $cadena = $order_id . $idTransaccion . $idEnlace . $monto;
        $sig = hash_hmac('sha256', $cadena, $this->client_secret);

        $authcode = get_post_meta($order_id, '_wc_order_wompi_authcode', true);
        if ($authcode == null)
        {

            update_post_meta($order_id, '_wc_order_wompi_Hash', $hash);
            update_post_meta($order_id, '_wc_order_wompi_cadena', $cadena);

            if ($sig == $hash)
            {
                update_post_meta($order_id, '_wc_order_wompi_StatusHash', $sig . ' valido:');
                $customer_order->add_order_note(__('wompi pago completado.', 'wompi-payment'));

                $customer_order->payment_complete();
                update_post_meta($order_id, '_wc_order_wompi_transactionid', $idTransaccion, true);
                $woocommerce
                    ->cart
                    ->empty_cart();
                wp_redirect(html_entity_decode($customer_order->get_checkout_order_received_url()));

            }
            else
            {
                update_post_meta($order_id, '_wc_order_wompi_StatusHash', $sig . ' No valido:');
                $customer_order->add_order_note(__('wompi hash no valido.', 'wompi-payment'));
                home_url();
            }
        }
        else
        {
            wp_redirect(html_entity_decode($customer_order->get_checkout_order_received_url()));
        }
    }
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Activar / Desactivar', 'wompi-payment') ,
                'label' => __('Activar este metodo de pago', 'wompi-payment') ,
                'type' => 'checkbox',
                'default' => 'no',
            ) ,
            'title' => array(
                'title' => __('Título', 'wompi-payment') ,
                'type' => 'text',
                'desc_tip' => __('Título de pago que el cliente verá durante el proceso de pago.', 'wompi-payment') ,
                'default' => __('Tarjeta de crédito VISA o MASTERCARD', 'wompi-payment') ,
            ) ,
            'description' => array(
                'title' => __('Descripción', 'wompi-payment') ,
                'type' => 'textarea',
                'desc_tip' => __('Descripción de pago que el cliente verá durante el proceso de pago.', 'wompi-payment') ,
                'default' => __('Pague con seguridad usando su tarjeta de crédito.', 'wompi-payment') ,
                'css' => 'max-width:350px;'
            ) ,
            'TextoWompi' => array(
                'title' => __('Título del pago', 'wompi-payment') ,
                'type' => 'text',
                'desc_tip' => __('Título que aparece en la descripcion del pago en wompi.', 'wompi-payment') ,
                'default' => __('Carrito de la Compra', 'wompi-payment') ,
            ) ,
            'client_id' => array(
                'title' => __('App ID', 'wompi-payment') ,
                'type' => 'text',
                'desc_tip' => __('ID de clave de seguridad del panel de control del comerciante.', 'wompi-payment') ,
                'default' => '',
            ) ,
            'client_secret' => array(
                'title' => __('Api Secret', 'wompi-payment') ,
                'type' => 'text',
                'desc_tip' => __('ID de clave de api del panel de control del comerciante.', 'wompi-payment') ,
                'default' => '',
            ) ,
            'api_email' => array(
                'title' => __('Correo para notificar', 'wompi-payment') ,
                'type' => 'text',
                'desc_tip' => __('El correo del comercio donde se notificará los pagos.', 'wompi-payment') ,
                'default' => '',
                'description' => 'Se puede colocar más de un correo separado por comas Ejemplo: correo@gmail.com,correo2@gmail.com'
            ) ,
            'api_notifica' => array(
                'title' => __('Se notificará al cliente?', 'wompi-payment') ,
                'type' => 'select',
                'options' => array(
                    'true' => 'SI',
                    'false' => 'NO'
                ) ,
                'desc_tip' => __('Si se notificará por correo al cliente el pago.', 'wompi-payment') ,
                'default' => 'true'
            ) ,
        );
    }
    public function process_payment($order_id)
    {
        global $woocommerce;
        $customer_order = new WC_Order($order_id);

        $client_id = $this->client_id;
        $client_secret = $this->client_secret;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://id.wompi.sv/connect/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=client_credentials&client_id=" . $client_id . "&client_secret=" . $client_secret . "&audience=wompi_api",
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
            ) ,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        if ($err)
        {
            echo "cURL Error #:" . $err;
        }
        else
        {
            $arrayResult = json_decode($response);
            $token = $arrayResult->{'access_token'};
            echo '<script>console.log("PHP token: ' . $token . '")</script>';
            $order = wc_get_order($order_id);
            $url_redi = $this->get_return_url($order);

            $data = $customer_order->get_data();
            
            $card_number = isset($_POST['binario-wompi-payment-card-number']) ? str_replace(array(' ', '-'), '', wc_clean($_POST['binario-wompi-payment-card-number'])) : '';
            $card_cvv = isset($_POST['binario-wompi-payment-card-cvc']) ? wc_clean($_POST['binario-wompi-payment-card-cvc']) : '';
            $card_expiry = isset($_POST['binario-wompi-payment-card-expiry']) ? preg_split('/\s?\/\s?/', wc_clean($_POST['binario-wompi-payment-card-expiry']), 2) : '';
            $flag = 0;
            if ($card_expiry[0] == "") {
                wc_add_notice('Faltan algunos detalles. Por favor ingresa un fecha de expiracion valida', 'error');
                $flag = 1;
                return;
            }
            if ($card_expiry[1] == "") {
                wc_add_notice('Faltan algunos detalles. Por favor ingresa un fecha de expiracion valida', 'error');
                $flag = 1;
                return;
            }

            if ($card_expiry[0] > 13) {
                wc_add_notice('Ocurrio un error. Por favor ingresa un mes valido.', 'error');
                $flag = 1;
                return;
            }
            if (strlen($card_expiry[1]) != 2) {
                wc_add_notice('Ocurrio un error. Por favor ingresa un año valido', 'error');
                $flag = 1;
                return;
            }
            $month = date('m');
            $year = date('y');

            if ($card_expiry[0] < $month && $card_expiry[1] <= $year) {
                wc_add_notice('Ocurrio un error. La fecha de expiacion no puede estar en el pasado.', 'error');
            }

            if ($card_expiry[1] < $year) {
                wc_add_notice('Ocurrio un error. La fecha de expiacion no puede estar en el pasado.', 'error');
                $flag = 1;
                return;
            }
            if ($card_expiry[0] < $month && $card_expiry[1] == $year) {
                wc_add_notice('Ocurrio un error. La fecha de expiacion no puede estar en el pasado.', 'error');
                $flag = 1;
                return;
            }
            if(strlen($card_number) >= 13 && strlen($card_number) < 17){
            }else{
                wc_add_notice('Ocurrio un error. Verifica el numero de tarjeta que ingresaste.', 'error');
                $flag = 1;
                return;
            }

            if($card_cvv == ""){
                wc_add_notice('Faltan algunos detalles. Ingresa un Codigo de Seguridad de la tarjeta valido.', 'error');
                $flag = 1;
                return;
            }
            $tarjetaCreditoDebito = array(
                "numeroTarjeta"=> $card_number,
                  "cvv"=> $card_cvv,
                  "mesVencimiento"=> $card_expiry[0],
                  "anioVencimiento"=> '20'.$card_expiry[1]
            );
            $configuracion = array(
                "emailsNotificacion"=> $this->api_email,
                  "urlWebhook"=> home_url() . '/?wc-api=WC_webhook_Wompi',
                  "notificarTransaccionCliente"=> true
            );
            $datosAdicionales = array(
                "additionalProp1" => $order_id
            );
            $payload_data = array(
                "tarjetaCreditoDebido"=> $tarjetaCreditoDebito,
                "monto"=> $customer_order->order_total,
                "emailCliente"=>  $customer_order->	get_billing_email(),
                "nombreCliente"=> $customer_order->get_billing_first_name().' '.$customer_order->get_billing_last_name(),
                "formaPago"=> "PagoNormal",
                "configuracion"=> $configuracion,
                "datosAdicionales"=> $datosAdicionales
            );
            $postdata = json_encode($payload_data);
            $curl2 = curl_init();
            $arrev = array(
                CURLOPT_URL => "https://api.wompi.sv/TransaccionCompra",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $postdata,
                CURLOPT_HTTPHEADER => array(
                    "authorization: Bearer " . $token,
                    "content-type: application/json"
                )
            );

            curl_setopt_array($curl2, $arrev);
            $response = curl_exec($curl2);
            $info = curl_getinfo($curl2);
            $arrayResult = json_decode($response);
            if( $info['http_code'] != '200')
            {
                $err = curl_error($curl2);
                echo "cURL Error #:" . $err;
                $urlEnlace = '';
                $result = 'fail';
                $errorMessageArrayString = '';
                $tmp_array = $arrayResult->{'mensajes'};
                foreach ($tmp_array as $array) {
                    echo $array;
                    $errorMessageArrayString = $errorMessageArrayString.' '. $array.'.';
                }
                wc_add_notice( 'Error de pago: '.$errorMessageArrayString, 'error');
                return;
            } else {
                update_post_meta($order_id, '_wc_order_wompi_transactionid', $arrayResult->{'idTransaccion'}, true);
                $customer_order->payment_complete();
                $woocommerce->cart->empty_cart();
                $result = 'success';
                $urlEnlace = $this->get_return_url( $customer_order );
            }
            curl_close($curl2);
            return array(
                'result' => $result,
                'redirect' => $urlEnlace 
            );
           
            // echo "cURL Error #:" . $err;
            
            // echo "cURL Error #:" . $err;
            // if ($err!='')
            // {
               
            // } else {
            //     // 
            //     // $urlEnlace = home_url() . '/?wc-api=WC_Gateway_Wompi';
            //     // Remove cart
               
            // }
        }

    }

}

add_action('woocommerce_admin_order_data_after_billing_address', 'show_WOMPI_info', 10, 1);
function show_WOMPI_info($order)
{
    $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
    echo '<p><strong>' . __('WOMPI Transaction Id') . ':</strong> ' . get_post_meta($order_id, '_wc_order_wompi_transactionid', true) . '</p>';
}
?>