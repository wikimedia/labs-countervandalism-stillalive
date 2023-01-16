<?php

namespace StillAlive\Test;

use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use StillAlive\Main;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers Main
 */
class MainTest extends \PHPUnit\Framework\TestCase {
	/**
	 * @var fsStreamDirectory
	 */
	private $tmpDir;

	public function setUp(): void {
		$this->tmpDir = vfsStream::setup( 'tmp' );
	}

	public static function provideArgs() {
		return [
			[ '' ],
			[ '-v' ],
			[ '--help' ],
			[ '--dry' ],
			[ '--pool example' ],
			[ '--pool=example' ],
			[ 'x', 'No more operands expected' ],
			[ '-x', 'is unknown' ],
			[ '-v x', 'No more operands expected' ],
			[ '--pool', 'must have a value' ],
		];
	}

	/**
	 * @dataProvider provideArgs
	 */
	public function testConstructor( $arg, $errorMsg = false ) {
		if ( $errorMsg ) {
			$this->expectOutputRegex( "/$errorMsg/" );
		}
		$app = new Main( [ 'arg' => $arg ] );
		$this->assertSame( $app->getExitCode(), $errorMsg ? 1 : 0, 'Exit code' );
	}

	public function testConstructorBadConfigFile() {
		$this->expectException( InvalidArgumentException::class, 'Config parameter' );
		$app = new Main( [ 'configFile' => '' ] );
	}

	public function testHelpText() {
		$expected = 'Usage: stillalive [options] ' . '

Options:
  --dry          Dry run (will not execute any tasks)
  --pool <arg>   Only execute tasks in this pool
  -v, --verbose  Be verbose in output
  -h, --help     Show this message

';

		$this->expectOutputString( $expected );
		$app = new Main( [ 'arg' => '--help' ] );
		TestingAccessWrapper::newFromObject( $app )
			->opt->set( \GetOpt\GetOpt::SETTING_SCRIPT_NAME, 'stillalive' );
		$app->run();
		$this->assertSame( 0, $app->getExitCode(), 'Exit code' );
	}

	public static function provideConfig() {
		return [
			'empty config' => [
				'arg' => '',
				'config' => [
					'cwd' => '/tmp',
					'tasks' => [],
				],
				'tasks' => [],
			],
			'simple task' => [
				'arg' => '',
				'config' => [
					'cwd' => '/tmp',
					'tasks' => [
						'sleep 1',
					],
				],
				'tasks' => [
					[
						'cwd' => '/tmp',
						'cmd' => 'nohup sleep 1 &',
						'match' => 'sleep 1',
					],
				],
			],
			'parameters, user and disabled' => [
				'arg' => '',
				'config' => [
					'cwd' => '/tmp',
					'user' => 'nobody',
					'parameters' => [
						'mykey' => 'my "value"',
					],
					'tasks' => [
						[
							'cmd' => 'sleep 1 | echo {mykey} > /dev/null 2>&1',
							'match' => 'sleep 1',
						],
						[
							'cmd' => 'sleep 2',
							'user' => false
						],
						[
							'cmd' => 'sleep 3',
							'disabled' => true
						]
					],
				],
				'tasks' => [
					[
						'cwd' => '/tmp',
						'cmd' => 'sudo -u \'nobody\' nohup sleep 1 | echo my "value" > /dev/null 2>&1 &',
						'match' => 'sleep 1',
					],
					[
						'cwd' => '/tmp',
						'cmd' => 'nohup sleep 2 &',
						'match' => 'sleep 2',
					],
				],
			],
			'templated task' => [
				'arg' => '',
				'config' => [
					'cwd' => '/tmp',
					'tasks' => [
						'sleep 1',
					],
					'templates' => [
						'repeat' => [
							'cmd' => 'echo {word} {word} {word}',
							'cwd' => '/tmp/repeat-{word}',
						],
					],
					'template-tasks' => [
						'repeat' => [
							[ 'word' => 'this' ],
							[ 'word' => 'that', 'cwd' => '/tmp/override' ],
						],
					],
				],
				'tasks' => [
					[
						'cwd' => '/tmp',
						'cmd' => 'nohup sleep 1 &',
						'match' => 'sleep 1',
					],
					[
						'cwd' => '/tmp/repeat-this',
						'cmd' => 'nohup echo this this this &',
						'match' => 'echo this this this',
					],
					[
						'cwd' => '/tmp/override',
						'cmd' => 'nohup echo that that that &',
						'match' => 'echo that that that',
					],
				],
			],
			'default pool' => [
				'arg' => '',
				'config' => [
					'cwd' => '/tmp',
					'tasks' => [
						'sleep 1',
					],
					'templates' => [
						'repeat' => [
							'cmd' => 'echo {word} {word} {word}',
							'cwd' => '/tmp/repeat-{word}',
						],
					],
					'template-tasks' => [
						'repeat' => [
							[ 'word' => 'this', 'pool' => 'srv1' ],
							[ 'word' => 'this', 'pool' => 'srv2' ],
							[ 'word' => 'that', 'pool' => 'srv2' ],
						],
					],
				],
				'tasks' => [
					[
						'cwd' => '/tmp',
						'cmd' => 'nohup sleep 1 &',
						'match' => 'sleep 1',
					],
				],
			],
			'specific pool' => [
				'arg' => '--pool srv2',
				'config' => [
					'cwd' => '/tmp',
					'tasks' => [
						'sleep 1',
					],
					'templates' => [
						'repeat' => [
							'cmd' => 'echo {word} {word} {word}',
							'cwd' => '/tmp/repeat-{word}',
						],
					],
					'template-tasks' => [
						'repeat' => [
							[ 'word' => 'this', 'pool' => 'srv1' ],
							[ 'word' => 'this', 'pool' => 'srv2' ],
							[ 'word' => 'that', 'pool' => 'srv2' ],
						],
					],
				],
				'tasks' => [
					[
						'cwd' => '/tmp/repeat-this',
						'cmd' => 'nohup echo this this this &',
						'match' => 'echo this this this',
					],
					[
						'cwd' => '/tmp/repeat-that',
						'cmd' => 'nohup echo that that that &',
						'match' => 'echo that that that',
					],
				],
			],
		];
	}

	/**
	 * @dataProvider provideConfig
	 */
	public function testGetTasks( $arg, $config, $tasks ) {
		$path = vfsStream::url( 'tmp/settings.json' );
		file_put_contents( $path, json_encode( $config ) );
		$app = new Main( [ 'arg' => $arg, 'configFile' => $path ] );

		$this->assertEquals( $tasks, $app->getTasks() );
	}

	public static function provideBadConfigData() {
		return [
			'empty' => [
				'config' => [],
				'error' => "missing key 'cwd'",
			],
			'no tasks' => [
				'config' => [ 'cwd' => '/tmp' ],
				'error' => "must have at least one of 'tasks' or 'template-tasks'",
			],
			'unused template' => [
				'config' => [
					'cwd' => '/tmp',
					'tasks' => [],
					'templates' => [],
				],
				'error' => "unused or incomplete template",
			],
			'incomplete template' => [
				'config' => [
					'cwd' => '/tmp',
					'tasks' => [],
					'template-tasks' => [],
				],
				'error' => "unused or incomplete template",
			],
			'no cmd' => [
				'config' => [
					'cwd' => '/tmp',
					'tasks' => [
						[],
					],
				],
				'error' => "missing key 'cmd'",
			],
		];
	}

	/**
	 * @dataProvider provideBadConfigData
	 */
	public function testGetTasksBadConfigData( $config, $error ) {
		$path = vfsStream::url( 'tmp/settings.json' );
		file_put_contents( $path, json_encode( $config ) );
		$app = new Main( [ 'arg' => '', 'configFile' => $path ] );

		$this->expectException( \DomainException::class );
		$this->expectExceptionMessageMatches( "/$error/" );
		$app->getTasks();
	}

	public function testGetTasksBadConfigFile() {
		$path = vfsStream::url( 'tmp/settings.json' );
		file_put_contents( $path, 'invalid json' );
		$app = new Main( [ 'arg' => '', 'configFile' => $path ] );

		$this->expectException( InvalidArgumentException::class, 'invalid syntax' );
		$this->assertFalse( $app->getTasks() );
	}

	public function testEnsureTasksWillStart() {
		$app = $this->getMockBuilder( Main::class )
			->setConstructorArgs( [ [ 'arg' => '', 'configFile' => 'unused' ] ] )
			->onlyMethods( [ 'run', 'getTasks', 'getPsDump', 'exec', 'output' ] )
			->getMock();

		$app->expects( $this->once() )->method( 'getPsDump' )
			->willReturn( '' );

		$app->expects( $this->once() )->method( 'exec' )
			->with( '/tmp', 'sleep 10 &' );

		$app->ensureTasks( [ [
			'cwd' => '/tmp',
			'cmd' => 'sleep 10 &',
			'match' => 'sleep 10',
		] ] );
	}

	public function testEnsureTasksWillDry() {
		$app = $this->getMockBuilder( Main::class )
			->setConstructorArgs( [ [ 'arg' => '--dry', 'configFile' => 'unused' ] ] )
			->onlyMethods( [ 'run', 'getTasks', 'getPsDump', 'exec', 'output' ] )
			->getMock();

		$app->expects( $this->once() )->method( 'getPsDump' )
			->willReturn( '' );

		$app->expects( $this->never() )->method( 'exec' );

		$app->ensureTasks( [ [
			'cwd' => '/tmp',
			'cmd' => 'sleep 10 &',
			'match' => 'sleep 10',
		] ] );
	}

	public function testEnsureTasksWillDetect() {
		$app = $this->getMockBuilder( Main::class )
			->setConstructorArgs( [ [ 'arg' => '', 'configFile' => 'unused' ] ] )
			->onlyMethods( [ 'run', 'getTasks', 'getPsDump', 'exec', 'output' ] )
			->getMock();

		$app->expects( $this->once() )->method( 'getPsDump' )->willReturn(
			"USER PID %CPU %MEM VSZ RSS TTY STAT START TIME COMMAND\n" .
			"root 1 0.0 0.0 100796 7208 ? Ss Oct18 0:00 /sbin/init\n" .
			"root 20000 0.0 0.1 97368 11656 ? S 08:15 0:00 nohup sleep 10\n"
		);

		$app->expects( $this->never() )->method( 'exec' );

		$app->ensureTasks( [ [
			'cwd' => '/tmp',
			'cmd' => 'nohup sleep 10 &',
			'match' => 'sleep 10',
		] ] );
	}
}
