<?php

namespace Innocode\CriticalCSS;

/**
 * Class Stylesheet
 * @package Innocode\CriticalCSS
 */
class Stylesheet
{
    /**
     * @var array
     */
    protected $styles = [];
    /**
     * @var string
     */
    protected $hash;

    /**
     * Returns URL's of stylesheets which are used for critical path.
     *
     * @return array
     */
    public function get_styles() : array
    {
        return $this->styles;
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
     * @return void
     */
    public function init() : void
    {
        global $wp_version;

        $registered_styles = wp_styles()->registered;
        $default_version = apply_filters( 'innocode_critical_css_default_version', $wp_version );

        foreach ( apply_filters( 'innocode_critical_css_styles', [] ) as $handle ) {
            if ( isset( $registered_styles[ $handle ] ) ) {
                $style = $registered_styles[ $handle ];
                $this->styles[] = [
                    'handle' => $style->handle,
                    'src'    => $style->src,
                    'ver'    => $style->ver ?: $default_version,
                ];
            }
        }

        $this->hash = md5( serialize( $this->styles ) );
    }

    /**
     * @return bool
     */
    public function has_styles() : bool
    {
        return ! empty( $this->styles );
    }

    /**
     * @return array
     */
    public function get_sources() : array
    {
        return array_reduce( $this->get_styles(), function ( array $sources, array $style ) {
            $src = $style['src'];

            if ( ! empty( $style['ver'] ) ) {
                $src = add_query_arg( 'ver', $style['ver'], $src );
            }

            $sources[] = esc_url( apply_filters( 'style_loader_src', $src, $style['handle'] ) );

            return $sources;
        }, [] );
    }
}
