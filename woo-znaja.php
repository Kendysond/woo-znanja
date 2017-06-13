<?php // error_reporting(-1); ini_set('display_errors', 1);//error_reporting(0); ini_set('display_errors', 0);
/**
 * Plugin Name: WooCommerce Znaja Integration
 * Plugin URI: https://waymakerlearning.com/
 * Description: Links Woocommerce Products to Znaja 
 * Version: 1.0.0
 * Author: Douglas Kendyson
 * Author URI: https://github.com/kendysond
 * Developer: Douglas Kendyson
 * Developer URI: https://github.com/kendysond
 * Text Domain: woocommerce-extension
 * Domain Path: /languages
 *
 * Copyright: © 2009-2015 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */



require_once dirname( __FILE__ ) . '/vendor/autoload.php';
require_once dirname( __FILE__ ) . '/functions.php';

define( 'WC_KKD_ZNAJA_MAIN_FILE', __FILE__ );

define( 'WC_KKD_ZNAJA_VERSION', '1.0.0' );
