<?php
/**
 * Plugin Name: AWS Lambda Critical CSS
 * Description: Generates critical stylesheets for templates via AWS Lambda
 * Version: 0.1.0
 * Author: Innocode
 * Author URI: https://innocode.com
 * Requires at least: 4.8
 * Tested up to: 4.8.1
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

define( 'AWS_LAMBDA_CRITICAL_CSS_VERSION', '0.1.0' );

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/class-critical-css-aws-lambda.php';

function aws_lambda_critical_css_run() {
    $aws_lambda_critical_css = new WP_Critical_CSS_AWS_Lambda();

    if ( false !== ( $version = $aws_lambda_critical_css->run() ) && !is_wp_error( $version ) ) {
        $aws_lambda_critical_css->schedule_receiving();
    }
}

add_action( 'wp_head', 'aws_lambda_critical_css_run', 1 );

add_action( WP_Critical_CSS_AWS_Lambda::KEY, function ( $key, $version ) {
    if ( false !== ( $_version = get_transient( $key ) ) && $_version == $version ) {

    }
}, 10, 2 );