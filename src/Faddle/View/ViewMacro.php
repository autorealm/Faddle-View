<?php namespace Faddle\View;

/**
 * 视图宏类
 * @author KYO
 * @since 2015-10-14
 *
 */
class ViewMacro extends \stdClass {

	public function __call($method, $args) {
		if (isset($this->$method)) {
			$func = $this->$method;
			return $func($args);
		} else {
			return false;
		}
	}
	
	
}

