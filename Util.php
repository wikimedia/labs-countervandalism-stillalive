<?php
/**
 * Utilities for StillAlive.
 *
 * @author Timo Tijhof, 2013-2016
 * @package stillalive
 */

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Util {
	private static $rPropStringTpl = '/\{\s*([a-zA-Z0-9_\-$]+)\s*\}/';

	/**
	 * @return boolean|array
	 * @throws ParseException
	 */
	public static function loadConfig( $filename ) {
		if ( !is_readable( $filename ) ) {
			return false;
		}
		$contents = file_get_contents( $filename );
		if ( substr( $filename, -4 ) === 'json' ) {
			return json_decode( self::stripComments( $contents ), true );
		} else {
			return Yaml::parse( $contents );
		}
	}

	/**
	 * From http://stackoverflow.com/a/19136663/319266
	 * @param string $str
	 */
	public static function stripComments( $str = '' ) {
		$str = preg_replace( '![ \t]*//.*[ \t]*[\r\n]!', '', $str );

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
	public static function placeholder( &$x, &$parameters = array() ) {
		if ( is_array( $x ) ) {
			foreach ( $x as $key => &$value ) {
				$value = self::placeholder( $value, $parameters );
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
