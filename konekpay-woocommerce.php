<?php
/**
 * Plugin Name: Konekpay WooCommerce Payment Gateway
 * Plugin URI: https://konekpay.biz.id
 * Description: Terima pembayaran otomatis (Virtual Account, QRIS, Alfamart) melalui Konekpay.
 * Version: 1.0.0
 * Author: Konekpay Support
 * Author URI: https://konekpay.biz.id
 * Developer: Konekpay Support
 * Text Domain: konekpay-woocommerce
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action( 'plugins_loaded', 'init_konekpay_gateway_class' );
add_action( 'woocommerce_blocks_loaded', 'konekpay_register_blocks_support' );

/**
 * Daftarkan integrasi blok Gutenberg untuk Konekpay
 */
function konekpay_register_blocks_support() {
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'class-wc-gateway-konekpay-blocks.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( $payment_method_registry ) {
                $payment_method_registry->register( new WC_Gateway_Konekpay_Blocks_Support() );
            }
        );
    }
}

function init_konekpay_gateway_class() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_Gateway_Konekpay extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'konekpay';
            $this->icon               = ''; // Bisa diisi URL logo di masa depan
            $this->has_fields         = false;
            $this->method_title       = 'Konekpay';
            $this->method_description = 'Terima pembayaran Virtual Account, QRIS, dan Alfamart secara instan menggunakan Konekpay.';

            // Load settings
            $this->init_form_fields();
            $this->init_settings();

            $this->title          = $this->get_option( 'title' );
            if ( empty( $this->title ) ) {
                $this->title = 'Konekpay (VA, QRIS, Alfamart)';
            }
            
            $this->description    = $this->get_option( 'description' );
            if ( empty( $this->description ) ) {
                $this->description = 'Bayar aman menggunakan bank transfer Virtual Account (VA), QRIS, atau Alfamart via Konekpay.';
            }
            
            $this->enabled        = $this->get_option( 'enabled', 'no' );
            $this->sandbox        = $this->get_option( 'environment', 'sandbox' ) === 'sandbox';
            $this->api_key        = $this->sandbox ? $this->get_option( 'sandbox_api_key' ) : $this->get_option( 'production_api_key' );

            $this->supports           = array( 'products' );

            // Log constructor call
            $log = sprintf(
                "[%s] __construct called. Enabled: %s. Sandbox: %s. Has API Key: %s\n",
                date('Y-m-d H:i:s'),
                $this->enabled,
                $this->sandbox ? 'yes' : 'no',
                empty($this->api_key) ? 'no' : 'yes'
            );
            file_put_contents( dirname(__FILE__) . '/debug_log.txt', $log, FILE_APPEND );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'check_callback' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_scripts' ) );
        }

        /**
         * Load library snap.js dan script integrasi checkout modal konekpay
         */
        public function enqueue_checkout_scripts() {
            if ( is_checkout() && ! is_order_received_page() ) {
                // Register & Load Konekpay Snap JS
                // Karena konekpay.biz.id adalah domain production, kita gunakan url https://konekpay.biz.id/snap.js
                wp_enqueue_script( 'konekpay-snap', 'https://konekpay.biz.id/snap.js', array(), '1.0.0', true );

                // Buat custom script inline untuk menangkap checkout AJAX response
                wp_register_script( 'konekpay-checkout-handler', '', array( 'jquery', 'konekpay-snap' ), '1.0.0', true );
                wp_enqueue_script( 'konekpay-checkout-handler' );

                $inline_js = "
                    jQuery(document).ready(function($) {
                        function checkKonekpayHash() {
                            var hash = window.location.hash;
                            if (hash && hash.indexOf('#konekpay-token-') === 0) {
                                // Ekstrak token dan thank you url
                                var params = hash.substring(16).split('&thankyou=');
                                var token = params[0];
                                var thankyouUrl = params[1] ? decodeURIComponent(params[1]) : '';

                                // Hapus hash dari URL agar tidak terpicu berulang-ulang
                                window.location.hash = '';

                                if (token) {
                                    // Panggil popup modal Snap Konekpay jika library snap terdeteksi
                                    if (typeof snap !== 'undefined' && typeof snap.pay === 'function') {
                                        snap.pay(token);

                                        // Listen event 'message' dari iframe Konekpay
                                        window.addEventListener('message', function(e) {
                                            if (e.data === 'close_snap') {
                                                // Saat popup ditutup, redirect user ke halaman Thank You WooCommerce
                                                window.location.href = thankyouUrl;
                                            }
                                        });
                                    } else {
                                        // Fallback: Jika snap.js gagal diload (misal diblokir adblocker),
                                        // langsung arahkan browser ke hosted checkout Konekpay agar transaksi tidak hilang.
                                        window.location.href = 'https://konekpay.biz.id/checkout/' + token;
                                    }
                                }
                            }
                        }

                        // Daftarkan listener perubahan hash URL
                        $(window).on('hashchange', checkKonekpayHash);

                        // Cek saat halaman pertama kali diload (jika ada hash tertinggal)
                        checkKonekpayHash();
                    });
                ";
                wp_add_inline_script( 'konekpay-checkout-handler', $inline_js );
            }
        }

        /**
         * Memastikan metode pembayaran ini hanya tampil ketika mata uang Rupiah (IDR) diaktifkan
         */
        public function is_available() {
            $is_enabled = $this->enabled === 'yes';
            $log = sprintf(
                "[%s] is_available called. Enabled option: %s. Result: %s\n",
                date('Y-m-d H:i:s'),
                $this->enabled,
                $is_enabled ? 'true' : 'false'
            );
            file_put_contents( dirname(__FILE__) . '/debug_log.txt', $log, FILE_APPEND );
            
            return $is_enabled;
        }

        /**
         * Konfigurasi Form Settings di Admin Panel WooCommerce
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => 'Enable/Disable',
                    'type'    => 'checkbox',
                    'label'   => 'Aktifkan Pembayaran Konekpay',
                    'default' => 'no'
                ),
                'title' => array(
                    'title'       => 'Judul Metode Pembayaran',
                    'type'        => 'text',
                    'description' => 'Nama metode pembayaran yang akan tampil saat checkout.',
                    'default'     => 'Konekpay (VA, QRIS, Alfamart)',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Deskripsi Pembayaran',
                    'type'        => 'textarea',
                    'description' => 'Deskripsi pembayaran yang dibaca pelanggan saat memilih metode pembayaran.',
                    'default'     => 'Bayar aman menggunakan bank transfer Virtual Account (VA), QRIS, atau Alfamart via Konekpay.',
                ),
                'environment' => array(
                    'title'       => 'Environment',
                    'type'        => 'select',
                    'default'     => 'sandbox',
                    'options'     => array(
                        'sandbox'    => 'Sandbox (Testing)',
                        'production' => 'Production (Live)',
                    ),
                ),
                'sandbox_api_key' => array(
                    'title'       => 'Sandbox API Key',
                    'type'        => 'text',
                    'description' => 'API Key khusus mode sandbox. Ambil dari dashboard member Konekpay.',
                ),
                'production_api_key' => array(
                    'title'       => 'Production API Key',
                    'type'        => 'text',
                    'description' => 'API Key khusus mode production. Ambil dari dashboard member Konekpay.',
                ),
            );
        }

        /**
         * Proses Pembayaran & Redirect ke Hosted Checkout Konekpay
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            
            // Siapkan payload transaksi sesuai format API Konekpay
            $payload = array(
                'invoice_number' => (string) $order->get_id(), // ID Order WooCommerce sebagai nomor invoice
                'amount'         => (int) $order->get_total(),
                'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email()
            );

            // API Endpoint Hosted Checkout Konekpay
            $api_url = 'https://konekpay.biz.id/api/v1/checkout';

            // Kirim request ke API Konekpay
            $response = wp_remote_post( $api_url, array(
                'method'    => 'POST',
                'body'      => json_encode( $payload ),
                'headers'   => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept'        => 'application/json'
                ),
                'timeout'   => 45
            ));

            // Jika gagal koneksi ke API
            if ( is_wp_error( $response ) ) {
                wc_add_notice( 'Konekpay Error: Gagal terhubung dengan server pembayaran.', 'error' );
                return;
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            // Cek respons sukses dari Konekpay
            if ( isset( $body['status'] ) && $body['status'] === 'success' && isset( $body['data']['checkout_url'] ) ) {
                // Kurangi stok barang
                wc_reduce_stock_levels( $order_id );
                
                // JANGAN kosongkan keranjang di sini, biarkan WooCommerce mengosongkannya secara otomatis
                // ketika pembeli berhasil dialihkan ke halaman Thank You.
                
                // Kembalikan alur hash agar dideteksi oleh Javascript pembuka popup modal
                return array(
                    'result'   => 'success',
                    'redirect' => '#konekpay-token-' . $body['data']['token'] . '&thankyou=' . urlencode( $order->get_checkout_order_received_url() )
                );
            } else {
                $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Gagal memproses pembuatan sesi pembayaran.';
                wc_add_notice( 'Konekpay Error: ' . $error_msg, 'error' );
                return;
            }
        }

        /**
         * Menangani Webhook Callback dari Konekpay
         */
        public function check_callback() {
            // Ambil raw JSON dari body request
            $payload = file_get_contents( 'php://input' );
            $data    = json_decode( $payload, true );

            if ( empty( $data ) ) {
                wp_send_json( array( 'status' => 'error', 'message' => 'Empty payload' ), 400 );
            }

            // Ambil header tanda tangan digital
            $header_signature = isset( $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] ) ? $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] : '';

            if ( empty( $header_signature ) ) {
                wp_send_json( array( 'status' => 'error', 'message' => 'Missing signature header' ), 403 );
            }

            // Hitung signature lokal berdasarkan payload dan API Key
            $calculated_signature = hash_hmac( 'sha256', $payload, $this->api_key );

            // Validasi signature
            if ( hash_equals( $calculated_signature, $header_signature ) ) {
                $order_id = $data['invoice_number'];
                $status   = $data['status'];
                
                // Cari order WooCommerce berdasarkan ID
                $order = wc_get_order( $order_id );

                if ( ! $order ) {
                    wp_send_json( array( 'status' => 'error', 'message' => 'Order not found' ), 404 );
                }

                // Cek jika status dari webhook adalah PAID
                if ( $status === 'PAID' ) {
                    $order->payment_complete();
                    $order->add_order_note( sprintf( 'Konekpay Webhook: Pembayaran sukses diterima. Metode: %s', isset($data['payment_method']) ? $data['payment_method'] : 'Konekpay' ) );
                    
                    wp_send_json( array( 'status' => 'success', 'message' => 'Webhook callback processed successfully.' ), 200 );
                } else {
                    wp_send_json( array( 'status' => 'success', 'message' => 'Status is not PAID' ), 200 );
                }
            } else {
                wp_send_json( array( 'status' => 'error', 'message' => 'Signature verification failed' ), 403 );
            }
        }

        /**
         * Tampilkan URL Webhook di halaman pengaturan admin panel WooCommerce
         */
        public function admin_options() {
            parent::admin_options();
            
            // Webhook callback URL otomatis WooCommerce
            $callback_url = add_query_arg( 'wc-api', 'WC_Gateway_Konekpay', home_url( '/' ) );
            
            echo '<div style="margin-top: 20px; padding: 20px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; color: #166534;">';
            echo '<h4 style="margin-top: 0; color: #15803d; font-weight: bold;">Webhook / Callback Konekpay</h4>';
            echo '<p style="margin-bottom: 10px;">Salin URL di bawah ini lalu daftarkan pada pengaturan aplikasi di dashboard Konekpay Anda untuk mengotomatisasi pembaruan status order:</p>';
            echo '<code style="background-color: #ffffff; padding: 6px 12px; border: 1px solid #dcfce7; border-radius: 4px; font-size: 14px; font-family: monospace; display: block; overflow-x: auto;">' . esc_url( $callback_url ) . '</code>';
            echo '</div>';
        }
    }
}

/**
 * Daftarkan gateway class Konekpay ke daftar payment gateway WooCommerce
 */
add_filter( 'woocommerce_payment_gateways', 'add_konekpay_gateway_class' );
function add_konekpay_gateway_class( $gateways ) {
    $log = sprintf(
        "[%s] add_konekpay_gateway_class filter called. Current gateways: %s\n",
        date('Y-m-d H:i:s'),
        is_array($gateways) ? implode(', ', $gateways) : 'Not an array'
    );
    file_put_contents( dirname(__FILE__) . '/debug_log.txt', $log, FILE_APPEND );

    $gateways[] = 'WC_Gateway_Konekpay';
    return $gateways;
}
