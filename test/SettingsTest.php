<?php

require_once dirname( __DIR__ ) . '/Util.php';

class SettingsTest extends PHPUnit_Framework_TestCase {
	public function testCvnSettings() {
		$localSettings = Util::loadConfig( dirname( __DIR__ ) . '/localSettings-cvn.yaml' );

		$this->assertEquals( 'array', gettype( $localSettings ), 'parsed without errors' );
		$this->assertEquals( 'cvn.cvnservice', $localSettings['user'], 'read property' );
	}
}
