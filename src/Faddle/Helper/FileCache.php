<?php namespace Faddle\Helper;


class FileCache extends Cache {

	public function __construct($options=array()) {
		if(!empty($options)) {
			$this->options = $options;
		}
		
		if(substr($this->options['cachedir'], -1) != '/') $this->options['cachedir'] .= '/';
		
		$this->init();
	}

	public static function make() {
		$self = new self();
		$self->options = array(
			'prefix' => 't_',
			'expire' => 0,
			'length' => 0,
			'cachedir'   => '/',
		);
		return $self;
	}

	private function init() {
		// 创建应用缓存目录
		if (!empty($this->options['cachedir']) and !is_dir($this->options['cachedir'])) {
			mkdir($this->options['cachedir']);
		}
	}

	/**
	 * 获取完整缓存文件名
	 */
	private function cached_file_name($key) {
		if (file_exists(dirname($key))) return $key;
		$name = md5($key);
		$filename = $this->options['prefix'].$name.'.php';
		
		return $this->options['cachedir'].$filename;
	}

	/**
	 * 应用缓存目录
	 */
	public function with($dirname, $mk=true) {
		// 创建应用缓存目录
		$path = $this->options['cachedir'] . DIRECTORY_SEPARATOR . $dirname;
		if (!is_dir($path) and $mk) {
			mkdir($path);
		}
		$this->options['cachedir'] = $path;
	}
	
	/**
	 * 获取缓存
	 */
	public function get($key) {
		$filename = $this->cached_file_name($key);
		if (!file_exists($filename)) return false;
		if (!is_file($filename)) {
			return false;
		}
		
		$content = file_get_contents($filename);
		if (!$content) return false;
		
		$expire = (int)substr($content,8, 12);
		if ($expire != 0 && time() > filemtime($filename) + $expire) {
			//缓存过期删除缓存文件
			unlink($filename);
			return false;
		}
		if (DATA_CACHE_CHECK) { //数据校验
			$check = substr($content,20, 32);
			$datatype = (int)substr($content,52, 1);
			$content = substr($content,54, -3);
			if($check != md5($content)) {
				return false;
			}
		} else {
			$datatype = (int)substr($content,20, 1);
			$content = substr($content,22, -3);
		}
		if (DATA_CACHE_COMPRESS && function_exists('gzcompress')) { //数据压缩
			$content = gzuncompress($content);
		}
		
		if ($datatype != 1) {
			$content = unserialize($content);
		}
		
		return $content;
	}

	/**
	 * 设置缓存
	 */
	public function set($key, $value, $expire=null, $datatype=0) {
		if (is_null($expire)) {
			$expire =  $this->options['expire'];
		}
		
		$filename = $this->cached_file_name($key);
		//保存数据类型（0:普通类型，1:标准PHP数据类型）
		if ($datatype == 0) {
			$data = serialize($value);
		} else {
			$datatype = 1;
			$data = var_export($value, true);
		}
		
		if (DATA_CACHE_COMPRESS && function_exists('gzcompress')) { //数据压缩
			$data = gzcompress($data, 3);
		}
		
		if (DATA_CACHE_CHECK) { //数据校验
			$check = md5($data);
		} else {
			$check = '';
		}
		
		$data = "<?php\n//".sprintf('%012d', $expire).$check.$datatype.'\n'.$data."\n?>";
		$result = file_put_contents($filename, $data);
		if ($result) {
			if ($this->options['length'] > 0) { //缓存队列
				$this->queue($key);
			}
			clearstatcache();
			return true;
		} else {
			return false;
		}
		
	}

	public function put($key, $value, $expire=null, $datatype=0) {
		return $this->set($key, $value, $expire, $datatype);
	}

	public function time($key) {
		$filename = $this->cached_file_name($key);
		if (!file_exists($filename)) return false;
		return filemtime($filename);
	}

	public function delete($key) {
		return unlink($this->cached_file_name($key));
	}

	public function clear() {
		$path = $this->options['cachedir'];
		$files = scandir($path);
		if ($files) {
			foreach($files as $f) {
				$file = $path.$f;
				if ($f != '.' && $f != '..' && is_dir($file)){
					array_map('unlink', glob($file.'/*.*'));
				} elseif (is_file($file)){
					unlink($file);
				}
			}
			return true;
		}

		return false;
	}


}