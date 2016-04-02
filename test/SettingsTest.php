<?php

require_once dirname( __DIR__ ) . '/Util.php';

class SettingsTest extends PHPUnit_Framework_TestCase {
	public function testCvnSettings() {
		$localSettings = json_decode( Util::stripComments(
			file_get_contents( dirname( __DIR__ ) . '/localSettings-cvn.json' )
		), true );

		$this->assertEquals( JSON_ERROR_NONE, json_last_error(), 'last error' );
		$this->assertTrue( is_array( $localSettings ), 'parsed without errors' );

	}
}
