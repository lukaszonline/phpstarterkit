<?php

namespace PhpStarterKit;

class Page extends Core
{
    public static function run($function, ...$argument)
    {
        if(method_exists(__CLASS__, $function)) {
            return self::$function(...$argument);
        }

        return $function($argument);
    }

    protected static function url($path = NULL): string
    {
        if(!is_null($path)) {
            $path = ($path == 'index') ? NULL : '/' . trim($path);
        }

        return ENV['url'] . $path;
    }

    protected static function asset($path): string
    {
        return self::url('assets/' . trim($path));
    }
}