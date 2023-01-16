<?php

namespace StillAlive\Test;

use StillAlive\Util;

/**
 * @covers Util
 */
class SettingsTest extends \PHPUnit\Framework\TestCase {
	public function testCvnSettings() {
		$localSettings = Util::loadConfig( dirname( __DIR__ ) . '/localSettings-cvn.yaml' );

		$this->assertEquals( 'array', gettype( $localSettings ), 'parsed without errors' );
		$this->assertEquals( 'cvn.cvnservice', $localSettings['user'], 'read property' );
	}
}
