<?php namespace Faddle\View;

use Faddle\Helper\TextUtils as TextUtils;
use Faddle\Helper\OneCacheHelper as OneCacheHelper;

/**
 * 模板引擎类
 * @author KYO
 * @since 2015-9-21
 */
class ViewEngine implements \IteratorAggregate {
	public static $default_extension='.html';
	public static $extends = array();
	public static $modifiers = array();
	
	private $_vars = array();
	private $_config = array();
	private $_trace = array();
	
	private $file;
	private $path = '';
	private $cache = false; //不建议这里开启缓存
	private $cache_path = '';
	private $cache_file = '';
	private $cacher;
	private $expire = 0;
	protected $content = '';
	
	public function __construct($config=array()) {
		$_vars = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
		
		if(!isset($config) && file_exists(ROOT_PATH.'/config/templates.xml')){
			//获取系统变量
			$_sxe = simplexml_load_file(ROOT_PATH.'/config/templates.xml');
			$_tags = $_sxe->xpath('/root/taglib');
			foreach ($_tags as $_tag) {
				$this->_config["{$_tag->name}"] = $_tag->value;
			}
		} else {
			//$this->_config = $config;
			$this->_config = $config + $this->_config;
		}
		if (empty(self::$modifiers)) $this->init_modifier();
		if (empty(self::$extends)) {
			ViewTemplate::helper('elapsed_time', function() {
				return mstimer(1);
			});
			self::extend(function($content) {
		 		return ViewTemplate::parse($content);
		 	});
		}
		
	}
	
	public static function make($file, $data=array(), $config=array()) {
		$self = new self($config);
		$self->file = $file;
		$self->assign($data);
		
		return $self;
	}
	
	/**
	 * 获取变量循环迭代器
	 * @return \ArrayIterator
	 */
	public function getIterator() {
		return $this->_vars->getIterator();
	}

	/**
	 * @param string|int $name
	 * @return mixed
	 */
	public function __get($name) {
		return $this->_vars[$name];
	}

	/**
	 * @param string|int $name
	 * @param mixed $value
	 */
	public function __set($name, $value) {
		$this->_vars[$name] = $value;
	}

	/**
	 * @param string|int $name
	 * @return bool
	 */
	public function __isset($name) {
		return isset($this->_vars[$name]);
	}

	// 接收要注入的变量
	public function assign($key, $value=null) {
		if (is_array($key) or ($key instanceof \Traversable)) {
			foreach ($key as $k => $v) {
				$this->_vars[$k] = $v;
			}
		} else if(isset($key) && !empty($key)) {
			$this->_vars[$key] = $value;
			
		}

		return $this;
	}
	
	public function config($config=null) {
		if (is_array($config)) {
			$this->_config = array_merge($this->_config, $config);
		}
		return $this->_config;
	}
	
	public function data() {
		return $this->_vars;
	}
	
	public function file() {
		return $this->file;
	}
	
	public function path() {
		if (array_key_exists('template_path', $this->_config))
			$this->path = $this->_config['template_path'];
		$this->path = rtrim($this->path, '/') . '/';
		return $this->path;
	}
	
	public function cache_path() {
		if (array_key_exists('cache_path', $this->_config))
			$this->cache_path = $this->_config['cache_path'];
		$this->cache_path = rtrim($this->cache_path, '/') . '/';
		return $this->cache_path;
	}
	
	public function expire() {
		if (array_key_exists('expire', $this->_config))
			$this->expire = $this->_config['expire'];
		return $this->expire;
	}
	
	public function cache() {
		if (array_key_exists('cache', $this->_config))
			$this->cache = $this->_config['cache'];
		return $this->cache;
	}
	
	public static function exists($file, $path='', $suffix='') {
		if (file_exists($file)) return $file;
		$ext = (! empty($suffix)) ? $suffix : '.view.php';
		$_ext = pathinfo($file, PATHINFO_EXTENSION);
		if (! empty($_ext)) $_ext = '.'.ltrim($_ext, '.');
		$exts = (array) $ext;
		foreach ($exts as $ext) {
			if (! empty($_ext)) {
				if (starts_with($ext, $_ext))
					$ext = str_ireplace($_ext, '', $ext);
			}
			$file = $path . $file . $ext;
			if (file_exists($file)) break;
		}
		if (! file_exists($file))
			return false;
		else
			return $file;
	}
	
	public function real_file($file) {
		if ($file != null)
			$this->file = $file;
		if (empty($this->file)) return false;
		if ($file = self::exists($this->file, $this->path(), $this->_config['suffix'])) {
			$this->file = $file;
			return true;
		} else {
			return false;
		}
	}
	
	public function fetch($file=null, $include=true) {
		if (! $this->real_file($file)) {
			return false;
		}
		
		if ($include) {
			ob_start() and ob_clean();     // Start output buffering
			extract($this->data());         // Extract the vars to local namespace
			include $this->file;           // Include the file
			$contents = ob_get_contents(); // Get the contents of the buffer
			ob_end_clean();                // End buffering and discard
		} else {
			$contents = file_get_contents($this->file);
		}
		
		return $contents;              // Return the contents
	}
	
	public function render($file=null, $onlyparse=false) {
		$this->_trace['begin'] = microtime();

		// 读取模板文件
		if (! $this->real_file($file)) {
			trigger_error(sprintf('未找到模板文件：%s', $file), E_USER_WARNING);
			return false;
		}
		
		if ($this->cache()) {
			$cached = $this->load_from_cache();
			if ($cached) {
				$this->_trace['end'] = microtime();
				$this->content = $cached;
				return $cached;
			}
		}
		
		$contents = $this->fetch($this->file, false);
		
		// 载入模板解析类
		$parser = new ViewParser($contents, $this);
		
		$contents = $parser->parse();

		if (! $onlyparse) {
			$contents = $this->padding($contents);
		}

		if ($this->cache()) {
			if ($this->cacher) {
				OneCacheHelper::save($this->cache_file, $contents, $this->cacher);
			} else {
				// 获取缓冲区内的数据，并且创建缓存文件
				file_put_contents($this->cache_file, $contents);
			}
		}
		
		$this->_trace['end'] = microtime();
		
		$this->content = $contents;
		return $contents;
	}
	
	public function padding($contents, $isfile=false) {
		ob_start() and ob_clean();
			
		extract($this->data(), EXTR_OVERWRITE);
		
		try {
			if ($isfile)
				include $contents;
			else
				eval('?>'.$contents);
		} catch (\Exception $e) {
			ob_end_clean(); throw $e;
		}
		
		return ob_get_clean();
	}
	
	public function load_from_cache() {
		if (! is_file($this->file)) return false;
		$cached = false;
		// 生成缓存文件，仅保存经过PHP解析度文件
		$this->cache_file = $this->cache_path().md5($this->file).'';
		// 判断是否存在缓存文件
		if (file_exists($this->cache_file)) {
			if (filemtime($this->cache_file) >= filemtime($this->file)) {
				$contents = file_get_contents($this->cache_file);
				$cached = true;
			}
		} else {
			// 建立缓存驱动
			//if (! $this->cacher) $this->cacher = new SaeKVHelper();
			$data = OneCacheHelper::load($this->cache_file, $this->cacher, filemtime($this->file));
			if ($data and !empty($data)) {
				$contents = $data;
				$cached = true;
			}
		}
		if ($cached) return $contents;
		else return false;
	}
	
	public static function extend_modifier($name, $func) {
		if (empty($name))
			return false;
		if (!is_callable($func))
			return false;
		self::$modifiers[$name] = $func;
		return true;
	}
	
	public static function call_modifier() {
		$args = func_get_args();
		$name = trim($args[0]);
		if (empty($name)) {
			return false;
		}
		$func =  self::$modifiers[strtolower($name)];
		if (empty($func) ) {
			if (function_exists($name)) {
				$func = $name;
			} else if (method_exists(TextUtils::class, $name)) {
				$func = array(TextUtils::class, $name);
			} else  {
				return false;
			}
		}
		try {
			$ret = call_user_func_array($func, array_slice($args,1));
		} catch(\Exception $e) {
			trigger_error(sprintf('过滤器[%s]解析出错：%s', $name, $e->getMessage()), E_USER_WARNING);
			return false;
		}
		return $ret;
	}
	
	public static function extend(\Closure $func) {
		if (!is_callable($func))
			return false;
		self::$extends[] = $func;
		return true;
	}
	
	public static function modifier_exists($name) {
		$name = trim($name);
		return (array_key_exists(strtolower($name), self::$modifiers) 
				or function_exists($name) 
				or method_exists(TextUtils::class, $name));
	}
	
	private function init_modifier() {
		self::extend_modifier('upper', function($input) {
			if (!is_string($input)) return $input;
			return strtoupper($input);
		});
		self::extend_modifier('lower', function($input) {
			if (!is_string($input)) return $input;
			return strtolower($input);
		});
		self::extend_modifier('capitalize', function($input) {
			if (!is_string($input)) return $input;
			return ucwords($input);
		});
		self::extend_modifier('truncate', function($input, $len) {
			if (empty($len)) return $input;
			$len = intval($len);
			return TextUtils::usubstr($input, $len);
		});
		self::extend_modifier('len', function($input) {
			if (is_array($input))
				return count($input);
			elseif (is_string($input))
				return mb_strlen($input, 'utf-8');
			else
				return 1;
		});
		self::extend_modifier('trims', function($input) {
			if (is_string($input)) return trim($input);
			if (is_array($input)) {
				foreach ($input as $k => $v) {
					if (empty($v)) unset($input[$k]);
				}
			}
			return $input;
		});
		self::extend_modifier('safe', function($input) {
			return htmlentities($input, ENT_QUOTES);
		});
		self::extend_modifier('strip', function($input) {
			if (!is_string($input)) return $input;
			return strip_tags($input);
		});
		self::extend_modifier('last_key', function($input) {
			if (!is_array($input)) return $input;
			return current(array_reverse(array_keys($input)));
		});
		self::extend_modifier('last_value', function($input) {
			if (!is_array($input)) return $input;
			return current(array_reverse($input));
		});
		self::extend_modifier('call', function($input, $sub) {
			if (is_callable($input)) return call_user_func($input, $sub);
			elseif (is_array($input)) return $input[$sub];
			elseif (is_object($input) and method_exists($input, $sub))
				return call_user_func(array($input, $sub), array_slice(func_get_args(), 2));
			return ($input);
		});
		
		$arr = array('eq','neq','lt','gt','lte','gte','true','false');
		foreach ($arr as $a) {
			self::extend_modifier($a, function() use ($a) {
				$args = func_get_args();
				array_unshift($args, $a);
				return call_user_func_array(array(self, 'modify_if'), $args);
			});
		}
		
	}
	
	private static function modify_if($operator, $input, $condition, $true_val, $false_val=false) {
		if (! isset($true_val)) {
			$true_val = $input;
		}
		switch ($operator) {
			case '':
			case '==':
			case '=':
			case 'eq':
			default:
				$operator="==";
				break;
			case '<':
			case 'lt':
				$operator="<";
				break;
			case '>':
			case 'gt':
				$operator=">";
				break;
			case '<=':
			case 'lte':
				$operator="<=";
				break;
			case '>=':
			case 'gte':
				$operator=">=";
				break;
			case '!=':
			case 'neq':
				$operator = "!=";
				break;
			case 'true':
				$operator = '==';
				$true_val = $condition;
				$false_val = false;
				$condition = true;
				break;
			case 'false':
				$operator = '==';
				$true_val = $condition;
				$false_val = true;
				$condition = false;
				break;
		}
		$ret = $input;
		if (eval('return ("'.$input.'" '.$operator.' "'.$condition.'");')) {
			$ret = $true_val;
		} else {
			$ret = $false_val;
		}
		return $ret;
	}
	
	/*
	 *清理缓存的视图模板文件
	 *@param null $path
	 */
	public function clean($path=null) {
		if ($path == null) {
			$path = $this->cache_path();
			$path = glob($path . '.*');
		} else {
			$path = $this->cache_path().md5($path) . '';
		}
		foreach ((array) $path as $file) {
			unlink($file);
		}
	}
	
}

