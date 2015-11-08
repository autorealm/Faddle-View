<?php

if (! defined('FADDLE_PATH')) {
	define('FADDLE_PATH', (dirname(dirname(__FILE__))));
}
if ( ! defined('CRLF')) {
	define('CRLF', "\r\n");
}
if ( ! defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}


if (!function_exists('combine_arr')) {
	function combine_arr($a, $b) {
		$acount = count($a);
		$bcount = count($b);
		$size = ($acount > $bcount) ? $bcount : $acount;
		$a = array_slice($a, 0, $size);
		$b = array_slice($b, 0, $size);
		return array_combine($a, $b);
	}
	
}

if (!function_exists('starts_with')) {
	function starts_with($haystack, $needles) {
		foreach ((array) $needles as $needle) {
			if (strpos($haystack, $needle) === 0) return true;
		}
	
		return false;
	}
}

if (!function_exists('mstimer')) {
	/**
	 * 毫秒计时函数
	 * @param number $mode
	 * @return void|string
	 */
	function mstimer($mode=0) {
		static $t;
		if (! $mode) {
			$t = microtime();
			return;
		}
		$t1 = microtime();
		list($m0, $s0) = explode(' ', $t);
		list($m1, $s1) = explode(' ', $t1);
		return sprintf("%.3f ms",($s1+$m1-$s0-$m0)*1000);
	}
}

//spl_autoload_register('autoload');

function __autoload($class) {
	require_once(FADDLE_PATH.'/'.str_replace('\\', '/', ($class)).'.php');
}

