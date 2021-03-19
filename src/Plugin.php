<?php

namespace Innocode\CriticalCSSAWSLambda;

/**
 * Class Plugin
 * @package Innocode\CriticalCSSAWSLambda
 */
final class Plugin
{
    /**
     * @var Stylesheet
     */
    private $stylesheet;
    /**
     * @var Lambda
     */
    private $lambda;
    /**
     * @var RESTController
     */
    private $rest_controller;
    /**
     * @var string
     */
    private $template_name;

    /**
     * Plugin constructor.
     * @param array       $styles
     * @param string      $key
     * @param string      $secret
     * @param string      $region
     * @param string|null $function
     */
    public function __construct( array $styles, string $key, string $secret, string $region, string $function = null )
    {
        $this->stylesheet = new Stylesheet( $styles );
        $this->lambda = new Lambda( $key, $secret, $region );

        if ( null !== $function ) {
            $this->lambda->set_function( $function );
        }

        $this->rest_controller = new RESTController();
    }

    /**
     * @return Stylesheet
     */
    public function get_stylesheet() : Stylesheet
    {
        return $this->stylesheet;
    }

    /**
     * @return Lambda
     */
    public function get_lambda() : Lambda
    {
        return $this->lambda;
    }

    /**
     * @return RESTController
     */
    public function get_rest_controller() : RESTController
    {
        return $this->rest_controller;
    }

    /**
     * @return string
     */
    public function get_template_name() : string
    {
        return $this->template_name;
    }

    public function run()
    {
        add_filter( 'template_include', [ $this, 'init_template_name' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'init_stylesheet' ], PHP_INT_MAX );
        add_action( 'wp_head', [ $this, 'schedule_lambda' ], 2 );
        add_action( 'wp_head', [ $this, 'print_stylesheet' ], 3 );
        add_action( 'aws_lambda_critical_css', [ $this, 'invoke_lambda' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    /**
     * @param string $template
     * @return string
     */
    public function init_template_name( string $template ) : string
    {
        $this->template_name = sanitize_key( str_replace( get_template_directory(), '', $template ) );

        return $template;
    }

    public function init_stylesheet()
    {
        global $wp_version;

        $this->get_stylesheet()->init(
            apply_filters( 'aws_lambda_critical_css_styles_default_style_version', $wp_version )
        );
    }

    /**
     * Schedules cron task with AWS Lambda function.
     */
    public function schedule_lambda()
    {
        $template_name = $this->get_template_name();

        if ( ! $template_name ) {
            return;
        }

        $stylesheet = $this->get_stylesheet();
        $new_hash = $stylesheet->get_hash();

        if ( ! $new_hash ) {
            return;
        }

        $current_hash = get_option( "aws_lambda_critical_css_$template_name" );

        if ( $current_hash == $new_hash ) {
            return;
        }

        $secret = wp_generate_password( 32, true, true );
        $expiration = 20 * MINUTE_IN_SECONDS;

        set_transient( "aws_lambda_critical_css_$template_name", $new_hash, $expiration );
        set_transient( 'aws_lambda_critical_css_secret', wp_hash_password( $secret ), $expiration );

        wp_schedule_single_event( time(), 'aws_lambda_critical_css', [
            Helpers::get_current_url(),
            $template_name,
            $stylesheet->get_sources(),
            $new_hash,
            $this->get_rest_controller()->url( 'stylesheet' ),
            $secret
        ] );
    }

    /**
     * Invokes AWS Lambda function
     *
     * @param string $url
     * @param string $key
     * @param array  $styles
     * @param string $hash
     * @param string $return_url
     * @param string $secret
     */
    public function invoke_lambda(
        string $url,
        string $key,
        array $styles,
        string $hash,
        string $return_url,
        string $secret
    )
    {
        $lambda = $this->get_lambda();
        $lambda->init();
        $lambda( [
            'url'        => $url,
            'key'        => $key,
            'styles'     => $styles,
            'hash'       => $hash,
            'return_url' => $return_url,
            'secret'     => $secret,
        ] );
    }

    /**
     * Prints critical path stylesheet.
     * Usually stylesheet files are cached on production, so there is no reason to use critical css every time.
     * Developer may control that with filters: 'aws_lambda_critical_css_can_print' and 'aws_lambda_critical_css_printed'.
     */
    public function print_stylesheet()
    {
        $template_name = $this->get_template_name();
        $hash = $this->get_stylesheet()->get_hash();

        if ( ! apply_filters( 'aws_lambda_critical_css_can_print', true, $template_name, $hash ) ) {
            return;
        }

        $stylesheet = trim(
            (string) get_option( "aws_lambda_critical_css_{$template_name}_stylesheet", '' )
        );

        if ( ! $stylesheet ) {
            return;
        }

        printf(
            '<style>%s</style>',
            apply_filters( 'aws_lambda_critical_css_stylesheet', strip_tags( $stylesheet ), $template_name, $hash )
        );

        do_action( 'aws_lambda_critical_css_printed', $template_name, $hash );
    }

    /**
     * Adds routes.
     */
    public function register_rest_routes()
    {
        $this->get_rest_controller()->register_routes();
    }
}
