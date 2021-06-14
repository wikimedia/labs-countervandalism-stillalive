<?php
/**
 * Main class.
 *
 * @author Timo Tijhof, 2013-2017
 * @package stillalive
 */

namespace StillAlive;

use GetOpt\GetOpt;
use GetOpt\ArgumentException;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

class Main {
	public $opt;
	private $exitCode = 1;

	/**
	 * @param array $options
	 *  - string 'arg' Defaults to `$_SERVER['argv']`.
	 *  - array 'configFile' Defaults to localSettings.yaml, localSettings.json
	 * @throws InvalidArgumentException
	 */
	public function __construct( array $params = array() ) {
		$params += array(
			'arg' => null,
			'configFile' => array(
				__DIR__ . '/../localSettings.yaml',
				__DIR__ . '/../localSettings.json',
			)
		);

		if ( !$params['configFile'] ) {
			throw new InvalidArgumentException( 'Config parameter must not be empty' );
		}

		$opt = new GetOpt(
			[
				[ null, 'dry', GetOpt::NO_ARGUMENT, 'Dry run (will not execute any tasks)', false ],
				[ null, 'pool', GetOpt::REQUIRED_ARGUMENT, 'Only execute tasks in this pool', false ],
				[ 'v', 'verbose', GetOpt::NO_ARGUMENT, 'Be verbose in output', false ],
				[ 'h', 'help', GetOpt::NO_ARGUMENT, 'Show this message', false ],
			],
			[
				GetOpt::SETTING_STRICT_OPERANDS => true
			]
		);
		try {
			$opt->process( $params['arg'] );
		} catch ( ArgumentException $e ) {
			echo "Error: " . $e->getMessage() . "\n\n" . $opt->getHelpText();
			$this->exitCode = 1;
			return;
		}

		$this->opt = $opt;
		$this->params = $params;
		$this->exitCode = 0;
	}

	public function run() {
		if ( $this->opt['help'] ) {
			echo $this->opt->getHelpText();
			return;
		}

		$tasks = $this->getTasks();

		if ( $this->opt['verbose'] ) {
			echo "-- Registered tasks:\n\n";
			var_export( $tasks );
		}

		$this->ensureTasks( $tasks );
	}

	/**
	 * @return array
	 */
	public function getTasks() {
		$config = null;
		foreach ( (array)$this->params['configFile'] as $file ) {
			$config = Util::loadConfig( $file );
			if ( is_array( $config ) ) {
				break;
			}
		}

		if ( !is_array( $config ) ) {
			throw new InvalidArgumentException( 'Config file not found or invalid syntax' );
		}

		// Expand any placeholders in the data
		if ( isset( $config['parameters'] ) ) {
			Util::placeholder( $config, $config['parameters'] );
		}

		// Validate top-level required fields
		foreach ( array( 'cwd' ) as $key ) {
			if ( !isset( $config[$key] ) ) {
				throw new DomainException( "Config is missing key '{$key}'" );
			}
		}

		if ( !isset( $config['tasks'] ) && !isset( $config['template-tasks'] ) ) {
			throw new DomainException( "Config must have at least one of 'tasks' or 'template-tasks'" );
		}

		// Expand top-level optional fields
		if ( !isset( $config['tasks'] ) ) {
			$config['tasks'] = [];
		}

		// Expand top-level optional fields
		if ( !isset( $config['user'] ) ) {
			$config['user'] = false;
		}

		// Expand template-tasks field
		if ( isset( $config['template-tasks'] ) xor isset( $config['templates'] ) ) {
				throw new DomainException( "Config contains unused or incomplete template" );
		}
		if ( isset( $config['template-tasks'] ) ) {
			foreach ( $config['template-tasks'] as $templateID => $entries ) {
				foreach ( $entries as $entry ) {
					$copy = $config['templates'][$templateID];
					Util::placeholder( $copy, $entry );

					// Add optional overrides
					foreach ( array( 'cmd', 'cwd', 'user', 'match', 'pool', 'disabled' ) as $key ) {
						if ( isset( $entry[$key] ) ) {
							$copy[$key] = $entry[$key];
						}
					}

					$config['tasks'][] = $copy;
				}
			}
		}

		// Validate, expand, and filter tasks
		foreach ( $config['tasks'] as $taskID => &$task ) {
			// Expand simple string
			if ( is_string( $task ) ) {
				$task = array(
					'cmd' => $task,
				);
			}

			// Validate required task field
			if ( !isset( $task['cmd'] ) ) {
				throw new DomainException( "Task '{$taskID}' is missing key 'cmd'" );
			}

			// Expand optional task fields with defaults
			if ( !isset( $task['cwd'] ) ) {
				$task['cwd'] = $config['cwd'];
			}
			if ( !isset( $task['user'] ) ) {
				$task['user'] = $config['user'];
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

			// Filter
			if (
				// Different pool
				$this->opt['pool'] !== $task['pool'] ||
				// Disabled
				$task['disabled'] === true
			 ) {
				unset( $config['tasks'][$taskID] );
				continue;
			}

			// Wrap command:
			// - Ensure process is in the background.
			$task['cmd'] = trim( $task['cmd'] );
			if ( substr( $task['cmd'], -2 ) !== ' &' ) {
				$task['cmd'] .= ' &';
			}
			// - Ensure process will not be affected by shell hup.
			if ( substr( $task['cmd'], 0, 6 ) !== 'nohup ' ) {
				$task['cmd'] = 'nohup ' . $task['cmd'];
			}
			// - Override user if specified
			if ( $task['user'] ) {
				$task['cmd'] = 'sudo -u ' . escapeshellarg( $task['user'] ) . ' ' . $task['cmd'];
			}

			unset( $task['user'] );
			unset( $task['pool'] );
			unset( $task['disabled'] );
		}

		return array_values( $config['tasks'] );
	}

	public function ensureTasks( array $tasks ) {
		$psDump = $this->getPsDump();

		foreach ( $tasks as $task ) {
			// Still alive?
			$this->output( "Task: {$task['match']}" );
			$psLine = Util::findLine( $psDump, $task['match'] );
			if ( $psLine !== false ) {
				$this->output( "\t=> RUNNING" );
				$this->output( "\tps: {$psLine}" );
				continue;
			} else {
				$this->output( "\t=> NOT RUNNING" );
			}

			if ( $this->opt['dry'] ) {
				$this->output( "\t=> DRY-RUN" );
				continue;
			} else {
				$this->output( "\t=> START..." );
				$this->output( "\tcwd: {$task['cwd']}" );
				$this->output( "\tcmd: {$task['cmd']}" );
				$this->exec( $task['cwd'], $task['cmd'] );
			}
		}
	}

	/**
	 * @return string Output from `ps aux` command.
	 * @codeCoverageIgnore
	 */
	protected function getPsDump() {
		return shell_exec( 'ps aux' );
	}

	/** @codeCoverageIgnore */
	protected function exec( $cwd, $command ) {
		chdir( $cwd );
		exec( $command );
		sleep( 1 );
	}

	/** @codeCoverageIgnore */
	protected function output( $text ) {
		print "$text\n";
	}

	/**
	 * @return int
	 */
	public function getExitCode() {
		return $this->exitCode;
	}
}
