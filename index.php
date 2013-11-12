<?php

$config = array();

if (file_exists(__DIR__ . '/config.php'))
{
    $config = include __DIR__ . '/config.php';
}

if (!empty($config['debug']))
{
    error_reporting(-1);

    set_error_handler(function($errno, $errstr, $errfile, $errline)
    {
        echo $errstr.' in '.$errfile.' on line '.$errline;
    });
}

include __DIR__ . '/vendor/autoload.php';
include __DIR__ . '/src/Rah/Textile/Sandbox.php';

\Rah\Textile\Sandbox::init($config);
