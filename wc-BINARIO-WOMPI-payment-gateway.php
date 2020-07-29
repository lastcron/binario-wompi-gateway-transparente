<?php
/*
Plugin Name: WOMPI - El Salvador Gateway Transparente
Plugin URI: https://github.com/lastcron/binario-wompi-gateway-transparente
Description: Plugin WooCommerce para integrar la pasarela de pago Wompi El Salvador permitiendo hacer el pago de forma transparente sin ser redirigido a la pagina de WOMPI El Salvador.
Version: 1.0
Author: BINARIO Software Factory
Author URI: https://binario.com.sv
*/

  // Payment Gateway with WooCommerce infinitechsv
  add_action( 'plugins_loaded', 'BINARIO_WOMPI_payment_init', 0 );

  function BINARIO_WOMPI_payment_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    include_once( 'wc-BINARIO-WOMPI-payment.php' );
    add_filter( 'woocommerce_payment_gateways', 'add_BINARIO_WOMPI_payment_gateway' );
    function add_BINARIO_WOMPI_payment_gateway( $methods ) {
      
      $methods[] = 'WOMPI_Payment_Gateway';
      return $methods;
    }
  }


  add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'BINARIO_WOMPI_payment_action_links' );
  function BINARIO_WOMPI_payment_action_links( $links ) {
    $plugin_links = array(
      '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'WOMPI-payment' ) . '</a>',
    );

    return array_merge( $plugin_links, $links );
  }