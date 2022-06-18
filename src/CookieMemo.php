<?php

namespace Innocode\CriticalCSS;

use Innocode\CriticalCSS\Interfaces\MemoInterface;

class CookieMemo implements MemoInterface
{
    /**
     * @var string
     */
    protected $cookie_name;

    /**
     * @param string $cookie_name
     */
    public function __construct( string $cookie_name )
    {
        $this->cookie_name = $cookie_name . ( defined( 'COOKIEHASH' ) ? '_' . COOKIEHASH : '' );
    }

    /**
     * @return string
     */
    public function get_cookie_name() : string
    {
        return $this->cookie_name;
    }

    /**
     * @param string $hash
     * @return string
     */
    public function generate_cookie_name( string $hash ) : string
    {
        return "{$this->get_cookie_name()}_$hash";
    }

    /**
     * @param bool   $can_print
     * @param string $template
     * @param string $object
     * @param array  $query_vars
     * @param string $query_vars_key
     * @param string $hash
     * @return bool
     */
    public function can_print(
        bool $can_print,
        string $template,
        string $object,
        array $query_vars,
        string $query_vars_key,
        string $hash
    ) : bool
    {
        return ! isset( $_COOKIE[ $this->generate_cookie_name( $hash ) ] );
    }

    /**
     * @param string $template
     * @param string $object
     * @param array  $query_vars
     * @param string $query_vars_key
     * @param string $hash
     * @return void
     */
    public function printed(
        string $template,
        string $object,
        array $query_vars,
        string $query_vars_key,
        string $hash
    ) : void
    {
        printf(
            "<script>\ndocument.cookie = '%s=%s; path=%s%s%s; samesite=lax';\n</script>\n",
            $this->generate_cookie_name( $hash ),
            rawurlencode( "$template:$object:$query_vars_key" ),
            COOKIEPATH,
            COOKIE_DOMAIN ? '; domain=' . COOKIE_DOMAIN : '',
            is_ssl() ? '; secure' : ''
        );
    }
}
