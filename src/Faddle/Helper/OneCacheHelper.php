<?php namespace Faddle\Helper;

/**
 * 数据缓存类
 */
class OneCacheHelper {
	const CONFIG_EXIT = "[CACHEFILE-1.00]\r\n";
	public $driver = null;
	public $expire = 0;
	private $data = [];
	private $file;

	function __construct($file, $driver=null, $expire=0) {
		$this->$driver = $driver;
		$this->$expire = $expire;
		$this->file = $file;
		$data = self::load($file, $this->$driver);
		if ($data) $this->data =$data;
	}

	public function _save() {
		return self::save($this->file, $this->data, $this->driver, $this->expire);
	}
	
	/**
	 * 重置所有数据；不传参数代表清空数据
	 */
	public function reset($list = array()) {
		$this->data = $list;
		$this->_save();
	}

	/**
	 * 添加一条数据，不能重复；如果已存在则返回false;1k次/s
	 */
	public function add($k, $v) {
		if (!isset($this->data[$k])) {
			$this->data[$k] = $v;
			$this->_save();
			return true;
		}
		return false;
	}

	/**
	 * 获取数据;不存在则返回false;100w次/s
	 * $k null 不传则返回全部;
	 * $k string 为字符串；则根据key获取数据，只有一条数据
	 * $search_value 设置时；表示以查找的方式筛选数据筛选条件为 $key=$k 值为$search_value的数据；多条
	 */
	public function get($k = '', $v = '', $search_value = false) {
		if ($k === '')
			return $this->data;
		
		$search = array();
		if ($search_value === false) {
			if (is_array($k)) {
				// 多条数据获取
				$num = count($k);
				for ($i = 0; $i < $num; $i++) {
					$search[$k[$i]] = $this->data[$k[$i]];
				}
				return $search;
			} else if (isset($this->data[$k])) {
				// 单条数据获取
				return $this->data[$k];
			}
		} else {
			// 查找内容数据方式获取；返回多条
			foreach ($this->data as $key => $val) {
				if ($val[$k] == $search_value) {
					$search[$key] = $this->data[$key];
				}
			}
			return $search;
		}
		return false;
	}

	/**
	 * 更新数据;不存在;或者任意一条不存在则返回false;不进行保存
	 * $k $v string 为字符串；则根据key只更新一条数据
	 * $k $v array array($key1,$key2,...),array($value1,$value2,...)
	 * 则表示更新多条数据
	 * $search_value 设置时；表示以查找的方式更新数据中的数据
	 */
	public function update($k, $v, $search_value = false) {
		if ($search_value === false) {
			if (is_array($k)) {
				// 多条数据更新
				$num = count($k);
				for ($i = 0; $i < $num; $i++) {
					$this->data[$k[$i]] = $v[$i];
				}
				$this->_save();
				return true;
			} else if (isset($this->data[$k])) {
				// 单条数据更新
				$this->data[$k] = $v;
				$this->_save();
				return true;
			}
		} else {
			// 查找方式更新；更新多条
			foreach ($this->data as $key => $val) {
				if ($val[$k] == $search_value) {
					$this->data[$key][$k] = $v;
				}
			}
			$this->_save();
			return true;
		}
		return false;
	}

	/*
	 * 替换方式更新；满足key更新的需求
	 */
	public function replace_update($key_old, $key_new, $value_new) {
		if (isset($this->data[$key_old])) {
			$value = $this->data[$key_old];
			unset($this->data[$key_old]);
			$this->data[$key_new] = $value_new;
			$this->_save();
			return true;
		}
		return false;
	}

	/**
	 * 删除;不存在返回false
	 */
	public function delete($k, $v = '', $search_value = false) {
		if ($search_value === false) {
			if (is_array($k)) {
				// 多条数据更新
				$num = count($k);
				for ($i = 0; $i < $num; $i++) {
					unset($this->data[$k[$i]]);
				}
				$this->_save();
				return true;
			} else if (isset($this->data[$k])) {
				// 单条数据删除
				unset($this->data[$k]);
				$this->_save();
				return true;
			}
		} else {
			// 查找内容数据方式删除；删除多条
			foreach ($this->data as $key => $val) {
				if ($val[$k] == $search_value) {
					unset($this->data[$key]);
				}
			}
			$this->_save();
			return true;
		}
		return false;
	}

	/**
	 * 排序
	 */
	public static function arr_sort(&$arr, $key, $type = 'asc') {
		$keysvalue = $new_array = array();
		foreach ($arr as $k => $v) {
			$keysvalue[$k] = $v[$key];
		}
		if ($type == 'asc') {
			asort($keysvalue);
		} else {
			arsort($keysvalue);
		}
		reset($keysvalue);
		foreach ($keysvalue as $k => $v) {
			$new_array[$k] = $arr[$k];
		}
		return $new_array;
	}

	/**
	 * 加载数据；并解析成程序数据
	 */
	public static function load($file, $driver=null, $ftime=0) { // 10000次需要4s 数据量差异不大。
		if (is_null($driver)) {
			if (!file_exists($file))
				touch($file);
			$str = file_get_contents($file);
		} else if (is_object($driver)) {
			if (method_exists($driver, 'get')) {
				$str = $driver->get($file);
			}
		}
		if (! $str) return false;
		$str = substr($str, strlen(CONFIG_EXIT));
		$stored = unserialize($str);
		if ($stored['expire'] > 0) {
			//$this->expire = $stored['expire'];
			if (time() > $stored['mtime'] + $stored['expire']) {
				//$this->reset();
				return false;
			}
		} else if ($ftime > 0) {
			if ($ftime > $stored['mtime']) {
				//文件已经过期
				return false;
			} else {
				return $stored['data'];
			}
		}
		$str = $stored['data'];
		$data = json_decode($str, true);
		if (is_null($data))
			$data = array();
		
		return $data;
	}

	/**
	 * 保存数据；
	 */
	public static function save($file, $data, $driver=null, $expire=0) { // 10000次需要6s
		if (!$file) return false;
		//if (intval($expire) <= 0) $expire = $this->expire;
		if (is_array($data)) $data = json_encode($data);
		$contents = array(
				'mtime'		=> time(),
				'expire'	=> intval($expire),
				'data'		=> $data
		);
		$contents = CONFIG_EXIT . serialize($contents);
		if (is_null($driver)) {
			if ($fp = fopen($file, "w")) {
				if (flock($fp, LOCK_EX)) { // 进行排它型锁定
					$str = $contents;
					fwrite($fp, $str);
					fflush($fp); // flush output before releasing the lock
					flock($fp, LOCK_UN); // 释放锁定
				}
				fclose($fp);
				return true;
			}
		} else if (is_object($driver)) {
			$ret = false;
			if (method_exists($driver, 'set')) {
				$ret = $driver->set($file, $contents);
			}
			if ($ret==false and method_exists($driver, 'put')) {
				$ret = $driver->put($file, $contents);
			}
			if ($ret==false and method_exists($driver, 'add')) {
				$ret = $driver->add($file, $contents);
			}
			return $ret;
		}
	}
}