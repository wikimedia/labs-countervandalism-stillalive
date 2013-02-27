<?php
/**
 * Main entry point for StillAlive.
 * Goes through and ensures that all enabled entries in the pool
 * are running.
 * 
 * @author Timo Tijhof, 2013
 * @package StillAlive
 */

require_once( __DIR__ . '/Util.php' );

$verbose = false;

$localSettings = json_decode(
	Util::stripComments(
		file_get_contents( __DIR__ . '/localSettings.json' )
	),
	true
);

if ( !$localSettings ) {
	throw new Exception( 'SyntaxError while parsing JSON.' );
}
Util::placeholder( $localSettings, $localSettings['parameters'] );

$psDump = `ps aux`;

// Process templates
foreach ( $localSettings['template-pool'] as $templateID => $entries ) {
	foreach ( $entries as $entry ) {
		$copy = $localSettings['templates'][$templateID];
		Util::placeholder( $copy, $entry );
		if ( isset( $entry['disabled'] ) ) {
			$copy['disabled'] = $entry['disabled'];
		}
		$localSettings['pool'][] = $copy;
	}
	unset( $localSettings['template-pool'][$templateID] );
}

if ( $verbose ) {
	echo "-- Expanded settings:\n\n";
	var_export( $localSettings );

	echo "\n\n-- Check the pool:\n";
}

foreach ( $localSettings['pool'] as $poolID => $entry ) {

	// Expand simple entries
	if ( is_string( $entry ) ) {
		$entry = array(
			'cmd' => $entry,
		);
	}

	// Required
	if ( !isset( $entry['cmd'] ) ) {
		throw new Exception( 'Missing "cmd" property in pool[' . $poolID . '].' );
	}
	// Optional
	if ( !isset( $entry['cwd'] ) ) {
		$entry['cwd'] = $localSettings['cwd'];
	}
	if ( !isset( $entry['match'] ) ) {
		// We're using match instead of PID because our bots
		// can restart themselves, thus changing the PID.
		$entry['match'] = $entry['cmd'];
	}
	if ( !isset( $entry['disabled'] ) ) {
		$entry['disabled'] = false;
	}

	// Disabled? Ignore.
	if ( isset( $entry['disabled'] ) && $entry['disabled'] === true ) {
		continue;
	}

	// Still alive? Keep running.
	$psLine = Util::findLine( $psDump, $entry['match'] );
	if ( $psLine !== false ) {
		echo "\npool[$poolID] Running\n\t$psLine\n";
	} else {
		echo "\npool[$poolID] Not running";
		echo "\n\tcwd: {$entry['cwd']}\n";
		chdir( $entry['cwd'] );
		$entry['cmd'] = trim( $entry['cmd'] );
		if ( substr( $entry['cmd'], -2 ) !== ' &' ) {
			// Always in the background
			$entry['cmd'] .= ' &';
		}
		if ( substr( $entry['cmd'], 0, 6 ) !== 'nohup ' ) {
			// Don't let leaving the shell kill the process
			$entry['cmd'] = 'nohup ' . $entry['cmd'];
		}
		echo "\tcmd: {$entry['cmd']}\n";
		exec( $entry['cmd'] );
		sleep( 1 );
	}
}
