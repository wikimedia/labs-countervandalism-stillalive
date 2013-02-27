#!/usr/bin/env php
<?php
/**
 * Main entry point for StillAlive.
 * Goes through and ensures that all tasks are running.
 * 
 * @author Timo Tijhof, 2013
 * @package StillAlive
 */

require_once( __DIR__ . '/Util.php' );

$opts = (object)getopt('', array(
	'verbose',
	'dry',
	'pool::'
));
$opts->verbose = isset( $opts->verbose );
$opts->dry = isset( $opts->dry );
$opts->pool = isset( $opts->pool ) ? strval( $opts->pool ) : false;

$localSettings = json_decode(
	Util::stripComments(
		@file_get_contents( __DIR__ . '/localSettings.json' )
	),
	true
);

if ( !$localSettings ) {
	echo 'SyntaxError while parsing JSON. Ensure localSettings.json exists and contains valid JSON.';
	echo "\n"; exit( 1 );
}
Util::placeholder( $localSettings, $localSettings['parameters'] );

$psDump = `ps aux`;

// Process templates
foreach ( $localSettings['template-tasks'] as $templateID => $entries ) {
	foreach ( $entries as $entry ) {
		$copy = $localSettings['templates'][$templateID];
		Util::placeholder( $copy, $entry );

		// Optional overrides
		foreach ( array( 'cmd', 'cwd', 'match', 'pool', 'disabled' ) as $key ) {
			if ( isset( $entry[$key] ) ) {
				$copy[$key] = $entry[$key];
			}
		}

		$localSettings['tasks'][] = $copy;
	}
	unset( $localSettings['template-tasks'][$templateID] );
}

if ( $opts->verbose ) {
	echo "-- Expanded settings:\n\n";
	var_export( $localSettings );

	echo "\n\n-- Check the tasks:\n";
}

foreach ( $localSettings['tasks'] as $taskID => $task ) {

	// Expand simple entries
	if ( is_string( $task ) ) {
		$task = array(
			'cmd' => $task,
		);
	}

	// Required
	if ( !isset( $task['cmd'] ) ) {
		echo 'Missing "cmd" property in tasks[' . $taskID . '].';
		echo "\n"; exit( 1 );
	}
	// Optional
	if ( !isset( $task['cwd'] ) ) {
		$task['cwd'] = $localSettings['cwd'];
	}
	if ( !isset( $task['match'] ) ) {
		// We're using match instead of PID because our bots
		// can restart themselves, thus changing the PID.
		$task['match'] = $task['cmd'];
	}
	if ( !isset( $task['pool'] ) ) {
		$task['pool'] = false;
	}
	if ( !isset( $task['disabled'] ) ) {
		$task['disabled'] = false;
	}

	// Still alive?
	echo "\nTask: {$task['match']}\n";
	$psLine = Util::findLine( $psDump, $task['match'] );
	if ( $psLine !== false ) {
		echo "\t=> RUNNING\n";
		echo "\tps: {$psLine}\n";
	} else {
		echo "\t=> NOT RUNNING\n";
	}

	// Disabled? Ignore.
	if ( isset( $task['disabled'] ) && $task['disabled'] === true ) {
		echo "\t=> DISABLED\n";
		continue;
	}

	// Different pool? Ignore.
	if ( $opts->pool !== $task['pool'] ) {
		echo "\t=> DIFFERENT POOL\n";
		continue;
	}

	// Ensure command goes in backgrond and not affected
	// by shell hup when user leaves ssh.
	$task['cmd'] = trim( $task['cmd'] );
	if ( substr( $task['cmd'], -2 ) !== ' &' ) {
		$task['cmd'] .= ' &';
	}
	if ( substr( $task['cmd'], 0, 6 ) !== 'nohup ' ) {
		$task['cmd'] = 'nohup ' . $task['cmd'];
	}

	if ( !$opts->dry ) {
		echo "\t=> START...\n";
		echo "\tcwd: {$task['cwd']}\n";
		echo "\tcmd: {$task['cmd']}\n";
		chdir( $task['cwd'] );
		exec( $task['cmd'] );
		sleep( 1 );
	} else {
		echo "\t=> DRY-RUN\n";
	}
}
