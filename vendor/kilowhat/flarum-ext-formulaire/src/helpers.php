<?php

if (!function_exists('config')) {
    /**
     * Used as a replacement to Laravel's config() which is not available in Flarum
     * The Excel library always provides defaults, so we just need to proxy the default and ignore the key
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config(string $key, $default = null)
    {
        return $default;
    }
}
