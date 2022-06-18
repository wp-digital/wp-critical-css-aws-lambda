<?php

namespace Innocode\CriticalCSS\Integrations\FlushCache;

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
        $flush_secrets = [ $plugin->get_secrets_manager(), 'flush' ];
        $clean_db = [ $plugin->get_db(), 'drop_table' ];

        if ( function_exists( 'flush_cache_add_button' ) ) {
            flush_cache_add_button(
                __( 'Critical CSS secrets', 'innocode-critical-css' ),
                $flush_secrets
            );
            flush_cache_add_button(
                __( 'Critical CSS database', 'innocode-critical-css' ),
                $clean_db
            );
        }
    }
}
