<?php namespace Faddle\Helper;

if ( ! defined('DATA_CACHE_COMPRESS')) {
	define('DATA_CACHE_COMPRESS', FALSE);
}
if ( ! defined('DATA_CACHE_CHECK')) {
	define('DATA_CACHE_CHECK', TRUE);
}

/**
 * 缓存类
 * 
 */
class Cache {
	protected $handler;
	protected $options = array(
			'prefix' => 't_',
			'expire' => 7200,
			'length' => 0,
			'cachedir'   => '',
		);

	public function connect($type='', $options=array()) {
		if(empty($type)) $type = 'File';
		$class = strpos($type,'Cache')? $type : ucwords(strtolower($type)).'Cache';
		//chdir('cache');
		if(class_exists($class))
			$cache = new $class($options);
		else
			throw new Exception(':'.$type);
		
		return $cache;
	}

	/**
	 * 获取缓存类实例
	 * 
	 */
	public static function getInstance($type='', $options=array()) {
		static $_instance = array();
		$guid = $type.to_guid_string($options);
		if (!isset($_instance[$guid])) {
			$obj = new Cache();
			$_instance[$guid] =	$obj->connect($type, $options);
		}
		
		return $_instance[$guid];
	}

	public function __get($key) {
		return $this->get($key);
	}

	public function __set($key, $value) {
		return $this->set($key, $value);
	}

	public function __delete($key) {
		$this->delete($key);
	}

	public function __clear() {
		return $this->clear();
	}

	public function options($key, $value) {
		if (isset($key) and isset($value))
			$this->options[$key] = $value;
		else if (isset($key)) {
			if (is_array($key))
				$this->options = (array)$key;
			else
				return $this->options[$key];
		}
		return  $this->options;
	}

	public function __call($method, $args) {
		//调用缓存类型自带的方法
		if(method_exists($this->handler, $method)) {
			return call_user_func_array(array($this->handler, $method), $args);
		} else {
			throw new Exception('类 '.__CLASS__.' : '.$method.' 方法不存在！');
			return false;
		}
	}

	/**
	 * 队列缓存
	 */
	protected function queue($key, $value) {
		if (!$value) {
			$value = array();
		}
		//进列
		if (!array_search($key, $value)) array_push($value, $key);
		if (count($value) > $this->options['length']) {
			//出列
			$key = array_shift($value);
			//删除缓存
			$this->delete($key);
		}
		
		return true;
	}
	
}


