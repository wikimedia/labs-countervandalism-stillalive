# stillalive

A basic PHP script to ensure certain processes stay alive.

The script is configured by a JSON file that supports
templates and minimalistic syntax.

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
Can be overridden for individial processes in the pool.

### pool
The Pool is a collection of items that represent processes that
are or should be running.

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

	// Set disabled to true to temporarily ignore this item.
	// In that case stillalive won't start it if it stops.
	// It will keep existing processes running, however.
	"disabled": false
}
```

As shortcut, if a pool item only has the `cmd` property, you
may use the cmd string in place of the item object and it will
be converted into an object automatically.

### templates

Each Template has an id. A template is a Pool item with
additional placeholders that are not listed in `parameters`.

### template-pool

In the `template-pool` we use Templates to generate extra
Pool items. The template pool is keyed by template id and
contains arrays with parameters for each template.

The example makes this easier to understand. 

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
	"pool": [
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
	"template-pool": {
		"bob-init": [
			{ "filter": "foo" },
			{ "filter": "bar" },
			{ "filter": "baz" },
			{ "filter": "quux", "disabled": true }
		]
	}
}
```

## Install

1. Extract the files in a directory of choice
1. Create `localSettings.json` in the same directory, following
   the above example.
1. Run `php stillalive.php` at a regular interval (e.g. from
   crontab).
