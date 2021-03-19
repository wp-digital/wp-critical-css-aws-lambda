<?php

namespace Innocode\CriticalCSSAWSLambda;

/**
 * Class Helpers
 * @package Innocode\CriticalCSSAWSLambda
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

        return home_url( add_query_arg( [], $wp->request ) );
    }

    /**
     * Checks if string is md5 hash.
     *
     * @param string $hash
     *
     * @return bool
     */
    public static function is_md5( string $hash ) : bool
    {
        return (bool) preg_match( '/^[a-f0-9]{32}$/', $hash );
    }
}
