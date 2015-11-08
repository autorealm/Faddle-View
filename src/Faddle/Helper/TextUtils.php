<?php namespace Faddle\Helper;

/**
 * 文本工具类
 */
class TextUtils {
	
	
	/**
	 * 获取指定长度的 utf8 字符串
	 *
	 * @param string $string
	 * @param int $length
	 * @param string $dot
	 * @return string
	 */
	public static function usubstr($string, $length, $dot = '...') {
		if (strlen($string) <= $length)
			return $string;
		
		$strcut = '';
		$n = $tn = $noc = 0;
		
		while ($n < strlen($string)) {
			$t = ord($string[$n]);
			if ($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
				$tn = 1;
				$n++;
				$noc++;
			} elseif (194 <= $t && $t <= 223) {
				$tn = 2;
				$n += 2;
				$noc += 2;
			} elseif (224 <= $t && $t <= 239) {
				$tn = 3;
				$n += 3;
				$noc += 2;
			} elseif (240 <= $t && $t <= 247) {
				$tn = 4;
				$n += 4;
				$noc += 2;
			} elseif (248 <= $t && $t <= 251) {
				$tn = 5;
				$n += 5;
				$noc += 2;
			} elseif ($t == 252 || $t == 253) {
				$tn = 6;
				$n += 6;
				$noc += 2;
			} else {
				$n++;
			}
			if ($noc >= $length)
				break;
		}
		if ($noc > $length) {
			$n -= $tn;
		}
		if ($n < strlen($string)) {
			$strcut = substr($string, 0, $n);
			return $strcut . $dot;
		} else {
			return $string;
		}
	}
	
	/**
	 * 产生随机字符串
	 *
	 * @param int $length 输出长度 ，默认为 12
	 * @param string $chars 可选的 ，默认为 0123456789
	 * @return string 字符串
	 *        
	 */
	public static function random($chars='0123456789', $length=12) {
		$hash = '';
		$max = strlen($chars) - 1;
		for ($i = 0; $i < $length; $i++) {
			$hash .= $chars[mt_rand(0, $max)];
		}
		return $hash;
	}
	
}

?>