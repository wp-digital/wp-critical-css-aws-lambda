<?php

namespace Innocode\CriticalCSS;

use WP_REST_Controller;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class RESTController
 * @package Innocode\CriticalCSS
 */
class RESTController extends WP_REST_Controller
{
    /**
     * REST constructor.
     */
    public function __construct()
    {
        $this->namespace = 'innocode/v1';
        $this->rest_base = 'critical-css';
    }

    /**
     * Adds routes.
     */
    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => [ $this, 'save_item_permissions_check' ],
                'callback'            => [ $this, 'save_item' ],
                'args'                => [
                    'key'        => [
                        'description'       => __( 'Object identifier for the stylesheet.', 'innocode-critical-css' ),
                        'type'              => 'string',
                        'required'          => true,
                        'validate_callback' => [ $this, 'validate_key' ],
                    ],
                    'hash'       => [
                        'description' => __( 'Version of the stylesheet.', 'innocode-critical-css' ),
                        'type'        => 'string',
                        'required'    => true,
                    ],
                    'stylesheet' => [
                        'description'       => __( 'Critical path stylesheet.', 'innocode-critical-css' ),
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => [ $this, 'sanitize_stylesheet' ],
                    ],
                    'secret'     => [
                        'description' => __( 'Secret for the callback.', 'innocode-critical-css' ),
                        'type'        => 'string',
                        'required'    => true,
                    ],
                    'url'        => [
                        'description'       => __( 'URL of the requested page.', 'innocode-critical-css' ),
                        'type'              => 'string',
                        'validate_callback' => 'wp_http_validate_url',
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                ],
            ]
        );
    }

    /**
     * Checks if a given request has access to create items.
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function save_item_permissions_check( WP_REST_Request $request )
    {
        $key = $request->get_param( 'key' );
        $secret = $request->get_param( 'secret' );

        $secret_hash = innocode_critical_css()->get_secrets_manager()->get( (string) $key );

        if ( false === $secret_hash || ! wp_check_password( (string) $secret, $secret_hash ) ) {
            return new WP_Error(
                'rest_innocode_critical_css_cannot_save_stylesheet',
                __( 'Sorry, you are not allowed to save critical stylesheet.', 'innocode-critical-css' ),
                [ 'status' => WP_Http::UNAUTHORIZED ]
            );
        }

        /**
         * 'permission_callback' is also used after 'callback' in 'rest_send_allow_header' function through
         * 'rest_post_dispatch' hook with priority 10, so, secret should be in place after 'callback' but still
         * better to remove it after response returning as it cannot be used anymore after successful request.
         */
        add_filter( 'rest_pre_echo_response', [ $this, 'delete_secret_hash' ], PHP_INT_MAX, 3 );

        return true;
    }

    /**
     * Saves critical path stylesheet.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function save_item( WP_REST_Request $request )
    {
        $stylesheet = $request->get_param( 'stylesheet' );
        $version = $request->get_param( 'hash' );

        list( $template, $object, $key ) = explode( ':', $request->get_param( 'key' ), 3 );

        $db = innocode_critical_css()->get_db();

        if ( ! $db->save_entry( $stylesheet, $version, $template, $object, $key ) ) {
            return new WP_Error(
                'rest_innocode_critical_css_stylesheet_not_saved',
                __( 'Critical stylesheet not saved.', 'innocode-critical-css' ),
                [ 'status' => WP_Http::INTERNAL_SERVER_ERROR ]
            );
        }

        $entry = $db->get_entry( $template, $object, $key );

        do_action( 'innocode_critical_css_callback', $entry, $request->get_param( 'url' ) );

        return $this->prepare_item_for_response( $entry, $request );
    }

    /**
     * Returns endpoint.
     *
     * @return string
     */
    public function url() : string
    {
        return rest_url( "/$this->namespace/$this->rest_base/" );
    }

    /**
     * Removes secret before response returning.
     *
     * @param array           $result
     * @param WP_REST_Server  $server
     * @param WP_REST_Request $request
     *
     * @return array
     */
    public function delete_secret_hash( array $result, WP_REST_Server $server, WP_REST_Request $request ) : array
    {
        innocode_critical_css()
            ->get_secrets_manager()
            ->delete( $request->get_param( 'key' ) );

        return $result;
    }

    /**
     * @param string $param
     * @return bool
     */
    public function validate_key( string $param ) : bool
    {
        if ( false === strpos( $param, ':' ) ) {
            return false;
        }

        $parts = explode( ':', $param, 3 );

        if ( count( $parts ) < 3 ) {
            return false;
        }

        list( $template ) = $parts;

        if ( ! in_array( $template, Plugin::TEMPLATES, true ) ) {
            return false;
        }

        return true;
    }

    /**
     * @param string $param
     * @return string
     */
    public function sanitize_stylesheet( string $param ) : string
    {
        return trim( strip_tags( $param ) );
    }

    /**
     * @param Entry           $item
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function prepare_item_for_response( $item, $request ) : WP_REST_Response
    {
        return rest_ensure_response( [
            'id'         => $item->get_id(),
            'created'    => $item->get_created()->format( 'Y-m-d\TH:i:s' ),
            'updated'    => $item->get_updated()->format( 'Y-m-d\TH:i:s' ),
            'stylesheet' => $item->get_stylesheet(),
            'version'    => $item->get_version(),
        ] );
    }
}
