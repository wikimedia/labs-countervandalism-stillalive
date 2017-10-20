<?php
/**
 * Command-line entry point.
 *
 * @author Timo Tijhof, 2013-2017
 * @package stillalive
 */

require_once __DIR__ . '/../vendor/autoload.php';

try {
	$app = new StillAlive\Main();
	if ( $app->getExitCode() === 0 ) {
		$app->run();
	}
} catch ( Exception $e ) {
	echo "$e\n";
	exit( 1 );
}
exit( $app->getExitCode() );
