<?php
return [
	'target_php_version' => '7.0',

	'directory_list' => [
		'vendor/symfony/yaml/',
		'vendor/ulrichsg/getopt-php/',
		'src/',
	],

	'exclude_file_list' => [
	],

	'exclude_analysis_directory_list' => [
		'vendor/'
	],

	// https://github.com/phan/phan/tree/v3/.phan/plugins
	'plugins' => [
		// Recommended set from mediawiki-phan-config
		'AddNeverReturnTypePlugin',
		'DuplicateArrayKeyPlugin',
		'DuplicateExpressionPlugin',
		'LoopVariableReusePlugin',
		'PregRegexCheckerPlugin',
		'RedundantAssignmentPlugin',
		'SimplifyExpressionPlugin',
		'UnreachableCodePlugin',
		'UnusedSuppressionPlugin',
		'UseReturnValuePlugin',

		// Extra ones
		'AlwaysReturnPlugin',
		'DollarDollarPlugin',
		'EmptyStatementListPlugin',
		'PrintfCheckerPlugin',
		'SleepCheckerPlugin',
	],

	'suppress_issue_types' => [
		'PhanTypePossiblyInvalidDimOffset'
	],
];
