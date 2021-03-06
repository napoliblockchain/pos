<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit366a29d4104f590d60c4fc491cab110b
{
    public static $files = array (
        'decc78cc4436b1292c6c0d151b19445c' => __DIR__ . '/..' . '/phpseclib/phpseclib/phpseclib/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'p' => 
        array (
            'phpseclib3\\' => 11,
        ),
        'P' => 
        array (
            'ParagonIE\\ConstantTime\\' => 23,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'phpseclib3\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpseclib/phpseclib/phpseclib',
        ),
        'ParagonIE\\ConstantTime\\' => 
        array (
            0 => __DIR__ . '/..' . '/paragonie/constant_time_encoding/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit366a29d4104f590d60c4fc491cab110b::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit366a29d4104f590d60c4fc491cab110b::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit366a29d4104f590d60c4fc491cab110b::$classMap;

        }, null, ClassLoader::class);
    }
}
