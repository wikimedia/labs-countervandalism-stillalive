## Update vendor

* Change `composer.json` to pin the lowest deployed PHP version:

	```
		"config": {
			"optimize-autoloader": true,
			"platform": {
				"php": "7.0.33"
			}
		},
	```

* Change `composer.json` to remove require-dev entries.

	```
		"require-dev": {
		}
	```

* Ensure composer is 2.2 LTS (not later!). If you have a later version,
  run `composer selfupdate --2.2` to downgrade. This is to ensure
  support for PHP 7.0 autoloader.

* Run `composer update --no-dev`
