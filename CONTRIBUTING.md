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

* Ensure your Composer install is at version 2.6 or later.

* Run `composer update --no-dev`
