<?php

namespace StillAlive\Test;

use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use StillAlive\Main;
use StillAlive\Util;
use Wikimedia\TestingAccessWrapper;

class MainTest extends \PHPUnit_Framework_TestCase {
	/**
	 * @var  vfsStreamDirectory
	 */
	private $tmpDir;

	public function setUp() {
		$this->tmpDir = vfsStream::setup( 'tmp' );
	}

	public static function provideArgs() {
		return array(
			array( '' ),
			array( '-v' ),
			array( '--help' ),
			array( '--dry' ),
			array( '--pool example' ),
			array( '--pool=example' ),
			array( 'x', 'No more operands expected' ),
			array( '-x', 'is unknown' ),
			array( '-v x', 'No more operands expected' ),
			array( '--pool', 'must have a value' ),
		);
	}

	/**
	 * @dataProvider provideArgs
	 */
	public function testConstructor( $arg, $errorMsg = false ) {
		if ( $errorMsg ) {
			$this->expectOutputRegex( "/$errorMsg/" );
		}
		$app = new Main( array( 'arg' => $arg ) );
		$this->assertSame( $app->getExitCode(), $errorMsg ? 1 : 0, 'Exit code' );
	}

	public function testConstructorBadConfigFile() {
		$this->setExpectedException( InvalidArgumentException::class, 'Config parameter' );
		$app = new Main( array( 'configFile' => '' ) );
	}

	public function testHelpText() {
		$expected = 'Usage: stillalive [options] 

Options:
  --dry          Dry run (will not execute any tasks)
  --pool <arg>   Only execute tasks in this pool
  -v, --verbose  Be verbose in output
  -h, --help     Show this message
';

		$this->expectOutputString( $expected );
		$app = new Main( array( 'arg' => '--help' ) );
		TestingAccessWrapper::newFromObject( $app )
			->opt->set( \GetOpt\GetOpt::SETTING_SCRIPT_NAME, 'stillalive' );
		$app->run();
		$this->assertSame( $app->getExitCode(), 0, 'Exit code' );
	}

	public static function provideConfig() {
		return array(
			'empty config' => array(
				'arg' => '',
				'config' => array(
					'cwd' => '/tmp',
					'tasks' => array(),
				),
				'tasks' => array(),
			),
			'simple task' => array(
				'arg' => '',
				'config' => array(
					'cwd' => '/tmp',
					'tasks' => array(
						'sleep 1',
					),
				),
				'tasks' => array(
					array(
						'cwd' => '/tmp',
						'cmd' => 'nohup sleep 1 &',
						'match' => 'sleep 1',
					),
				),
			),
			'parameters, user and disabled' => array(
				'arg' => '',
				'config' => array(
					'cwd' => '/tmp',
					'user' => 'nobody',
					'parameters' => array(
						'mykey' => 'my "value"',
					),
					'tasks' => array(
						array(
							'cmd' => 'sleep 1 | echo {mykey} > /dev/null 2>&1',
							'match' => 'sleep 1',
						),
						array(
							'cmd' => 'sleep 2',
							'user' => false
						),
						array(
							'cmd' => 'sleep 3',
							'disabled' => true
						)
					),
				),
				'tasks' => array(
					array(
						'cwd' => '/tmp',
						'cmd' => 'sudo -u \'nobody\' nohup sleep 1 | echo my "value" > /dev/null 2>&1 &',
						'match' => 'sleep 1',
					),
					array(
						'cwd' => '/tmp',
						'cmd' => 'nohup sleep 2 &',
						'match' => 'sleep 2',
					),
				),
			),
			'templated task' => array(
				'arg' => '',
				'config' => array(
					'cwd' => '/tmp',
					'tasks' => array(
						'sleep 1',
					),
					'templates' => array(
						'repeat' => array(
							'cmd' => 'echo {word} {word} {word}',
							'cwd' => '/tmp/repeat-{word}',
						),
					),
					'template-tasks' => array(
						'repeat' => array(
							array( 'word' => 'this' ),
							array( 'word' => 'that', 'cwd' => '/tmp/override' ),
						),
					),
				),
				'tasks' => array(
					array(
						'cwd' => '/tmp',
						'cmd' => 'nohup sleep 1 &',
						'match' => 'sleep 1',
					),
					array(
						'cwd' => '/tmp/repeat-this',
						'cmd' => 'nohup echo this this this &',
						'match' => 'echo this this this',
					),
					array(
						'cwd' => '/tmp/override',
						'cmd' => 'nohup echo that that that &',
						'match' => 'echo that that that',
					),
				),
			),
			'default pool' => array(
				'arg' => '',
				'config' => array(
					'cwd' => '/tmp',
					'tasks' => array(
						'sleep 1',
					),
					'templates' => array(
						'repeat' => array(
							'cmd' => 'echo {word} {word} {word}',
							'cwd' => '/tmp/repeat-{word}',
						),
					),
					'template-tasks' => array(
						'repeat' => array(
							array( 'word' => 'this', 'pool' => 'srv1' ),
							array( 'word' => 'this', 'pool' => 'srv2' ),
							array( 'word' => 'that', 'pool' => 'srv2' ),
						),
					),
				),
				'tasks' => array(
					array(
						'cwd' => '/tmp',
						'cmd' => 'nohup sleep 1 &',
						'match' => 'sleep 1',
					),
				),
			),
			'specific pool' => array(
				'arg' => '--pool srv2',
				'config' => array(
					'cwd' => '/tmp',
					'tasks' => array(
						'sleep 1',
					),
					'templates' => array(
						'repeat' => array(
							'cmd' => 'echo {word} {word} {word}',
							'cwd' => '/tmp/repeat-{word}',
						),
					),
					'template-tasks' => array(
						'repeat' => array(
							array( 'word' => 'this', 'pool' => 'srv1' ),
							array( 'word' => 'this', 'pool' => 'srv2' ),
							array( 'word' => 'that', 'pool' => 'srv2' ),
						),
					),
				),
				'tasks' => array(
					array(
						'cwd' => '/tmp/repeat-this',
						'cmd' => 'nohup echo this this this &',
						'match' => 'echo this this this',
					),
					array(
						'cwd' => '/tmp/repeat-that',
						'cmd' => 'nohup echo that that that &',
						'match' => 'echo that that that',
					),
				),
			),
		);
	}

	/**
	 * @dataProvider provideConfig
	 */
	public function testGetTasks( $arg, $config, $tasks ) {
		$path = vfsStream::url( 'tmp/settings.json' );
		file_put_contents( $path, json_encode( $config ) );
		$app = new Main( array( 'arg' => $arg, 'configFile' => $path ) );

		$this->assertEquals( $tasks, $app->getTasks() );
	}

	public static function provideBadConfigData() {
		return array(
			'empty' => array(
				'config' => array(),
				'error' => "missing key 'tasks'",
			),
			'no cwd' => array(
				'config' => array( 'tasks' => array() ),
				'error' => "missing key 'cwd'",
			),
			'unused template' => array(
				'config' => array(
					'cwd' => '/tmp',
					'tasks' => array(),
					'templates' => array(),
				),
				'error' => "unused or incomplete template",
			),
			'incomplete template' => array(
				'config' => array(
					'cwd' => '/tmp',
					'tasks' => array(),
					'template-tasks' => array(),
				),
				'error' => "unused or incomplete template",
			),
			'no cmd' => array(
				'config' => array(
					'cwd' => '/tmp',
					'tasks' => array(
						array(),
					),
				),
				'error' => "missing key 'cmd'",
			),
		);
	}

	/**
	 * @dataProvider provideBadConfigData
	 */
	public function testGetTasksBadConfigData( $config, $error ) {
		$path = vfsStream::url( 'tmp/settings.json' );
		file_put_contents( $path, json_encode( $config ) );
		$app = new Main( array( 'arg' => '', 'configFile' => $path ) );

		$this->setExpectedExceptionRegExp( \DomainException::class, "/$error/" );
		$app->getTasks();
	}

	public function testGetTasksBadConfigFile() {
		$path = vfsStream::url( 'tmp/settings.json' );
		file_put_contents( $path, 'invalid json' );
		$app = new Main( array( 'arg' => '', 'configFile' => $path ) );

		$this->setExpectedException( InvalidArgumentException::class, 'invalid syntax' );
		$this->assertFalse( $app->getTasks() );
	}

	public function testEnsureTasksWillStart() {
		$app = $this->getMockBuilder( Main::class )
			->setConstructorArgs( array( array( 'arg' => '', 'configFile' => 'unused' ) ) )
			->setMethods( array( 'run', 'getTasks', 'getPsDump', 'exec', 'output' ) )
			->getMock();

		$app->expects( $this->once() )->method( 'getPsDump' )
			->willReturn( '' );

		$app->expects( $this->once() )->method( 'exec' )
			->with( '/tmp', 'sleep 10 &' );

		$app->ensureTasks( array( array(
			'cwd' => '/tmp',
			'cmd' => 'sleep 10 &',
			'match' => 'sleep 10',
		) ) );
	}

	public function testEnsureTasksWillDry() {
		$app = $this->getMockBuilder( Main::class )
			->setConstructorArgs( array( array( 'arg' => '--dry', 'configFile' => 'unused' ) ) )
			->setMethods( array( 'run', 'getTasks', 'getPsDump', 'exec', 'output' ) )
			->getMock();

		$app->expects( $this->once() )->method( 'getPsDump' )
			->willReturn( '' );

		$app->expects( $this->never() )->method( 'exec' );

		$app->ensureTasks( array( array(
			'cwd' => '/tmp',
			'cmd' => 'sleep 10 &',
			'match' => 'sleep 10',
		) ) );
	}

	public function testEnsureTasksWillDetect() {
		$app = $this->getMockBuilder( Main::class )
			->setConstructorArgs( array( array( 'arg' => '', 'configFile' => 'unused' ) ) )
			->setMethods( array( 'run', 'getTasks', 'getPsDump', 'exec', 'output' ) )
			->getMock();

		$app->expects( $this->once() )->method( 'getPsDump' )->willReturn(
			"USER PID %CPU %MEM VSZ RSS TTY STAT START TIME COMMAND\n" .
			"root 1 0.0 0.0 100796 7208 ? Ss Oct18 0:00 /sbin/init\n" .
			"root 20000 0.0 0.1 97368 11656 ? S 08:15 0:00 nohup sleep 10\n"
		);

		$app->expects( $this->never() )->method( 'exec' );

		$app->ensureTasks( array( array(
			'cwd' => '/tmp',
			'cmd' => 'nohup sleep 10 &',
			'match' => 'sleep 10',
		) ) );
	}
}
