<?php
/**
 * Plugin Name: AWS Lambda Critical CSS
 * Description: Generates critical stylesheets for templates via AWS Lambda.
 * Version: 2.2.0
 * Author: Innocode
 * Author URI: https://innocode.com
 * Tested up to: 5.6.2
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Innocode\CriticalCSSAWSLambda;

if ( ! function_exists( 'innocode_aws_lambda_critical_css_init' ) ) {
    function innocode_aws_lambda_critical_css_init() {
        if (
            ! defined( 'AWS_LAMBDA_CRITICAL_CSS_KEY' ) ||
            ! defined( 'AWS_LAMBDA_CRITICAL_CSS_SECRET' ) ||
            ! defined( 'AWS_LAMBDA_CRITICAL_CSS_REGION' )
        ) {
            return;
        }

        $styles = apply_filters( 'aws_lambda_critical_css_styles', [] );

        if ( empty( $styles ) ) {
            return;
        }

        $GLOBALS['aws_lambda_critical_css'] = new CriticalCSSAWSLambda\Plugin(
            $styles,
            AWS_LAMBDA_CRITICAL_CSS_KEY,
            AWS_LAMBDA_CRITICAL_CSS_SECRET,
            AWS_LAMBDA_CRITICAL_CSS_REGION,
            defined( 'AWS_LAMBDA_CRITICAL_CSS_FUNCTION' )
                ? AWS_LAMBDA_CRITICAL_CSS_FUNCTION
                : null
        );
        $GLOBALS['aws_lambda_critical_css']->run();
    }
}

add_action( 'init', 'innocode_aws_lambda_critical_css_init', 20 );

if ( ! function_exists( 'innocode_aws_lambda_critical_css' ) ) {
    function innocode_aws_lambda_critical_css() : CriticalCSSAWSLambda\Plugin {
        global $aws_lambda_critical_css;

        if ( is_null( $aws_lambda_critical_css ) ) {
            trigger_error(
                'Missing required constants or no styles included.',
                E_USER_ERROR
            );
        }

        return $aws_lambda_critical_css;
    }
}
