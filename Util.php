<?php
class Util {
	private static $rPropStringTpl = '/\{\s*([a-zA-Z0-9_\-$]+)\s*\}/';

	/**
	 * From jshint/cli.js
	 * @param string $str
	 */
	public static function stripComments( $str = '' ) {
		$str = preg_replace( '/\/\*(?:(?!\*\/)[\s\S])*\*\//', '', $str );
		$str = preg_replace( '/\/\/[^\n\r]*/', '', $str );

		return $str;
	}

	/**
	 * Replace parameter placeholders in a string.
	 *
	 * @param string|Object &$x String to be processed, or object
	 *  to recursively walk to find these strings.
	 * @param Object &$parameters
	 * @return string
	 */
	public static function placeholder( &$x, &$parameters = array(), $prefix = '-' ) {
		if ( is_array( $x) ) {
			foreach ( $x as $key => &$value ) {
				$value = self::placeholder( $value, $parameters, "-$prefix" );
			}
		} elseif ( is_string( $x ) ) {
			$x = preg_replace_callback( self::$rPropStringTpl, function ( $matches ) use ( $parameters ) {
				$name = $matches[1];
				if ( isset( $parameters[$name] ) ) {
					return $parameters[$name];
				} else {
					return $matches[0];
				}
			}, $x );
		}
		return $x;
	}

	/**
	 * Return the line that contains the substring.
	 * @param string $str
	 * @param string $substr
	 * @return string|false
	 */
	public static function findLine( $str, $substr ) {
		$lines = explode( "\n", $str );
		foreach ( $lines as $line ) {
			if ( strpos( $line, $substr ) ) {
				return $line;
			}
		}
		return false;
	}
}
