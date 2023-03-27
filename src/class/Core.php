<?php

namespace PhpStarterKit;

class Core
{
    protected static function env($key): string
    {
        return ENV[$key] ?? $key;
    }

    protected static function project($key): string
    {
        return PROJECT[$key] ?? $key;
    }

    protected static function __($path): string
    {
        $path = str_replace('.this.', '.' . self::project('page') . '.', $path);
        $explode = explode('.', $path);
        $content = constant(strtoupper(array_shift($explode)));
        $key = implode('.', $explode);
        $parts = strtok($key, '.');

        while($parts !== FALSE) {
            if(!isset($content[$parts])) {
                return $path;
            }

            $content = $content[$parts];
            $parts = strtok('.');
        }

        return $content;
    }
}