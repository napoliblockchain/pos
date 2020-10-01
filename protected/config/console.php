<?php

// This is the configuration for yiic console application.
// Any writable CConsoleApplication properties can be configured here.
$libsPath = dirname(__FILE__).DIRECTORY_SEPARATOR.'../../../napay-packages';
Yii::setPathOfAlias('libs', $libsPath);

return array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'My Console Application',

	// preloading 'log' component
	'preload'=>array('log'),
	// autoloading model and component classes
	'import'=>array(
		'application.models.*',
		'application.components.*',
	),

	// application components
	'components'=>array(

		// database settings are configured in database.php
		'db'=>require(dirname(__FILE__).'/database.php'),

		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
				array(
					'class'=>'CFileLogRoute',
					'levels'=>'error, warning',
				),
			),
		),
		// MIE CLASSI
		'webRequest'=>require($libsPath.'/webRequest/webRequest.php'),
		'crypt'=>require($libsPath.'/crypt/crypt.php'),
		'NAPay'=>require($libsPath.'/NAPay/Autoloader.php'),
		'Utils'=>require($libsPath.'/Utils/Utils.php'),
		'eth'=>require($libsPath.'/ethereum/eth.php'),

		//'Settings'=>require($libsPath.'/NAPay/Settings.php'),
		// 'Notifi'=>require(dirname(__FILE__).'../../extensions/web-app/Notifi.php'),
		// 'Push'=>require(dirname(__FILE__).'../../extensions/web-app/Push.php'),
		// 'SaveModels'=>require(dirname(__FILE__).'../../extensions/web-app/SaveModels.php'),
		// 'Save'=>require(dirname(__FILE__).'../../extensions/web-app/Save.php'),
		// 'eth'=>require(dirname(__FILE__).'../../extensions/web-app/eth.php'),

	),
	'params'=>array(
		'libsPath'=>$libsPath,
	),
);
