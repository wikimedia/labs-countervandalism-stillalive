# stillalive

A basic PHP script to ensure certain processes stay alive.

The script is configured by a JSON file that supports
templates and minimalistic syntax.

## Install

1. Extract the files in a directory of choice
1. Create `localSettings.json` in the same directory, following
   the example below.
1. Run `php stillalive.php` at a regular interval (e.g. from
   crontab).

## Command line options

```
Usage: php stillalive.php [options]

   --verbose   Output extra information
       --dry   Dry run (don't actually execute any tasks)
 --pool=<id>   Only run tasks in this pool

```

## localSettings

### parameters
Using `{curly braces}` one can make placeholders that will be
replaced with the values from properties in the `parameters`
object.

Curly braces containing characters that don't refer to a known
parameter are left untouched.

Whitespace around the identifier is optional and trimmed.

### cwd
This is the default directory that scripts will be executed in.
Can be overridden for individial tasks.

### tasks
The Task represent a process that is or should be running.

All properties (except for `cmd`) are optional.

Example:
```js
{
	"cmd": "node awesome-server.js --ratio 1.2",

	// Switch to this directory before executing the command
	// Default: settings.cwd
	"cwd": "/opt/awesome-server",

	// Used to identify the process in `ps aux`
	// Default: this.cmd
	// You can customise this in case the cmd spawns another
	// process, or in case you want to be able to change the
	// parameters while still identiying the old process.
	"match": "node awesome-server.js"

	// Temporarily ignore a task by disabling it.
	// In that case stillalive won't start it if it stops.
	// It will keep existing processes running, however.
	"disabled": false,
}
```

As shortcut, if a Task only has the `cmd` property, you may
set the Task to be just a string and stillalive will automatically
convert it to an object with a `cmd` property.

### templates

Each Template has an id. A template is a Task with additional
placeholders that are not listed in `parameters`.

The following words must not be used as template parameters:

* `cmd`
* `cwd`
* `match`
* `disabled`
* `group` (reserved for future use)

### template-tasks

In the `template-tasks` we use Templates to generate extra
Tasks. This list is keyed by template id and contains arrays
with parameters for each template.

The example makes it easier to understand.

## Example
So, all in all, this is that it can look like:
```js
{
	"parameters": {
		"alice": "/opt/alice",
		"alice-bin": "{ alice }/bin",
		"alice-log": "/var/log/alice",

		"bob": "/opt/bob",
		"bob-bin": "{ bob }/bin",
		"bob-log": "/var/log/bob"
	},
	"cwd": "/home/stillalive",
	"tasks": [
		"{alice-bin}/init --filter foo > {alice-log}/foo.log",
		"{alice-bin}/init --filter bar > {alice-log}/bar.log",
		"{alice-bin}/init --filter baz > {alice-log}/baz.log",
		{
			"cwd": "/tmp",
			"cmd": "{ alice-bin }/init --filter quux > { alice-bin }/quux.log",
			"disabled": true
		}
	],
	"templates": {
		"bob-init": {
			"cwd": "/tmp",
			"cmd": "{ bob-bin }/init --filter { filter } > { bob-log }/{ filter }.log"
		}
	},
	"template-tasks": {
		"bob-init": [
			{ "filter": "foo" },
			{ "filter": "bar" },
			{ "filter": "baz" },
			{ "filter": "quux", "disabled": true }
		]
	}
}
```

## Pool

If you need to devide the tasks in groups (e.g. different servers,
different intervals, different permissions, etc.) then you can use
the `pool` property in a Task.

By default a task is in an unnamed pool. This way you don't
"leak" tasks without a pool into all other pools.

Pool must should be of type string.

For example, given:

* Task A, B and C.
* Task A has no pool. Task B and C are in pool p1.
* `$ stillalive` ensures task A
* `$ stillalive --pool=p1` ensures task B and C.
