<?php
/**
 * Users who do not have 'composer' to manage dependencies, include this
 * file to provide auto-loading of the classes in this library.
 */
 namespace Mike42;

 class Autoloader
 {
	 /**
      * Register the autoloader, by default this will put the BitPay autoloader
      * first on the stack, to append the autoloader, pass `false` as an argument.
      *
      * Some applications will throw exceptions if the class isn't found and
      * some are not compatible with PSR standards.
      *
      * @param boolean $prepend
      */
     public static function register($prepend = true)
     {
         spl_autoload_register(array(__CLASS__, 'autoload'), true, (bool) $prepend);
     }

	 public static function autoload($class)
	 {

		spl_autoload_register ( function ($class) {
			/*
			 * PSR-4 autoloader, based on PHP Framework Interop Group snippet (Under MIT License.)
			 * https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-examples.md
			 */
			$prefix = "Mike42\\";
			$base_dir = __DIR__ . "/src/Mike42/";

			/* Only continue for classes in this namespace */
			$len = strlen ( $prefix );
			if (strncmp ( $prefix, $class, $len ) !== 0) {
				return;
			}

			/* Require the file if it exists */
			$relative_class = substr ( $class, $len );
			$file = $base_dir . str_replace ( '\\', '/', $relative_class ) . '.php';
			if (file_exists ( $file )) {
				require $file;
			}
		} );
	}

}
