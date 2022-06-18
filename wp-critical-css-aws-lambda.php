<?php
/**
 * Plugin Name: AWS Lambda Critical CSS
 * Description: Generates critical stylesheets for templates via AWS Lambda.
 * Version: 3.0.0
 * Author: Innocode
 * Author URI: https://innocode.com
 * Tested up to: 6.0.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Innocode\CriticalCSS;

define( 'INNOCODE_CRITICAL_CSS', __FILE__ );

if (
    ! defined( 'AWS_LAMBDA_CRITICAL_CSS_KEY' ) ||
    ! defined( 'AWS_LAMBDA_CRITICAL_CSS_SECRET' ) ||
    ! defined( 'AWS_LAMBDA_CRITICAL_CSS_REGION' )
) {
    return;
}

$GLOBALS['critical_css'] = new CriticalCSS\Plugin(
    AWS_LAMBDA_CRITICAL_CSS_KEY,
    AWS_LAMBDA_CRITICAL_CSS_SECRET,
    AWS_LAMBDA_CRITICAL_CSS_REGION
);

if ( ! defined( 'AWS_LAMBDA_CRITICAL_CSS_FUNCTION' ) ) {
    define( 'AWS_LAMBDA_CRITICAL_CSS_FUNCTION', 'critical-css-production-processor' );
}

$GLOBALS['critical_css']
    ->get_lambda()
    ->set_function( AWS_LAMBDA_CRITICAL_CSS_FUNCTION );

if ( ! defined( 'AWS_LAMBDA_CRITICAL_CSS_DB_TABLE' ) ) {
    define( 'AWS_LAMBDA_CRITICAL_CSS_DB_TABLE', 'innocode_critical_css' );
}

$GLOBALS['critical_css']
    ->get_db()
    ->set_table( AWS_LAMBDA_CRITICAL_CSS_DB_TABLE );

$GLOBALS['critical_css']->run();

if ( ! function_exists( 'innocode_critical_css' ) ) {
    function innocode_critical_css() : CriticalCSS\Plugin {
        global $critical_css;

        if ( is_null( $critical_css ) ) {
            trigger_error(
                'Missing required constants or no styles included',
                E_USER_ERROR
            );
        }

        return $critical_css;
    }
}
