<?php
/*
Plugin Name: AWS Lambda Critical CSS
Description:
Version: 0.1
Plugin
Author: Vitalii Pylypenko
Author URI: https://github.com/redink-no
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/


require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/class-critical-css-aws-lambda.php';
function run_critical_css() {
    $critical_obj = new WP_Critical_CSS_AWS_Lambda();
    $critical_obj->load();
}

add_action( 'wp_head',  'run_critical_css');




