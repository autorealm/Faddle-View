<?php namespace Faddle;

use Faddle\View\ViewEngine as ViewEngine;
use Faddle\Helper\FileCache as StorageHelper;

/**
 * 视图类
 * @author KYO
 * @since 2015-9-21
 */
class View {
	protected static $config = array();
	public static $extends = array();
	private $data = array();
	private $path;
	private $engine;
	private $engine_config = array();
	public $cache = false;
	public $storage;
	
	public function __construct($config=array()) {
		if (! self::$config or empty(self::$config)) {
			self::$config = array();
			self::$config['default'] = array(
					'suffix'  => ['.view.php', '.faddle.php', '.tpl.html'],
					'template_path'  =>  '/views', //模板文件夹路径
					'storage_path'  =>  'cache', //模板文件夹缓存路径
					'bucket' => 'templates',
					'engine'  =>  'default', //模板引擎名称
					'expire'  =>  5000, //过期时间（秒）
			);
			self::$config['nature'] = array(
					'suffix'  => '.php',
					'template_path'  =>  '/views',
					'storage_path'  =>  'cache',
					'bucket' => 'templates',
					'engine'  =>  false,
					'expire'  =>  0,
			);
		}
		$config = (array) $config;
		$isdis = false; //判断是否是二维数组
		foreach($config as $c) {
			if (is_array($c)) $isdis = true;
			break;
		}
		if (! $isdis) $_config['engine'] = $config;
		else $_config = $config;
		self::$config = array_merge(self::$config, $_config);
		
		$this->storage = StorageHelper::make();
		
		
	}
	
	protected function init_engine($engine) {
		if (! is_array($engine)) {
			$this->engine = null;
			return;
		}
		$this->engine_config = $engine;
		$bucket = (array_key_exists('bucket', $engine)) ? $engine['bucket'] : 'views';
		if ($this->storage) $this->storage->with($bucket, false);
		if ($engine['engine']) {
			$this->engine = strtolower($engine['engine']);
		} else {
			$this->engine = null;
		}
	}
	
	public function __get($name) {
		return $this->data[$name];
	}
	
	public function __set($name, $value) {
		$this->data[$name] = $value;
	}
	
	public static function make($template, $data=array(), $config=array()) {
		$view = new self($config);
		
		return $view->show($template, $data);
	}
	
	public function show($template, $data=array()) {
		list($engine, $file) = $this->path($template, false);
		if ($engine) $engine = self::$config[$engine];
		else $engine = null;
		$this->init_engine($engine);
		
		$this->data = array_merge($this->data, $data);
		$data = $this->data;
		$this->path = $file;
		//echo $template; var_dump($this->path);
		
		if ($this->path == false) {
			if (starts_with($template, ['http:', 'https:'])) {
				$this->path = $template;
				return file_get_contents($template);
			}
			
			trigger_error(sprintf('模板加载出错：%s', $template), E_USER_WARNING);
			return false;
		}
		
		$cache = (array_key_exists('cache', $this->engine_config)) ? $this->engine_config['cache'] : $this->cache;
		if ($cache) {
			$compiled_path = $this->compiled();
			if ($this->path and ! $this->expired()) {
				$output = $this->storage->get($compiled_path);
				if (! empty($output)) return $output;
			}
		}
		
		if (in_array($this->engine, array('default', 'faddle', 'view'))) {
			$view = ViewEngine::make( $this->path, $data );
			$view->config($engine);
			
			$output = $view->render();
			
		} else if (in_array($this->engine, array('text', 'html'))) {
			if (is_file($this->path)) {
				$output = file_get_contents($this->path);
			} else {
				$output = false;
			}
		} else if (array_key_exists(strtolower($this->engine), self::$extends)) {
			$func = self::$extends[strtolower($this->engine)];
			try {
				$output = call_user_func_array($func, array($this->path, $data));
			} catch(\Exception $e) {
				$output = false;
			}
			
		} else if ($this->engine) {
			
			var_dump(self);
			
		} else {
			ob_start() and ob_clean();
			
			include ($this->path);
			
			$output = ob_get_clean();
		}
		
		if ($cache and $output) $this->storage->put($compiled_path, $output);
		return $output;
	}
	
	public function display($template, $date=array()) {
		$content = $this->show($template, $date);
		if ($content === false) {
			exit();
		} else {
			echo $content;
			@ob_flush();
		}
	}
	
	public function path($path, $rte=true) {
		$_path = trim($path, DIRECTORY_SEPARATOR);
		if (strpos($_path, 'path: ') == 0) {
			$file = substr($_path, 6);
			if (file_exists($file)) {
				if ($rte) return $file;
				else return ['default', $file];
			}
		}
		foreach (self::$config as $name => $config) {
			$suffix = (array_key_exists('suffix', $config)) ? $config['suffix'] : '';
			$template_path = (array_key_exists('template_path', $config)) ? $config['template_path'] : '';
			$suffixs = (array) $suffix;
			foreach ($suffixs as $suffix) {
				if (! empty($ext = pathinfo($_path, PATHINFO_EXTENSION))) {
					$ext = '.'.$ext;
					if (starts_with($suffix, $ext))
						$suffix = str_ireplace($ext, '', $suffix);
				}
				$file = $template_path . DIRECTORY_SEPARATOR . $_path . $suffix;
				if (file_exists($file)) {
					if ($rte) return $file;
					else return [$name, $file];
				}
			}
		}
		
		return false;
		
	}
	
	public function compiled($path=null) {
		$storage_path = (array_key_exists('storage_path', $this->engine_config)) ? $this->engine_config['storage_path'] : 'views';
		if (! is_null($path))
			return $storage_path.'/'.md5($path);
		else
			return $storage_path.'/'.md5($_SERVER['REQUEST_URI']);
	}
	
	public function expired($path=null) {
		$expire = (array_key_exists('expire', $this->engine_config)) ? $this->engine_config['expire'] : '0';
		$expire = intval($expire);
		$time = $this->storage->time($this->compiled($path));
		//echo "\n{$time} : {$expire} : ".time();
		if ($expire > 0) {
			if (! $time) return true;
			if ($time + $expire < time()) return true;
			else if (is_null($path)) return false;
		}
		if (is_null($path)) {
			if ($expire <= 0) return false;
			else return true;
		}
		$ftime = filemtime($path);
		//echo " : {$ftime}\n";
		return $ftime > $time;
	}
	
	public static function extend($name, \Closure $func) {
		if (empty($name))
			return false;
		if (!is_callable($func))
			return false;
		$name = strtolower($name);
		self::$extends[$name] = $func;
		return true;
	}
	
}
