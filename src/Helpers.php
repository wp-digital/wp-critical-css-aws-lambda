<?php

namespace Innocode\CriticalCSS;

/**
 * Class Helpers
 * @package Innocode\CriticalCSS
 */
final class Helpers
{
    /**
     * Returns current URL.
     *
     * @return string
     */
    public static function get_current_url() : string
    {
        global $wp;

        return add_query_arg( $_GET, home_url( trailingslashit( $wp->request ) ) );
    }
}
