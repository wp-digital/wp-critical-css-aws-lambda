<?php

namespace Innocode\CriticalCSSAWSLambda;

/**
 * Class Stylesheet
 * @package Innocode\CriticalCSSAWSLambda
 */
class Stylesheet
{
    /**
     * @var array
     */
    protected $styles;
    /**
     * @var array
     */
    protected $registered_styles;
    /**
     * @var string
     */
    protected $hash;

    /**
     * Stylesheet constructor.
     * @param array $styles
     */
    public function __construct( array $styles )
    {
        $this->styles = $styles;
    }

    /**
     * @return array
     */
    public function get_styles() : array
    {
        return $this->styles;
    }

    /**
     * Returns URL's of stylesheets which are used for critical path.
     *
     * @return array
     */
    public function get_registered_styles() : array
    {
        return $this->registered_styles;
    }

    /**
     * Returns critical path hash.
     *
     * @return string
     */
    public function get_hash() : string
    {
        return $this->hash;
    }

    /**
     * @param string $default_version
     */
    public function init( string $default_version )
    {
        $this->init_registered_styles( $default_version );
        $this->init_hash();
    }

    /**
     * @param string $default_version
     */
    public function init_registered_styles( string $default_version )
    {
        global $wp_styles;

        if ( ! isset( $wp_styles ) ) {
            return;
        }

        $registered_styles = $wp_styles->registered;

        foreach ( $this->get_styles() as $handle ) {
            if ( isset( $registered_styles[ $handle ] ) ) {
                $style = $registered_styles[ $handle ];
                $this->registered_styles[] = [
                    'handle' => $style->handle,
                    'src'    => $style->src,
                    'ver'    => $style->ver ? $style->ver : $default_version,
                ];
            }
        }
    }

    public function init_hash()
    {
        $this->hash = md5(
            array_reduce( $this->get_registered_styles(), function ( $version, array $style ) {
                return "$version|{$style['handle']}:{$style['ver']}";
            }, '' )
        );
    }

    /**
     * @return array
     */
    public function get_sources() : array
    {
        return array_reduce( $this->get_registered_styles(), function ( array $sources, array $style ) {
            $src = $style['src'];

            if ( ! empty( $style['ver'] ) ) {
                $src = add_query_arg( 'ver', $style['ver'], $src );
            }

            $sources[] = esc_url( apply_filters( 'style_loader_src', $src ) );

            return $sources;
        }, [] );
    }
}
