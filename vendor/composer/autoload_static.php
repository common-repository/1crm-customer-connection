<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita6a9de56aefef21f9018b9c8e087f526
{
    public static $files = array (
        '7b11c4dc42b3b3023073cb14e519683c' => __DIR__ . '/..' . '/ralouphie/getallheaders/src/getallheaders.php',
        'c964ee0ededf28c96ebd9db5099ef910' => __DIR__ . '/..' . '/guzzlehttp/promises/src/functions_include.php',
        'a0edc8309cc5e1d60e3047b5df6b7052' => __DIR__ . '/..' . '/guzzlehttp/psr7/src/functions_include.php',
        '37a3dc5111fe8f707ab4c132ef1dbc62' => __DIR__ . '/..' . '/guzzlehttp/guzzle/src/functions_include.php',
    );

    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\Http\\Message\\' => 17,
        ),
        'O' => 
        array (
            'OneCRM\\Portal\\' => 14,
            'OneCRM\\' => 7,
        ),
        'G' => 
        array (
            'GuzzleHttp\\Psr7\\' => 16,
            'GuzzleHttp\\Promise\\' => 19,
            'GuzzleHttp\\' => 11,
        ),
        'D' => 
        array (
            'Diggin\\HTMLSax\\' => 15,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'OneCRM\\Portal\\' => 
        array (
            0 => __DIR__ . '/../..' . '/include/class',
        ),
        'OneCRM\\' => 
        array (
            0 => __DIR__ . '/..' . '/onecrm/api/src',
        ),
        'GuzzleHttp\\Psr7\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/psr7/src',
        ),
        'GuzzleHttp\\Promise\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/promises/src',
        ),
        'GuzzleHttp\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/guzzle/src',
        ),
        'Diggin\\HTMLSax\\' => 
        array (
            0 => __DIR__ . '/..' . '/diggin/diggin-htmlsax/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'H' => 
        array (
            'HTML' => 
            array (
                0 => __DIR__ . '/..' . '/pear/html_safe',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita6a9de56aefef21f9018b9c8e087f526::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita6a9de56aefef21f9018b9c8e087f526::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInita6a9de56aefef21f9018b9c8e087f526::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
