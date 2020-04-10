<?php
namespace Jd29\Slim3;

class Util {
	static public $body_stream;

	static public function write ($string) {
		if (is_null(static::$body_stream)) {
			static::$body_stream = new \Slim\Http\NonBufferedBody();
		}
		if (is_array($string)) {
			return static::$body_stream->write(implode('', $string));
		} else {
			return static::$body_stream->write($string);
		}
	}

	static public function r ($var) {
		static::write(@r($var));
	}
}
