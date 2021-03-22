<?php

namespace Innocode\CriticalCSSAWSLambda;

use WP_REST_Controller;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class RESTController
 * @package Innocode\CriticalCSSAWSLambda
 */
class RESTController extends WP_REST_Controller
{
    /**
     * REST constructor.
     */
    public function __construct()
    {
        $this->namespace = 'innocode/v1';
        $this->rest_base = 'aws-lambda-critical-css';
    }

    /**
     * Adds routes.
     */
    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/stylesheet",
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => [ $this, 'create_item_permissions_check' ],
                'callback'            => [ $this, 'create_item' ],
                'args'                => [
                    'key'        => [
                        'required'          => true,
                        'validate_callback' => function ( $key ) {
                            return false !== get_transient( "aws_lambda_critical_css_$key" );
                        },
                    ],
                    'hash'       => [
                        'required'          => true,
                        'validate_callback' => function ( $hash ) {
                            return Helpers::is_md5( $hash );
                        },
                    ],
                    'stylesheet' => [
                        'required'          => true,
                        'sanitize_callback' => function ( $stylesheet ) {
                            return strip_tags( $stylesheet );
                        },
                    ],
                    'secret'     => [
                        'required' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Returns endpoint.
     *
     * @param string $path
     * @return string
     */
    public function url( string $path ) : string
    {
        return rest_url( "/$this->namespace/$this->rest_base/" . ltrim( $path, '/' ) );
    }

    /**
     * Checks if a given request has access to create items.
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function create_item_permissions_check( $request )
    {
        $secret_hash_key = 'aws_lambda_critical_css_secret_' . $request->get_param( 'key' );

        if (
            false === ( $secret_hash = get_transient( $secret_hash_key ) ) ||
            ! wp_check_password( $request->get_param( 'secret' ), $secret_hash )
        ) {
            return new WP_Error(
                'rest_innocode_aws_lambda_critical_css_cannot_create_stylesheet',
                __( 'Sorry, you are not allowed to create critical stylesheet.', 'innocode-aws-lambda-critical-css' ),
                [
                    'status' => WP_Http::UNAUTHORIZED,
                ]
            );
        }

        return true;
    }

    /**
     * Creates critical path stylesheet.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_item( $request )
    {
        $key = $request->get_param( 'key' );
        $hash = $request->get_param( 'hash' );

        if ( $hash !== get_transient( "aws_lambda_critical_css_$key" ) ) {
            return new WP_Error(
                'rest_innocode_aws_lambda_critical_css_invalid_param',
                __( 'Invalid key or hash.', 'innocode-aws-lambda-critical-css' ),
                [
                    'status' => WP_Http::BAD_REQUEST,
                ]
            );
        }

        update_option( "aws_lambda_critical_css_$key", $hash );
        delete_transient( "aws_lambda_critical_css_$key" );
        delete_transient( "aws_lambda_critical_css_secret_$key" );

        $updated = update_option(
            "aws_lambda_critical_css_{$key}_stylesheet",
            $request->get_param( 'stylesheet' ),
            false
        );

        return rest_ensure_response( $updated );
    }
}
