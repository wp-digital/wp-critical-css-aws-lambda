<?php

namespace Innocode\CriticalCSS\Integrations\Batcache;

use Innocode\CriticalCSS\Entry;
use Innocode\CriticalCSS\Interfaces\IntegrationInterface;
use Innocode\CriticalCSS\Plugin;

class Integration implements IntegrationInterface
{
    /**
     * @param Plugin $plugin
     * @return void
     */
    public function run( Plugin $plugin ) : void
    {
        add_action( 'innocode_critical_css_callback', [ $this, 'flush' ], 10, 2 );
    }

    /**
     * @param Entry  $entry
     * @param string $url
     * @return void
     */
    public function flush( Entry $entry, string $url ) : void
    {
        if ( ! function_exists( 'batcache_clear_url' ) || ! $url ) {
            return;
        }

        batcache_clear_url( $url );
    }
}
