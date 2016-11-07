<?php

require_once dirname( __DIR__ ) . '/Util.php';

class SettingsTest extends PHPUnit_Framework_TestCase {
	public function testCvnSettingsJSON() {
		$localSettings = Util::loadConfig( dirname( __DIR__ ) . '/localSettings-cvn.json' );

		$this->assertEquals( JSON_ERROR_NONE, json_last_error(), 'last error' );
		$this->assertEquals( 'array', gettype( $localSettings ), 'parsed without errors' );
		$this->assertEquals( 'cvn.cvnservice', $localSettings['user'], 'read property' );
	}

	public function testCvnSettingsYAML() {
		$localSettings = Util::loadConfig( dirname( __DIR__ ) . '/localSettings-cvn.yaml' );

		$this->assertEquals( 'array', gettype( $localSettings ), 'parsed without errors' );
		$this->assertEquals( 'cvn.cvnservice', $localSettings['user'], 'read property' );
	}

	public function testCvnSettings() {
		$settingJSON = Util::loadConfig( dirname( __DIR__ ) . '/localSettings-cvn.json' );
		$settingsYAML = Util::loadConfig( dirname( __DIR__ ) . '/localSettings-cvn.yaml' );

		$this->assertEquals( $settingJSON, $settingsYAML, 'settings formats in sync' );
	}
}
