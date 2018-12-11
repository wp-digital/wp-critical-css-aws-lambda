<?php
/**
 * Plugin Name: AWS Lambda Critical CSS
 * Description: Generates critical stylesheets for templates via AWS Lambda.
 * Version: 1.0.4
 * Author: Innocode
 * Author URI: https://innocode.com
 * Requires at least: 4.8
 * Tested up to: 5.0.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

define( 'AWS_LAMBDA_CRITICAL_CSS_VERSION', '1.0.4' );

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/class-critical-css-aws-lambda.php';

function aws_lambda_critical_css() {
    $GLOBALS['aws_lambda_critical_css'] = new WP_Critical_CSS_AWS_Lambda();
}

add_action( 'init', 'aws_lambda_critical_css', 99 );

function aws_lambda_critical_css_init() {
    /**
     * @var WP_Critical_CSS_AWS_Lambda $aws_lambda_critical_css
     */
    global $aws_lambda_critical_css;

    if ( isset( $aws_lambda_critical_css ) ) {
        $aws_lambda_critical_css->init();
    }
}

add_action( 'wp_enqueue_scripts', 'aws_lambda_critical_css_init', 99 );

function aws_lambda_critical_css_rest_api_init() {
    /**
     * @var WP_Critical_CSS_AWS_Lambda $aws_lambda_critical_css
     */
    global $aws_lambda_critical_css;

    if ( isset( $aws_lambda_critical_css ) ) {
        $aws_lambda_critical_css->register_rest_route();
    }
}

add_action( 'rest_api_init', 'aws_lambda_critical_css_rest_api_init' );

function aws_lambda_critical_css_run() {
    /**
     * @var WP_Critical_CSS_AWS_Lambda $aws_lambda_critical_css
     */
    global $aws_lambda_critical_css;

    if ( isset( $aws_lambda_critical_css ) ) {
        $aws_lambda_critical_css->run();
        $aws_lambda_critical_css->print_stylesheet();
    }
}

add_action( 'wp_head', 'aws_lambda_critical_css_run', 2 );