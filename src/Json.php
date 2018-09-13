<?php

namespace TusPhp;

class Json {

	public static function decodeOrEmptyArray($string) {
		$decoded = json_decode($string, true);
		return $decoded === null ? [] : $decoded;
	}

}
