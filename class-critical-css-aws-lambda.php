<?php

use Aws\Lambda\LambdaClient;

/**
 * Class WP_Critical_CSS_AWS_Lambda
 */
class WP_Critical_CSS_AWS_Lambda
{
    const KEY = 'aws_lambda_critical_css';
    const API_VERSION = '1';
    const SECRET_LENGTH = 20;
    /**
     * @var LambdaClient
     */
    protected $_lambda_client = null;
    /**
     * @var string
     */
    protected $_lambda_function = '';
    /**
     * @var string
     */
    protected $_site_key = '';
    /**
     * @var string
     */
    protected $_template_name = '';
    /**
     * @var string
     */
    protected $_key = '';
    /**
     * @var array
     */
    protected $_styles = [];
    /**
     * @var array
     */
    protected $_registered_styles = [];
    /**
     * @var string
     */
    protected $_url = '';
    /**
     * @var string
     */
    protected $_hash = '';

    /**
     * WP_Critical_CSS_AWS_Lambda constructor.
     */
    public function __construct()
    {
        $this->_styles = static::get_styles();

        if ( !empty( $this->_styles ) ) {
            $this->_site_key = static::get_site_key();
            $secret_key = static::key( 'secret' );

            add_action( $secret_key, function () {
                if ( static::has_required_constants() ) {
                    $this->_init_lambda();
                    $this->_generate_credentials();
                }
            } );

            if ( false === get_transient( $secret_key ) ) {
                wp_schedule_single_event( time(), $secret_key );
            }

            add_action( static::KEY, function ( $key, $hash, $args ) {
                if ( static::has_required_constants() ) {
                    $this->_init_lambda();
                    $this->_run( $key, $hash, $args );
                }
            }, 10, 3 );
        }
    }

    /**
     * Initialize
     */
    public function init()
    {
        if ( !empty( $this->_styles ) && static::has_required_constants() ) {
            $this->_registered_styles = $this->_get_registered_styles();

            if ( !empty( $this->_registered_styles ) ) {
                $this->_template_name = static::get_template_name();
                $this->_key = static::key( $this->_template_name );
                $this->_hash = $this->_get_hash();
                $this->_url = static::get_current_url();
            }
        }
    }

    /**
     * Schedule cron task with AWS Lambda function
     *
     * @return bool|string|\WP_Error
     */
    public function run()
    {
        if (
            !empty( $this->_key ) && !empty( $this->_hash )
            && ( false === ( $hash = get_transient( $this->_key ) ) || $hash != $this->_hash )
            && get_option( $this->_key ) !== $this->_hash
        ) {
            wp_schedule_single_event( time() + 10, static::KEY, [
                $this->_key,
                $this->_hash,
                $this->_get_lambda_args(),
            ] );

            return $this->_hash;
        }

        return false;
    }

    /**
     * Print critical path stylesheet
     */
    public function print_stylesheet()
    {
        $stylesheet = get_option( "{$this->_key}_stylesheet" );

        // Usually stylesheet files are cached on production, so there is no reason to use critical css every time.
        // Mechanism with checking of referer is ugly and used as a default, more pretty solution will be to use different hashes in case when e.g. Varnish is used on server.
        // Anyway developer may control that with filter - 'aws_lambda_critical_css_can_print'.
        if ( !empty( $stylesheet ) && apply_filters( static::key( 'can_print' ), !static::has_internal_referer() ) ) : ?>
            <style>
                <?= apply_filters( static::key( 'stylesheet' ), strip_tags( $stylesheet ) ) ?>
            </style>
            <?php do_action( static::key( 'printed' ), $this->_key, $this->_hash );
        endif;
    }

    /**
     * Register REST API route for receiving critical path stylesheet from AWS Lambda function
     */
    public function register_rest_route()
    {
        if ( !empty( $this->_styles ) && static::has_required_constants() ) {
            register_rest_route( static::get_api_namespace(), '/stylesheet', [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => function ( WP_REST_Request $request ) {
                        return $this->_create_stylesheet( $request );
                    },
                    'permission_callback' => function ( WP_REST_Request $request ) {
                        return $this->_create_stylesheet_permissions_check( $request );
                    },
                    'args'                => [
                        'key'        => [
                            'required'          => true,
                            'sanitize_callback' => function ( $key ) {
                                return sanitize_key( $key );
                            },
                            'validate_callback' => function ( $key ) {
                                return false !== get_transient( $key );
                            },
                        ],
                        'hash'       => [
                            'required'          => true,
                            'validate_callback' => function ( $hash ) {
                                return is_string( $hash ) && static::is_md5( $hash );
                            },
                        ],
                        'stylesheet' => [
                            'required'          => true,
                            'sanitize_callback' => function ( $stylesheet ) {
                                return strip_tags( $stylesheet );
                            },
                        ],
                        'secret'     => [
                            'required'          => true,
                            'validate_callback' => function ( $secret ) {
                                return is_string( $secret );
                            },
                        ],
                    ],
                ],
            ] );
        }
    }

    /**
     * Check permissions for REST API endpoint
     *
     * @param WP_REST_Request $request
     *
     * @return bool|WP_Error
     */
    protected function _create_stylesheet_permissions_check( WP_REST_Request $request )
    {
        if ( false === ( $secret_hash = get_transient( static::key( 'secret' ) ) ) || !wp_check_password( $request->get_param( 'secret' ), $secret_hash ) ) {
            return new WP_Error( 'rest_cannot_create_stylesheet', __( 'Sorry, you are not allowed to create critical stylesheet.' ), [
                'status' => rest_authorization_required_code(),
            ] );
        }

        return true;
    }

    /**
     * Create critical path stylesheet
     *
     * @param WP_REST_Request $request
     *
     * @return bool|WP_Error
     */
    protected function _create_stylesheet( WP_REST_Request $request )
    {
        $key = $request->get_param( 'key' );
        $hash = $request->get_param( 'hash' );

        if ( $hash !== get_transient( $key ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'Invalid key or hash.' ), [
                'status' => WP_Http::BAD_REQUEST,
            ] );
        }

        update_option( $key, $hash );
        delete_transient( $key );

        return update_option( "{$key}_stylesheet", $request->get_param( 'stylesheet' ), false );
    }

    /**
     * Initialize AWS Lambda client
     */
    protected function _init_lambda()
    {
        $this->_lambda_client = static::get_lambda_client();
        $this->_lambda_function = static::get_lambda_function();
    }

    /**
     * Return critical path hash
     *
     * @return string
     */
    protected function _get_hash()
    {
        return md5( array_reduce( $this->_registered_styles, function ( $version, array $style ) {
            return "$version|{$style['handle']}:{$style['ver']}";
        }, '' ) );
    }

    /**
     * Return URL's of stylesheets which are used for critical path
     *
     * @return array
     */
    protected function _get_registered_styles()
    {
        global $wp_styles, $wp_version;

        $styles = [];

        if ( isset( $wp_styles ) ) {
            $registered_styles = $wp_styles->registered;

            foreach ( $this->_styles as $handle ) {
                if ( isset( $registered_styles[ $handle ] ) ) {
                    $style = $registered_styles[ $handle ];
                    $styles[] = [
                        'handle' => $style->handle,
                        'src'    => $style->src,
                        'ver'    => $style->ver ? $style->ver : apply_filters( static::key( 'default_style_version' ), $wp_version ),
                    ];
                }
            }
        }

        return $styles;
    }

    /**
     * Return AWS Lambda function arguments
     *
     * @return array
     */
    protected function _get_lambda_args()
    {
        return [
            'key'        => $this->_key,
            'styles'     => array_column( $this->_registered_styles, 'src' ),
            'url'        => $this->_url,
            'hash'       => $this->_hash,
            'return_url' => static::get_rest_url( '/stylesheet' ),
            'site_key'   => $this->_site_key,
        ];
    }

    /**
     * Generate credentials for AWS Lambda function, send to environments variables and save hash to WordPress options
     *
     * @return bool
     */
    protected function _generate_credentials()
    {
        $result = $this->_lambda_client->getFunctionConfiguration( [
            'FunctionName' => $this->_lambda_function,
        ] );
        $environment = $result->get( 'Environment' );
        $vars = !is_null( $environment ) && isset( $environment['Variables'] ) && is_array( $environment['Variables'] ) ? $environment['Variables'] : [];
        $secret = wp_generate_password( static::SECRET_LENGTH );
        $vars[ $this->_site_key ] = $secret;
        $this->_lambda_client->updateFunctionConfiguration( [
            'FunctionName' => $this->_lambda_function,
            'Environment'  => [
                'Variables' => $vars,
            ],
        ] );

        return set_transient( static::key( 'secret' ), wp_hash_password( $secret ), WEEK_IN_SECONDS );
    }

    /**
     * Invoke AWS Lambda function
     *
     * @param string $key
     * @param string $hash
     * @param array  $args
     *
     * @return bool|WP_Error
     */
    protected function _run( $key, $hash, $args )
    {
        $result = $this->_lambda_client->invoke( [
            'FunctionName'   => $this->_lambda_function,
            'Payload'        => json_encode( $args ),
            'InvocationType' => 'Event',
        ] );

        if ( $result['StatusCode'] < WP_Http::OK || $result['StatusCode'] >= WP_Http::MULTIPLE_CHOICES ) {
            return new WP_Error( static::KEY, $result['FunctionError'] );
        }

        return set_transient( $key, $hash, DAY_IN_SECONDS / 2 );
    }

    /**
     * Check if all required constants are defined
     *
     * @return bool
     */
    public static function has_required_constants()
    {
        return defined( 'AWS_LAMBDA_CRITICAL_CSS_KEY' ) && defined( 'AWS_LAMBDA_CRITICAL_CSS_SECRET' ) && defined( 'AWS_LAMBDA_CRITICAL_CSS_REGION' );
    }

    /**
     * Return AWS Lambda client
     *
     * @return \Aws\Lambda\LambdaClient
     */
    public static function get_lambda_client()
    {
        return new LambdaClient( [
            'credentials' => [
                'key'    => AWS_LAMBDA_CRITICAL_CSS_KEY,
                'secret' => AWS_LAMBDA_CRITICAL_CSS_SECRET,
            ],
            'region'      => AWS_LAMBDA_CRITICAL_CSS_REGION,
            'version'     => '2015-03-31',
        ] );
    }

    /**
     * Return AWS Lambda function name
     *
     * @return string
     */
    public static function get_lambda_function()
    {
        return defined( 'AWS_LAMBDA_CRITICAL_CSS_FUNCTION' ) ? AWS_LAMBDA_CRITICAL_CSS_FUNCTION : 'wordpress_critical_css-production';
    }

    /**
     * Return site key which is prepared for using as environment variable of AWS Lambda function
     *
     * @return string
     */
    public static function get_site_key()
    {
        return static::sanitize_environment_key( get_site_url() );
    }

    /**
     * Return current template name
     *
     * @return string
     */
    public static function get_template_name()
    {
        global $template;

        return str_replace( get_template_directory(), '', $template );
    }

    /**
     * Return list of stylesheets for critical path
     *
     * @return array
     */
    public static function get_styles()
    {
        return apply_filters( static::key( 'styles' ), [] );
    }

    /**
     * Return current URL
     *
     * @return string
     */
    public static function get_current_url()
    {
        return home_url( $_SERVER['REQUEST_URI'] );
    }

    /**
     * Return prefixed and sanitized key
     *
     * @param string $key
     *
     * @return string
     */
    public static function key( $key )
    {
        return static::KEY . '_' . sanitize_key( $key );
    }

    /**
     * Return REST API namespace
     *
     * @return string
     */
    public static function get_api_namespace()
    {
        return static::KEY . '/v' . static::API_VERSION;
    }

    /**
     * Return REST API URL
     *
     * @param string $endpoint
     *
     * @return string
     */
    public static function get_rest_url( $endpoint )
    {
        return esc_url_raw( rest_url( static::get_api_namespace() . $endpoint ) );
    }

    /**
     * Check if string is md5 hash
     *
     * @param string $hash
     *
     * @return int
     */
    public static function is_md5( $hash )
    {
        return preg_match( '/^[a-f0-9]{32}$/', $hash );
    }

    /**
     * Sanitize key for using as environment variable of AWS Lambda function
     *
     * @param string $key
     *
     * @return string
     */
    public static function sanitize_environment_key( $key )
    {
        return preg_replace( '/[^a-zA-Z0-9_]/', '', $key );
    }

    /**
     * Check if referer is internal
     *
     * @return bool
     */
    public static function has_internal_referer()
    {
        $referer = wp_get_raw_referer();
        $home_url = home_url();

        return false !== $referer && substr( $referer, 0, strlen( $home_url ) ) === $home_url;
    }
}