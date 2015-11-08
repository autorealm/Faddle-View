<?php

define('FADDLE_PATH', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR. 'src');
require FADDLE_PATH . '/Faddle/autoload.php';


$config = array(
	'default' => array(
			'suffix'  => '.view.php',
			'template_path'  =>  dirname(__FILE__) . '/templates',
			'storage_path'  =>  'cache',
			'bucket' => 'templates',
			'engine'  =>  'default',
			'cache'  =>  true,
			'cache_path' => 'cache',
			'expire'  =>  5000,
	),
	'nature' => array(
			'suffix'  => '.php',
			'template_path'  =>  dirname(__FILE__) . '/templates',
			'storage_path'  =>  'cache',
			'bucket' => 'templates',
			'engine'  =>  false,
			'expire'  =>  0,
	)
);

$args = array(
	'engine' => 'Faddle',
	'foo' => 'fooooo',
	'bar' => 'baaaaar',
	'user' => array(
			'name' => 'seo',
			'role' => 'm'
		),
	'users' => array(
			'Name_1',
			'Name_2'
		)
	
);

mstimer();

$view = new Faddle\View($config);


$content =  $view->show('hello', $args);

ob_start() and ob_clean();

echo $content;
ob_end_flush();
