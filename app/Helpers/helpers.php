<?php

if (!function_exists('menuActive')) {
    function menuActive(array $patterns, $class = 'active pcoded-trigger')
    {
        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern) || request()->is($pattern)) {
                return $class;
            }
        }
        return '';
    }
}
