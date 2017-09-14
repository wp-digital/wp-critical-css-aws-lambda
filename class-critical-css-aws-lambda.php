<?php

use Aws\Lambda\LambdaClient;

/**
 * Class WP_Critical_CSS_AWS_Lambda
 */
class WP_Critical_CSS_AWS_Lambda
{
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
    protected $_cache_key = '';
    /**
     * @var array
     */
    protected $_css_files = [];
    /**
     * @var string
     */
    protected $_url = '';

    public function __construct()
    {
        if ( static::has_required_constants() ) {
            $this->_template_name = static::get_template_name();
            $this->_cache_key = sanitize_key( 'aws_lambda_critical_css_' . sanitize_key( $this->_template_name ) );

//            if ( !$invoke ) {
                $this->_lambda_client = static::get_lambda_client();
                $this->_lambda_function = static::get_lambda_function();
                $this->_css_files = static::get_css_files();
                $this->_url = static::get_current_url();
//            }
        }
    }

    public function run()
    {
        if ( !is_null( $this->_lambda_client ) ) {
            $invoke = $this->_lambda_client->invoke( [
                'FunctionName' => $this->_lambda_function,
                'Payload'      => json_encode( $this->_get_lambda_args() ),
            ] );

//            set_transient( $this->_cache_key, $invoke );
        }
    }

    /**
     * Return Lambda arguments
     *
     * @return array
     */
    protected function _get_lambda_args()
    {
        return [
            'bucket'        => AWS_LAMBDA_CRITICAL_CSS_BUCKET,
            'template_name' => $this->_template_name,
            'css_files'     => $this->_css_files,
            'url'           => $this->_url,
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
    public static function get_css_files()
    {
        global $wp_styles;

        $registered_styles = $wp_styles->registered;
        $css_files = [];

        foreach ( apply_filters( 'aws_lambda_critical_css_files', [] ) as $handle ) {
            if ( isset( $registered_styles[ $handle ] ) ) {
                $css_files[] = $registered_styles[ $handle ]->src;
            }
        }

        return $css_files;
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