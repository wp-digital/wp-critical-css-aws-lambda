<?php

namespace Innocode\CriticalCSS\Interfaces;

use Innocode\CriticalCSS\Plugin;

interface IntegrationInterface
{
    /**
     * @param Plugin $plugin
     * @return void
     */
    public function run( Plugin $plugin ) : void;
}
