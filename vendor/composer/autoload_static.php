<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitf6f53022c79571db3ea387e82d63a6fb
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitf6f53022c79571db3ea387e82d63a6fb::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitf6f53022c79571db3ea387e82d63a6fb::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
