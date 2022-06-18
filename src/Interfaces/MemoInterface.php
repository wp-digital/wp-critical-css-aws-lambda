<?php

namespace Innocode\CriticalCSS\Interfaces;

interface MemoInterface
{
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
    ) : bool;

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
    ) : void;
}
