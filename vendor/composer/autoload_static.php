<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit26f2ccdf314ea23580e659cbaab8fe51
{
    public static $files = array (
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
        'bd9634f2d41831496de0d3dfe4c94881' => __DIR__ . '/..' . '/symfony/polyfill-php56/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Polyfill\\Util\\' => 22,
            'Symfony\\Polyfill\\Php56\\' => 23,
            'Symfony\\Polyfill\\Mbstring\\' => 26,
            'Symfony\\Contracts\\Translation\\' => 30,
            'Symfony\\Component\\Translation\\' => 30,
        ),
        'G' => 
        array (
            'GraysonErhard\\ReservationExporter\\' => 34,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Polyfill\\Util\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-util',
        ),
        'Symfony\\Polyfill\\Php56\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-php56',
        ),
        'Symfony\\Polyfill\\Mbstring\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-mbstring',
        ),
        'Symfony\\Contracts\\Translation\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/translation-contracts',
        ),
        'Symfony\\Component\\Translation\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/translation',
        ),
        'GraysonErhard\\ReservationExporter\\' => 
        array (
            0 => __DIR__ . '/../..' . '/',
        ),
    );

    public static $fallbackDirsPsr4 = array (
        0 => __DIR__ . '/..' . '/nesbot/carbon/src',
    );

    public static $prefixesPsr0 = array (
        'U' => 
        array (
            'UpdateHelper\\' => 
            array (
                0 => __DIR__ . '/..' . '/kylekatarnls/update-helper/src',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit26f2ccdf314ea23580e659cbaab8fe51::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit26f2ccdf314ea23580e659cbaab8fe51::$prefixDirsPsr4;
            $loader->fallbackDirsPsr4 = ComposerStaticInit26f2ccdf314ea23580e659cbaab8fe51::$fallbackDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit26f2ccdf314ea23580e659cbaab8fe51::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
