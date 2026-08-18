<?php
/**
 * WooCommerce Blocks Integration for Konekpay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_Konekpay_Blocks_Support extends AbstractPaymentMethodType {
    
    protected $name = 'konekpay';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_konekpay_settings', array() );
    }

    public function is_active() {
        $enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
        return $enabled === 'yes';
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-konekpay-blocks-integration',
            plugins_url( 'assets/js/konekpay-blocks.js', __FILE__ ),
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ),
            '1.0.0',
            true
        );

        return array( 'wc-konekpay-blocks-integration' );
    }

    public function get_payment_method_data() {
        return array(
            'title'       => isset( $this->settings['title'] ) ? $this->settings['title'] : 'Konekpay (VA, QRIS, Alfamart)',
            'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : 'Bayar aman menggunakan bank transfer Virtual Account (VA), QRIS, atau Alfamart via Konekpay.',
            'supports'    => array( 'products' ),
        );
    }
}
