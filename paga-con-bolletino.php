<?php
/*
Plugin Name: Paga con Bollettino
Plugin URI: https://wordpress.org/plugins/Bollettino Postale-woocommerce-gateway/
Description:  Pay with Bollettino Postale on Woocommerce
Version: 1.0
Author: Elena Caramanico
Author URI: http://www.caygri.com
License: GPLv2 or later
*/
add_action('plugins_loaded', 'woocommerce_ppay_Init');

function woocommerce_ppay_Init() {
    if (!class_exists('WC_Payment_Gateway'))
        return;

class WC_Gateway_PPAY extends WC_Payment_Gateway {

    public function __construct() { 
		$this->id				= 'ppay';
		$this->icon 			= apply_filters('woocommerce_ppay_icon', '');
		$this->has_fields 		= false;
		$this->method_title     = __( 'Bollettino Postale)', 'woocommerce' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		
		// Define user set variables
		$this->title 			= $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description' );
		$this->ppay_number  	= $this->get_option( 'ppay_number' );
		$this->ppay_user   		= $this->get_option( 'ppay_user' );
		
		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    	add_action( 'woocommerce_thankyou_ppay', array( $this, 'thankyou_page' ) );

		// Customer Emails
    	add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 2 );
    } 

	/**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields() {
    
    	$this->form_fields = array(
			'enabled' => array(
							'title' => __( 'Abilita/Disabilita', 'woocommerce' ), 
							'type' => 'checkbox', 
							'label' => __( 'Abilita Bollettino Postale', 'woocommerce' ), 
							'default' => 'yes'
						), 
			'title' => array(
							'title' => __( 'Titolo', 'woocommerce' ), 
							'type' => 'text', 
							'description' => __( 'Definisci il titolo del sistema di pagamento.', 'woocommerce' ), 
							'default' => __( 'Bollettino Postale', 'woocommerce' )
						),
			'description' => array(
							'title' => __( 'Messaggio personalizzato', 'woocommerce' ), 
							'type' => 'textarea', 
							'description' => __( 'Comunica al cliente come effettuare il pagamento. Segnala che la merce non sarà inviata fino al ricevimento del pagamento', 'woocommerce' ), 
							'default' => __('Compila e paga con il bollettino. Nella causare specifica il numero d\'ordine e spediremo l\'ordine', 'woocommerce')
						),
			'ppay_number' => array(
							'title' => __( 'Numero Conto', 'woocommerce' ), 
							'type' => 'text', 
							'description' => '', 
							'default' => ''
						),
			'ppay_user' => array(
							'title' => __( 'Intestatario del Conto ', 'woocommerce' ),
							'type' => 'text',
							'description' => '',
							'default' => ''
						),

			);
    
    } // End init_form_fields()
    
	/**
	 * Admin Panel Options 
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
    	?>
    	<h3><?php _e('Bollettino Postale (o ricaricabile)', 'woocommerce'); ?></h3>
    	<p><?php _e('Permetti pagamenti attraverso Bollettino Postale o sistema similare', 'woocommerce'); ?></p>
    	<table class="form-table">
    	<?php
    		// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    	?>
		</table><!--/.form-table-->
    	<?php
    } // End admin_options()


    /**
    * Output for the order received page.
    **/
    

    function thankyou_page() {
		if ( $description = $this->get_description() )
        	echo wpautop( wptexturize( wp_kses_post( $description ) ) );

		echo '<h2>' . __( 'Informazioni di pagamento', 'woocommerce' ) . '</h2>';

		echo '<ul class="order_details ppay_details">';

		$fields = apply_filters('woocommerce_ppay_fields', array(
			'ppay_number'=> __('Numero Bollettino Postale', 'woocommerce'),
			'ppay_user'=> __('Intestatario Bollettino Postale', 'woocommerce')
		));

		foreach ( $fields as $key=>$value ) {
		    if ( ! empty( $this->$key ) ) {
		    	echo '<li class="' . esc_attr( $key ) . '">' . esc_attr( $value ) . ': <strong>' . wptexturize( $this->$key ) . '</strong></li>';
		    }
		}

		echo '</ul>';
    }
    
    /**
    * Add content to the WC emails.
    **/
    function email_instructions( $order, $sent_to_admin ) {
    	
    	if ( $sent_to_admin ) return;
    	
    	if ( $order->status !== 'on-hold') return;
    	
    	if ( $order->payment_method !== 'ppay') return;
    	
		if ( $description = $this->get_description() )
        	echo wpautop( wptexturize( $description ) );
		
		?><h2><?php _e('Informazioni', 'woocommerce') ?></h2><ul class="order_details ppay_details"><?php
		
		$fields = apply_filters('woocommerce_ppay_fields', array(
			'ppay_number'=> __('Numero Bollettino Postale (o ricaricabile)', 'woocommerce'),
			'ppay_user'=> __('Intestatario Bollettino Postale', 'woocommerce')
		));
		
		foreach ($fields as $key=>$value) :
		    if(!empty($this->$key)) :
		    	echo '<li class="'.$key.'">'.$value.': <strong>'.wptexturize($this->$key).'</strong></li>';
		    endif;
		endforeach;
		
		?></ul><?php
    }

    /**
    * Process the payment and return the result
    **/
    function process_payment( $order_id ) {
    	global $woocommerce;
    	
		$order = new WC_Order( $order_id );
		
		// Mark as on-hold (we're awaiting the payment)
		$order->update_status('on-hold', __('In attesa di conferma ricarica', 'woocommerce'));
		
		// Reduce stock levels
		$order->reduce_order_stock();

		// Remove cart
		$woocommerce->cart->empty_cart();
		
		// Return thankyou redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> $this->get_return_url( $order )
		);
    }

}

	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_ppay_gateway($methods) {
		$methods[] = 'WC_Gateway_PPAY';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'woocommerce_add_ppay_gateway' );
}
