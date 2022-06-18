<?php

namespace Innocode\CriticalCSS;

use Innocode\CriticalCSS\Interfaces\IntegrationInterface;
use Innocode\CriticalCSS\Interfaces\MemoInterface;
use Innocode\SecretsManager\SecretsManager;
use WP_Term;

/**
 * Class Plugin
 * @package Innocode\CriticalCSS
 */
final class Plugin
{
    /**
     * With same names as in related WordPress functions e.g. is_singular => 'singular'.
     *
     * @note Priority is important and used to determine which template to set.
     *
     * @const array
     */
    const TEMPLATES = [
        '404',
        'search',
        'front_page',
        'home',
        'privacy_policy',
        'post_type_archive',
        'tax',
        'singular', // Includes 'attachment', 'single' and 'page'.
        'category',
        'tag',
        'author',
        'date',
        'archive',
    ];

    const INTEGRATION_FLUSH_CACHE = 'flush_cache';
    const INTEGRATION_BATCACHE = 'batcache';

    /**
     * @var Db
     */
    protected $db;
    /**
     * @var SecretsManager
     */
    private $secrets_manager;
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
     * @var string|null
     */
    private $template;
    /**
     * @var string
     */
    private $object = '';
    /**
     * For custom needs like catching query parameters.
     *
     * @var array
     */
    private $query_vars = [];
    /**
     * @var IntegrationInterface[]
     */
    private $integrations = [];

    /**
     * Plugin constructor.
     * @param string $key
     * @param string $secret
     * @param string $region
     */
    public function __construct( string $key, string $secret, string $region )
    {
        $this->db = new Db();
        $this->secrets_manager = new SecretsManager( 'critical_css' );
        $this->stylesheet = new Stylesheet();
        $this->lambda = new Lambda( $key, $secret, $region );
        $this->rest_controller = new RESTController();

        $this->integrations[ Plugin::INTEGRATION_FLUSH_CACHE ] = new Integrations\FlushCache\Integration();
        $this->integrations[ Plugin::INTEGRATION_BATCACHE ] = new Integrations\Batcache\Integration();
    }

    /**
     * @return Db
     */
    public function get_db() : Db
    {
        return $this->db;
    }

    /**
     * @return SecretsManager
     */
    public function get_secrets_manager() : SecretsManager
    {
        return $this->secrets_manager;
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
     * @return string|null
     */
    public function get_template() : ?string
    {
        return apply_filters( 'innocode_critical_css_template', $this->template );
    }

    /**
     * @return string
     */
    public function get_object() : string
    {
        return apply_filters( 'innocode_critical_css_object', $this->object );
    }

    /**
     * @return array
     */
    public function get_query_vars() : array
    {
        return apply_filters( 'innocode_critical_css_query_vars', $this->query_vars );
    }

    /**
     * @return string
     */
    public function get_query_vars_key() : string
    {
        return md5( serialize( $this->get_query_vars() ) );
    }

    /**
     * @return IntegrationInterface[]
     */
    public function get_integrations() : array
    {
        return $this->integrations;
    }

    /**
     * @return void
     */
    public function run() : void
    {
        register_activation_hook( INNOCODE_CRITICAL_CSS, [ $this, 'activate' ] );
        register_deactivation_hook( INNOCODE_CRITICAL_CSS, [ $this, 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'run_integrations' ] );
        add_action( 'init', [ $this->get_db(), 'init' ] );
        add_action( 'init', [ $this, 'init_memo' ], 11 ); // Priority 11 to make it simple to hook on 'init' hook w/o 3rd parameter.
        add_action( 'rest_api_init', [ $this->get_rest_controller(), 'register_routes' ] );

        add_filter( 'template_include', [ $this, 'init' ] );
        add_action( 'wp_enqueue_scripts', [ $this->get_stylesheet(), 'init' ], PHP_INT_MAX );
        add_action( 'wp_head', [ $this, 'do_stylesheet' ], 2 ); // Priority is 2 to make sure it is called after 'wp_enqueue_scripts'.

        add_action( 'delete_post', [ $this, 'delete_post_stylesheet' ] );
        add_action( 'delete_term', [ $this, 'delete_term_stylesheet' ], 10, 3 );
        add_action( 'delete_user', [ $this, 'delete_author_stylesheet' ] );

        add_action( 'innocode_critical_css', [ $this, 'invoke_lambda' ], 10, 6 );

        add_action( 'delete_expired_transients', [ $this->get_secrets_manager(), 'flush_expired' ] );
    }

    /**
     * @return void
     */
    public function run_integrations() : void
    {
        foreach ( $this->get_integrations() as $integration ) {
            $integration->run( $this );
        }
    }

    /**
     * @return void
     */
    public function init_memo() : void
    {
        $memo = apply_filters( 'innocode_critical_css_memo', null );

        if ( ! ( $memo instanceof MemoInterface ) ) {
            $cookie_name = apply_filters( 'innocode_critical_css_cookie_name', 'innocode_critical_css' );
            $memo = new CookieMemo( $cookie_name );
        }

        add_filter( 'innocode_critical_css_can_print', [ $memo, 'can_print' ], 10, 6 );
        add_action( 'innocode_critical_css_printed', [ $memo, 'printed' ], 10, 5 );
    }

    /**
     * @param string $template
     * @return string
     */
    public function init( string $template ) : string
    {
        if ( is_embed() ) {
            // No critical CSS for Embeds.
            return $template;
        }

        foreach ( Plugin::TEMPLATES as $template_name ) {
            if ( call_user_func( "is_$template_name" ) ) {
                $this->template = $template_name;

                break;
            }
        }

        if ( is_404() || is_search() || is_front_page() || is_home() || is_privacy_policy() ) {
            $this->object = '';
        } elseif ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );

            if ( is_array( $post_type ) ) {
                $post_type = reset( $post_type );
            }

            if (
                null !== ( $post_type_object = get_post_type_object( $post_type ) ) &&
                $post_type_object->has_archive
            ) {
                $this->object = $post_type;
            } else {
                $this->object = '';
            }
        } elseif ( is_tax() || is_singular() || is_category() || is_tag() || is_author() ) {
            $object = get_queried_object();
            $object_id = $object instanceof WP_Term ? $object->term_taxonomy_id : get_queried_object_id();
            $this->object = (string) $object_id;
        } elseif ( is_day() ) {
            $this->object = get_the_date( 'Ymd' );
        } elseif ( is_month() ) {
            $this->object = get_the_date( 'Ym' );
        } elseif ( is_year() ) {
            $this->object = get_the_date( 'Y' );
        } else {
            $this->object = '';
        }

        return $template;
    }

    /**
     * @return void
     */
    public function do_stylesheet() : void
    {
        if ( null === $this->get_template() || ! $this->get_stylesheet()->has_styles() ) {
            return;
        }

        $this->schedule_lambda();
        $this->print_stylesheet();
    }

    /**
     * Schedules cron task with AWS Lambda function.
     *
     * @return void
     */
    public function schedule_lambda() : void
    {
        $template = $this->get_template();
        $object = $this->get_object();
        $query_vars_key = $this->get_query_vars_key();
        $stylesheet = $this->get_stylesheet();
        $hash = $stylesheet->get_hash();

        if (
            null !== ( $entry = $this->get_db()->get_entry( $template, $object, $query_vars_key ) ) &&
            (
                $entry->get_version() === $hash ||
                (
                    ! $entry->has_version() &&
                    null !== $entry->get_updated() &&
                    time() <= $entry->get_updated()->getTimestamp() + $this->get_secrets_manager()->get_expiration()
                )
            )
        ) {
            return;
        }

        $args = [
            Helpers::get_current_url(),
            $template,
            $object,
            $query_vars_key,
            $stylesheet->get_sources(),
            $hash,
        ];

        if ( wp_next_scheduled( 'innocode_critical_css', $args ) ) {
            return;
        }

        wp_schedule_single_event( time(), 'innocode_critical_css', $args );
    }

    /**
     * Prints critical path stylesheet.
     * Usually stylesheet files are cached on production, so there is no reason to use critical css every time.
     * Developer may control that with filters: 'innocode_critical_css_can_print' and 'innocode_critical_css_printed'.
     *
     * @return void
     */
    public function print_stylesheet() : void
    {
        $template = $this->get_template();
        $object = $this->get_object();
        $query_vars = $this->get_query_vars();
        $query_vars_key = $this->get_query_vars_key();
        $hash = $this->get_stylesheet()->get_hash();

        if (
            ! apply_filters( 'innocode_critical_css_can_print', true, $template, $object, $query_vars, $query_vars_key, $hash ) ||
            null === ( $entry = $this->get_db()->get_entry( $template, $object, $query_vars_key ) )
        ) {
            return;
        }

        $stylesheet = $entry->get_stylesheet();

        if ( ! $stylesheet ) {
            return;
        }

        printf(
            "<style>\n%s\n</style>\n",
            apply_filters( 'innocode_critical_css_stylesheet', $stylesheet, $template, $object, $query_vars, $query_vars_key, $hash )
        );

        do_action( 'innocode_critical_css_printed', $template, $object, $query_vars, $query_vars_key, $hash );
    }

    /**
     * Invokes AWS Lambda function
     *
     * @param string $url
     * @param string $template
     * @param string $object
     * @param string $query_vars_key
     * @param array  $sources
     * @param string $hash
     *
     * @return void
     */
    public function invoke_lambda(
        string $url,
        string $template,
        string $object,
        string $query_vars_key,
        array $sources,
        string $hash
    ) : void
    {
        $key = "$template:$object:$query_vars_key";

        $secrets_manager = $this->get_secrets_manager();

        list( $is_set, $secret ) = $secrets_manager->init( $key );

        if ( ! $is_set ) {
            return;
        }

        $this->get_db()->clear_entry( $template, $object, $query_vars_key );

        $lambda = $this->get_lambda();
        $lambda->init();
        $lambda( [
            'url'        => $url,
            'key'        => $key,
            'styles'     => $sources,
            'hash'       => $hash,
            'return_url' => $this->get_rest_controller()->url(),
            'secret'     => $secret,
        ] );
    }

    /**
     * @param int $post_id
     * @return void
     */
    public function delete_post_stylesheet( int $post_id ) : void
    {
        $this->get_db()->delete_entries( 'singular', $post_id );
    }

    /**
     * @param int    $term_id
     * @param int    $term_taxonomy_id
     * @param string $taxonomy
     * @return void
     */
    public function delete_term_stylesheet( int $term_id, int $term_taxonomy_id, string $taxonomy ) : void
    {
        switch ( $taxonomy ) {
            case 'category':
                $template = 'category';

                break;
            case 'post_tag':
                $template = 'tag';

                break;
            default:
                $template = 'term';

                break;
        }

        $this->get_db()->delete_entries( $template, $term_taxonomy_id );
    }

    /**
     * @param int $user_id
     * @return void
     */
    public function delete_author_stylesheet( int $user_id ) : void
    {
        $this->get_db()->delete_entries( 'author', $user_id );
    }

    /**
     * @return void
     */
    public function activate() : void
    {
        $this->get_db()->init();
    }

    /**
     * @return void
     */
    public function deactivate() : void
    {
        $this->get_db()->drop_table();
        $this->get_secrets_manager()->flush();
    }
}
