<?php namespace Faddle\View;

use Faddle\View as View;
use Faddle\View\ViewEngine as ViewEngine;

/**
 * 模板解析类
 * @author KYO
 * @since 2015-9-21
 */
class ViewParser {
	private $engine = null;
	private $content = '';
	private $_prefix = '\{\{[\s]{0,}';
	private $_suffix = '[\s]{0,}\}\}';
	private $_prefix_2 = '\{\%[\s]{0,}';
	private $_suffix_2 = '[\s]{0,}\%\}';
	private $_prefix_3 = '\{#[\s]{0,}';
	private $_suffix_3 = '[\s]{0,}#\}';
	private $_patten = [];
	private $_match = [];
	private $_patten_all = '';
	private $_match_all = '';
	private $_patten_extends = '';
	private $_match_extends = '';
	private $_patten_yield = '';
	private $_patten_section = '';
	private $_patten_literal = '';
	private $_match_literal = '';
	private $_patten_comment = '';
	
	private $_patten_import = '';
	private $_patten_macro = '';
	private $_patten_filter = '';
	
	private static $sections = array();
	private static $literals = array(); //保存原样显示区块内容
	public static $gen_get = false; //开关
	private $macros = array();
	
	public function __construct($contents, $engine=null) {
		if ($engine instanceof ViewEngine)
			$this->engine = $engine;
		$this->content = $contents;
		$this->init();
	}
	
	private function init() {
		$_prefix = & $this->_prefix;
		$_suffix = & $this->_suffix;
		$_prefix_2 = & $this->_prefix_2;
		$_suffix_2 = & $this->_suffix_2;
		$_patten = & $this->_patten;
		$_match = & $this->_match;
		
		$_patten[] = '/' .$_prefix. 'if\s+(.*)' .$_suffix. '/isU';
		$_match[] = "<?php if ($1) {?>";
		
		$_patten[] = '/' .$_prefix. 'else' .$_suffix. '/i';
		$_match[] = "<?php } else {?>";
		
		$_patten[] = '/' .$_prefix. 'end[\s]{0,}[\w]+' .$_suffix. '/i';
		$_match[] = "<?php }?>";
		
		$_patten[] = '/' .$_prefix. 'else[\s]{0,}if\s+(.*)' .$_suffix. '/isU';
		$_match[] = "<?php } elseif ($1) {?>";
		
		$_patten[] = '/' .$_prefix. 'foreach\s+\$([\w\_\-\>\:\d]+)[\s]{0,}\([\$]{0,1}([\w\_\d]+),[\s]{0,}[\$]{0,1}([\w\_\d]+)\)' .$_suffix. '/isU';
		$_match[] = "<?php foreach(\$$1 as \$$2=>\$$3) { ?>";
		
		$_patten[] = '/' .$_prefix. '(?:(foreach.*)|(for.*)|(switch.*)|(while.*)|(do.*))' .$_suffix. '/isU';
		$_match[] = "<?php $1 { ?>";
		
		$_patten[] = '/' .$_prefix. 'include\s+[\"|\']([\w\.\-\_\d]+)[\"|\']' .$_suffix. '/i';
		$_match[] = "<?php include '$1';?>";
		
		//【注意】: 使用 ([\s\S]+) 比  (.*) 更耗时。
		$_patten[] = '/'.$_prefix. '(\$[\w\_\-\>\:\d]+)(?:\s(.+)?|\s*?)[\s]{1,}or[\s]{1,}(.+)?' .$_suffix. '/i';
		$_match[] = "<?php if ($1 $2) { echo $1; } else { echo $3; }?>";
		
		$_patten[] = '/[ \t]{0,}'.$_prefix. '(\$.+?)' .$_suffix. '/';
		$_match[] = "<?php echo $1;?>";
		
		$_patten[] = '/<!--\{([\w]+)\}-->/';
		$_match[] = "<?php echo \$GLOBALS['$1'];?>";
		
		
		$this->_patten_all = '/'.$_prefix. '(.+?)' .$_suffix. '/';
		$this->_match_all = "<?php $1;?>";
		
		$this->_patten_extends = '/' .$_prefix_2. 'extends\s+file=\"([\w\/\.\-\_\d]+)\"(?:\s+as=\"(.*)\"|)' .$_suffix_2. '/iU';
		$this->_match_extends = '/<!--[\s]{0,}' .$_prefix_2. '#([\w\/\.\-\_\d]+)' .$_suffix_2. '[\s]{0,}-->/';
		
		$this->_patten_include = '/[ \t]{0,}' .$_prefix_2. 'include\s+file=\"([\w\/\.\-\_\d]+)\"(?:\s+as=\"(.*)\"|)(?:\s+with=(\{.*\})|)(?:\s+(only)|)' .$_suffix_2. '/iU';
		$this->_match_include = '';
		
		$this->_patten_yield = '/[ \t]{0,}' .$_prefix_2. 'yield\s+name=\"([\w\/\.\-\_\d]+)\"' .$_suffix_2. '/i';
		$this->_patten_section = '/' .$_prefix_2. 'section\s+name=\"([\w\/\.\-\_\d]+)\"' .$_suffix_2. '([\s\S]*?)' .$_prefix_2. 'endsection' .$_suffix_2. '/i';
		
		$this->_patten_literal = '/' .$_prefix_2. 'literal(?:\s+as=\"(.*)\"|)' .$_suffix_2. '([\s\S]*?)' .$_prefix_2. 'endliteral' .$_suffix_2. '/i';
		$this->_match_literal = '/' .$_prefix_2. '(\$.+?)' .$_suffix_2. '/';
		
		$this->_patten_comment = '/' .$this->_prefix_3. '(.*)' .$this->_suffix_3. '/U';
		
		$this->_patten_import = '/' .$_prefix_2. 'import\s+file=\"([\w\/\.\-\_\d]+)\"\s+as=\"([\w\_\d]+)\"' .$_suffix_2. '/iU';
		$this->_patten_macro = '/' .$_prefix_2. 'macro\s+([\w\_\d]+)\((?:(.+)?|)\)' .$_suffix_2. '([\s\S]*?)' .$_prefix_2. 'endmacro' .$_suffix_2. '/i';
		$this->_patten_filter = '/' .$_prefix. '(.*?)\|(\w+)(?:\:[\(]{0,1}(.*?)[\)]{0,1}|)?(?:(\|.*)|)' .$_suffix. '/';
		
	}
	
	/**
	 * 导入宏内容
	 */
	public static function import($body) {
		if (empty($body)) return [];
		$self = new self($body);
		$self->parse_macro();
		
		return $self->macros;
	}
	
	/**
	 * 解析模板内容
	 */
	public function parse($content=null) {
		if (! empty($content)) {
			$this->content = $content;
		}
		if (empty($this->content)) {
			trigger_error('未指定需要解析的视图内容。', E_USER_WARNING);
			return '';
		}
		//解析原样显示内容标识
		$this->parse_literal();
		//解析模板注释内容标识
		$this->parse_comment();
		//解析模板区块内容标识
		$this->parse_section();
		//解析续承模板内容标识
		$this->parse_extends();
		//解析包含模板内容标识
		$this->parse_include();
		//解析区块位置内容标识
		$this->parse_yield();
		//解析引入宏文件内容标识
		$this->parse_import();
		//解析文本过滤函数内容标识
		$this->parse_filter();
		//解析PHP处理内容标识
		$this->parse_content();
		//解析扩展处理内容标识
		$this->parse_all();
		
		//unset($sections);
		//if ($this->content) $this->content = ltrim($this->content);
		return $this->content;
	}
	
	/**
	 * 用于最后还原 literal 标识内容，需开启 $gen_get=true 并经过 parse() 解析。
	 * @param string $content
	 * @return string
	 */
	public function gen_get($content) {
		if (preg_match_all($this->_match_literal, $content, $matchs, PREG_SET_ORDER)) {
			foreach ($matchs as $match) {
				$name = $match[1];
				$match = '/' . preg_quote($match[0], '/') . '/';
				if (array_key_exists($name, self::$literals))
					$content = preg_replace($match, self::$literals[$name], $content);
				else
					$content = preg_replace($match, '<!-- [literal error] : '.$name.' -->', $content);
			}
		}
		
		return $content;
	}
	
	private function parse_extends() {

		if (preg_match($this->_patten_extends, $this->content, $math)) {
			$this->content = preg_replace($this->_patten_extends, '', $this->content);
			//var_dump($math);
			if ($this->engine != null) $data = $this->engine->data();
			$temp = $math[1];
			$as = $math[2] ? $math[2] : '_vacancy';
			$math = '/' . preg_replace('/\//', '\/', $math[0]) . '/';
			$data[$as] = $this->content;
			$content = View::make(  $temp, $data );
			
			if ($content) {
				if (preg_match($this->_match_extends, $content, $math)) {
					$this->content = preg_replace($this->_match_extends, $this->content, $content);
				} else {
					$content = $content ."\r\n". $this->content ."\r\n";
					$this->content = $content;
				}
			} else {
				$this->content = preg_replace($math, '<!-- [extends failed] : '.$math.' -->', $this->content);
			}
		}
		
		return $this->content;
	}
	
	private function parse_include() {

		if (preg_match_all($this->_patten_include, $this->content, $maths, PREG_SET_ORDER)) {
			
			foreach ($maths as $math) {
				if ($this->engine != null) $data = $this->engine->data();
				$temp = $math[1];
				$as = (count($math)>2) ? $math[2] : '_spaceholder';
				$with = (count($math)>3) ? json_decode($math[3], true) : [];
				$only = (count($math)>4) ? true : false;
				$math = '/' . preg_replace(['/\//', '/\$/', '/\./'], ['\/', '\$', '\.'], $math[0]) . '/';
				if ($only)
					$data = $with;
				else
					$data = array_merge($data, $with);
				$content = View::make( $temp , $data );
				//var_dump($data);
				if ($content) {
					$this->content = preg_replace($math, $content, $this->content);
					extract(array(
							$as => $content
					), EXTR_OVERWRITE);
					
				} else {
					$this->content = preg_replace($math, '<!-- [include error] : '.$math.' -->', $this->content);
				}
			}
		}
		
		return $this->content;
	}
	
	private function parse_section() {
		
		if (preg_match_all($this->_patten_section, $this->content, $maths, PREG_SET_ORDER)) {
			foreach ($maths as $math) {
				$name = $math[1];
				$content = $math[2] ? $math[2] : '';
		
				//var_dump($content);
		
				self::$sections[$name] = $content;
			}
		
			//extract($sections, EXTR_OVERWRITE);
		}
		
		$this->content = preg_replace($this->_patten_section, '', $this->content);
		return $this->content;
	}
	
	private function parse_yield() {
		
		if (preg_match_all($this->_patten_yield, $this->content, $matchs, PREG_SET_ORDER)) {
			$yields = array();
			foreach ($matchs as $match) {
				$name = $match[1];
				$match = '/' . preg_quote($match[0], '/') . '/';
				$this->content = preg_replace($match, self::$sections[$name], $this->content);
			}
			$this->content = preg_replace($this->_patten_yield, '', $this->content);
		}
		
		return $this->content;
	}
	
	/**
	 * 解析原样显示标识，未开启 gen_get 时仅能正确解析主内容模板，
	 * 开启后需调用 gen_get() 获取还原内容。
	 * @return string
	 */
	private function parse_literal() {
	
		if (preg_match_all($this->_patten_literal, $this->content, $matchs, PREG_SET_ORDER)) {
			
			foreach ($matchs as $match) {
				if (! empty($match[1])) {
					$as = $match[1];
				} else {
					$as = '_literal'. rand();
				}
				$content = trim($match[2], "\r\n");
				$match = $match[0];
				if (self::$gen_get == false) {
					if ($this->engine != null) $this->engine->$as = $content;
					$to = '<?php echo $'. $as .' ;?>';
				} else {
					self::$literals[$as] = $content; //静态保存并留在最后页面生成时解析
					$to = ''.$this->_prefix_2. '$'.$as .$this->_suffix_2.'';
				}
				$this->content = str_replace($match, $to, $this->content);
			}
			
			$this->content = preg_replace($this->_patten_literal, '', $this->content);
		}
		
		return $this->content;
	}
	
	private function parse_import() {
		if (preg_match_all($this->_patten_import, $this->content, $matchs, PREG_SET_ORDER)) {
			
			foreach ($matchs as $match) {
				$name = $match[1];
				$as = $match[2];
				$config = ($this->engine) ? $this->engine->config() : [];
				$engine = $this->engine;
				$ve = new ViewEngine($config);
				$body = $ve->fetch($name, false);
				$macros = ViewParser::import($body);
				//var_dump($macros);
				//json_decode(json_encode(self::$macros), true);
				$obj = new ViewMacro();
				$self = & $this;
				foreach ($macros as $key => $val) {
					$obj->$key = function() use ($val) {
						$args = combine_arr(array_keys($val[0]), func_get_args()[0]);
						$args = array_merge($val[0], $args);
						$body = $this->parse($val[1]);
						
						ob_start() and ob_clean();
						extract($args, EXTR_OVERWRITE);
						
						try {
							eval('?>'.$body);
						} catch (\Exception $e) {
							ob_end_clean(); throw $e;
						}
						
						$body = ob_get_clean();
						//echo "\r\n|".$body."|\r\n";
						return $body;
					};
					
				}

				if ($engine) $engine->$as = $obj;
			}
			
			$this->content = preg_replace($this->_patten_import, '', $this->content);
		}
	
		return $this->content;
	}
	
	private function parse_macro() {
		if (preg_match_all($this->_patten_macro, $this->content, $matchs, PREG_SET_ORDER)) {
			
			foreach ($matchs as $match) {
				$name = $match[1];
				$params = $match[2];
				$body = $match[3];
				$params = explode(',', $params);
				$_params = [];
				foreach ($params as $param) {
					$param = trim($param);
					$param = explode('=', $param);
					$param[0] = ltrim(trim($param[0], " \0\"\'\r\n"), '$');
					if (count($param) == 2) $param[1] = trim($param[1], " \0\"\'\r\n");
					else $param[1] = null;
					$_params[$param[0]] = $param[1];
				}
				//$params = array_flip($params);
				$this->macros[$name] = [$_params, $body];
			}
				
			$this->content = preg_replace($this->_patten_macro, '', $this->content);
		}
	
		return $this->content;
	}
	
	private function parse_comment() {
		$this->content = preg_replace($this->_patten_comment, '', $this->content);
		
		return $this->content;
	}
	
	private function parse_content() {
		
		$this->content = preg_replace($this->_patten, $this->_match, $this->content);
		
		return $this->content;
	}
	
	private function parse_all() {
		if (! empty(ViewEngine::$extends)) {
			
			if (preg_match_all($this->_patten_all, $this->content, $matchs, PREG_SET_ORDER)) {
				
				foreach ($matchs as $match) {
					$all = $match[1];
					$match = '/' . preg_quote($match[0], '/') . '/';
					foreach (ViewEngine::$extends as $extend) {
						$rc = call_user_func($extend, $all);
						if ($rc == $all or $rc == false) continue;
						$this->content = preg_replace($match, $rc, $this->content);
						
					}
					
				}
				
			}
			
		}
		
		$this->content = preg_replace($this->_patten_all, $this->_match_all, $this->content);
		
		return  $this->content;
	}
	
	private function parse_filter() {
		
		$this->content = preg_replace_callback($this->_patten_filter, function($match) {
			list($_, $args, $name, $params, $mods) = $match;
			$name = trim($name);
			if (! empty(ViewEngine::$modifiers) and ViewEngine::modifier_exists($name)) {
				//eval("\$args = \"$args\";");
				$params = $args . (empty($params) ? '' : ',' . $params);
				$ov = '\\Faddle\\View\\ViewEngine::call_modifier(\''.$name.'\','. $params .')';
				if (! empty($mods)) {
					$mods = addcslashes($mods, '\'');
					$mods = preg_replace('/\|(\w+)(?:\:[\(]{0,1}([^\)\|]+)[\)]{0,1}|)/u', '\'$1\'=>\'$2\',', $mods);
					$mods = substr($mods, 0, -1);
					try {
						eval("\$mods = array($mods);");
						foreach ($mods as $name => $params) {
							$name = trim($name);
							$params = $ov . (empty($params) ? '' : ',' . $params);
							$ov = '\\Faddle\\View\\ViewEngine::call_modifier(\''.$name.'\','. $params .')';
						}
					} catch (\Exception $e) {
						trigger_error("模板过滤器[". var_export($mods, true)."]\r\n".sprintf('解析出错：%s', $e->getMessage())
								, E_USER_WARNING);
					}
				}
				
				return '<?php try{ echo @'.$ov.'; } catch(\Exception $e) { '
					. 'echo "<!-- [filter error] : {$e->getMessage()} -->"; '
					.'trigger_error(sprintf(\'模板过滤器解析出错：%s\', $e->getMessage()), E_USER_WARNING); } ?>';
				
			} else {
				return "<?php echo $args; ?>";
			}
			
		}, $this->content);
	
		
		return  $this->content;
	}
	
}

