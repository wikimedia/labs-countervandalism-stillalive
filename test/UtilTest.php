<?php

namespace StillAlive\Test;

use StillAlive\Util;

class UtilTest extends \PHPUnit_Framework_TestCase {

	public static function provideStripComments() {
		return array(
			array(
				'{ "foo": true }',
			),
			array(
				'{ "foo": "a // x b" }',
			),
			array(
				'{ "foo": "a /* x */ b" }',
			),
			array(
				"{ \"foo\": true // x\n}",
				"{ \"foo\": true}",
			),
			array(
				"{\n  \"foo\": true, // x\n  // y\n  \"bar\": false\n}",
				"{\n  \"foo\": true,  \"bar\": false\n}"
			)
		);
	}

	/**
	 * @dataProvider provideStripComments
	 */
	public function testStripComments( $input, $output = null ) {
		$output = $output ?: $input;
		$this->assertEquals( $output, Util::stripComments( $input ) );
	}

	public static function providePlaceholder() {
		return array(
			'plain' => array(
				array( 'x' => 'Foo', 'y' => 'Bar' ),
				'b {x} a {y}{y}',
				'b Foo a BarBar'
			),
			'spaces' => array(
				array( 'x' => 'Foo' ),
				'b { x }',
				'b Foo'
			),
			'fallback' => array(
				array( 'x' => 'Foo' ),
				'echo "{x}" "{something}"',
				'echo "Foo" "{something}"'
			),
			'numbers' => array(
				array( 'this' => 1, 'that' => 2 ),
				array( 'here' => 'a/{this}', 'there' => 'b/{that}' ),
				array( 'here' => 'a/1', 'there' => 'b/2' ),
			),
		);
	}

	/**
	 * @dataProvider providePlaceholder
	 */
	public function testPlaceholder( array $params, $data, $output ) {
		$this->assertEquals( $output, Util::placeholder( $data, $params ) );
	}
}
