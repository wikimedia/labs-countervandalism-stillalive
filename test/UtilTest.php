<?php

require_once dirname( __DIR__ ) . '/Util.php';

class UtilTest extends PHPUnit_Framework_TestCase {

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
			array(
				'b {x} a {y}{y}',
				array( 'x' => 'Foo', 'y' => 'Bar' ),
				'b Foo a BarBar'
			),
			array(
				'b { x }',
				array( 'x' => 'Foo' ),
				'b Foo'
			),
			array(
				array( 'here' => 'a/{this}', 'there' => 'b/{that}' ),
				array( 'this' => 1, 'that' => 2 ),
				array( 'here' => 'a/1', 'there' => 'b/2' ),
			),
		);
	}

	/**
	 * @dataProvider providePlaceholder
	 */
	public function testPlaceholder( $data, $params, $output ) {
		$this->assertEquals( $output, Util::placeholder( $data, $params ) );
	}
}
