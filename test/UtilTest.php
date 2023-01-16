<?php

namespace StillAlive\Test;

use StillAlive\Util;

/**
 * @covers Util
 */
class UtilTest extends \PHPUnit\Framework\TestCase {

	public static function provideStripComments() {
		return [
			[
				'{ "foo": true }',
			],
			[
				'{ "foo": "a // x b" }',
			],
			[
				'{ "foo": "a /* x */ b" }',
			],
			[
				"{ \"foo\": true // x\n}",
				"{ \"foo\": true}",
			],
			[
				"{\n  \"foo\": true, // x\n  // y\n  \"bar\": false\n}",
				"{\n  \"foo\": true,  \"bar\": false\n}"
			]
		];
	}

	/**
	 * @dataProvider provideStripComments
	 */
	public function testStripComments( $input, $output = null ) {
		$output = $output ?: $input;
		$this->assertEquals( $output, Util::stripComments( $input ) );
	}

	public static function providePlaceholder() {
		return [
			'plain' => [
				[ 'x' => 'Foo', 'y' => 'Bar' ],
				'b {x} a {y}{y}',
				'b Foo a BarBar'
			],
			'spaces' => [
				[ 'x' => 'Foo' ],
				'b { x }',
				'b Foo'
			],
			'fallback' => [
				[ 'x' => 'Foo' ],
				'echo "{x}" "{something}"',
				'echo "Foo" "{something}"'
			],
			'numbers' => [
				[ 'this' => 1, 'that' => 2 ],
				[ 'here' => 'a/{this}', 'there' => 'b/{that}' ],
				[ 'here' => 'a/1', 'there' => 'b/2' ],
			],
		];
	}

	/**
	 * @dataProvider providePlaceholder
	 */
	public function testPlaceholder( array $params, $data, $output ) {
		$this->assertEquals( $output, Util::placeholder( $data, $params ) );
	}
}
