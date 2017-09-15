<?php

use Aws\Lambda\LambdaClient;

/**
 * Class WP_Critical_CSS_AWS_Lambda
 */
class WP_Critical_CSS_AWS_Lambda
{
    const KEY = 'aws_lambda_critical_css';
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
     * @var string
     */
    protected $_url = '';
    /**
     * @var string
     */
    protected $_version = '';

    public function __construct()
    {
        if ( static::has_required_constants() ) {
            $this->_template_name = static::get_template_name();
            $this->_key = static::KEY . '_' . sanitize_key( $this->_template_name );
            $this->_styles = static::get_styles();
            $this->_version = $this->_get_version();

            if ( false === ( $version = get_transient( $this->_key ) ) || $version != $this->_version ) {
                $this->_lambda_client = static::get_lambda_client();
                $this->_lambda_function = static::get_lambda_function();
                $this->_url = static::get_current_url();
            }
        }
    }

    /**
     * Invoke AWS Lambda function
     *
     * @return bool|string|\WP_Error
     */
    public function run()
    {
        if ( !is_null( $this->_lambda_client ) ) {
            $result = $this->_lambda_client->invoke( [
                'FunctionName' => $this->_lambda_function,
                'Payload'      => json_encode( $this->_get_lambda_args() ),
            ] );

            if ( $result['StatusCode'] < WP_Http::OK && $result['StatusCode'] >= WP_Http::MULTIPLE_CHOICES ) {
                return new WP_Error( static::KEY, $result['FunctionError'] );
            }

            set_transient( $this->_key, $this->_version );

            return $this->_version;
        }

        return false;
    }

    /**
     * Cron task
     */
    public function schedule_receiving()
    {
        wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, static::KEY, [
            $this->_key,
            $this->_version,

        ] );
    }

    /**
     * Return critical path version
     *
     * @return string
     */
    protected function _get_version()
    {
        return array_reduce( $this->_styles, function ( $version, array $style ) {
            return "$version|{$style['handle']}:{$style['ver']}";
        }, '' );
    }

    /**
     * Return AWS Lambda arguments
     *
     * @return array
     */
    protected function _get_lambda_args()
    {
        return [
            'bucket'        => AWS_LAMBDA_CRITICAL_CSS_BUCKET,
            'template_name' => $this->_template_name,
            'styles'        => array_column( $this->_styles, 'src' ),
            'url'           => $this->_url,
            'version'       => $this->_version,
        ];
    }

    /**
     * Check if all required constants are defined
     *
     * @return bool
     */
    public static function has_required_constants()
    {
        return defined( 'AWS_LAMBDA_CRITICAL_CSS_KEY' ) && defined( 'AWS_LAMBDA_CRITICAL_CSS_SECRET' ) && defined( 'AWS_LAMBDA_CRITICAL_CSS_REGION' ) && defined( 'AWS_LAMBDA_CRITICAL_CSS_BUCKET' );
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
        return defined( 'AWS_LAMBDA_CRITICAL_CSS_FUNCTION' ) ? AWS_LAMBDA_CRITICAL_CSS_FUNCTION : 'wordpress_critical_css';
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
     * Return URL's of stylesheets which are used for critical path
     *
     * @return array
     */
    public static function get_styles()
    {
        global $wp_styles, $wp_version;

        $registered_styles = $wp_styles->registered;
        $styles = [];

        foreach ( apply_filters( static::KEY . '_styles', [] ) as $handle ) {
            if ( isset( $registered_styles[ $handle ] ) ) {
                $style = $registered_styles[ $handle ];
                $styles[] = [
                    'handle' => $style->handle,
                    'src'    => $style->src,
                    'ver'    => $style->ver ? $style->ver : $wp_version,
                ];
            }
        }

        return $styles;
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
}