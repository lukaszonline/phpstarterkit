<?php

$prefix = dirname($_SERVER['SCRIPT_FILENAME']) . '/..';

$paths = [
    'project' => $prefix,
    'public' => $prefix . '/public',
    'resources' => $prefix . '/resources',
    'storage' => $prefix . '/storage',
];

if(is_file($paths['project'] . '/.env')) {
    $env = [];

    foreach(file($paths['project'] . '/.env') as $assignment) {
        if(!empty(trim($assignment))) {
            $assignment = explode('=', $assignment);
            $env[strtolower($assignment[0])] = trim(str_replace('"', '', $assignment[1]));
        }
    }
}
else {
    exit('File .env not found!');
}

define('ENV', $env);
define('PATHS', $paths);

class_alias('PhpStarterKit\Page', 'Page');