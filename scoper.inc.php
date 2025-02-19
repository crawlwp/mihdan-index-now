<?php
/**
 * PHP-Scoper configuration file.
 *
 * @package mihdan-index-now
 */

use Isolated\Symfony\Component\Finder\Finder;

$wp_classes   = json_decode(file_get_contents(__DIR__ .'/vendor/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-classes.json'), true);
$wp_functions = json_decode(file_get_contents(__DIR__ .'/vendor/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-functions.json'), true);
$wp_constants = json_decode(file_get_contents(__DIR__ .'/vendor/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-constants.json'), true);


// Google API services to include classes for.
$google_services = implode(
	'|',
	array(
		'Indexing',
		'SearchConsole',
		'Foo',
	)
);

return array(
	'prefix'                     => 'Mihdan\\IndexNow\\Dependencies',
	'finders'                    => array(
		Finder::create()->files()->in('vendor/writecrow/country_code_converter/src'),

		// General dependencies, except Google API services.
		Finder::create()
		      ->files()
		      ->ignoreVCS( true )
		      ->notName( '/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.(json|lock)/' )
		      ->exclude(
			      array(
				      'doc',
				      'test',
				      'test_old',
				      'tests',
				      'Tests',
				      'vendor-bin',
			      )
		      )
		      ->path( '#^firebase/#' )
		      ->path( '#^google/apiclient/#' )
		      ->path( '#^google/auth/#' )
		      ->path( '#^guzzlehttp/#' )
		      ->path( '#^monolog/#' )
		      ->path( '#^psr/#' )
		      ->path( '#^ralouphie/#' )
		      ->path( '#^react/#' )
		      ->path( '#^true/#' )
		      ->path( '#^symfony/#' )
		      ->path( '#^paragonie/#' )
		      ->path( '#^phpseclib/#' )
		      ->path( '#^martin-hughes/#' )
		      ->path( '#^carbonphp/#' )
		      ->path( '#^nesbot/#' )
		      ->path( '#^deliciousbrains/#' )
		      ->path( '#^collizo4sky/persist-admin-notices-dismissal/#' )
		      ->in( 'vendor' ),

		// Google API service infrastructure classes.
		Finder::create()
		      ->files()
		      ->ignoreVCS( true )
		      ->notName( '/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/' )
		      ->exclude(
			      array(
				      'doc',
				      'test',
				      'test_old',
				      'tests',
				      'Tests',
				      'vendor-bin',
			      )
		      )
		      ->path( "#^google/apiclient-services/src/($google_services)/#" )
		      ->in( 'vendor' ),

		// Google API service entry classes.
		Finder::create()
		      ->files()
		      ->ignoreVCS( true )
		      ->name( "#^($google_services)\.php$#" )
		      ->depth( '== 0' )
		      ->in( 'vendor/google/apiclient-services/src' ),
		Finder::create()
		      ->files()
		      ->ignoreVCS( true )
		      ->name( '#^autoload.php$#' )
		      ->depth( '== 0' )
		      ->in( 'vendor/google/apiclient-services' ),
	),
	'exclude-files'            => array(

		// This dependency is a global function which should remain global.
		'vendor/ralouphie/getallheaders/src/getallheaders.php',
	),
	'patchers'                   => array(
		function( $file_path, $prefix, $contents ) {
			if ( preg_match( '#google/apiclient/src/Google/Http/REST\.php$#', $file_path ) ) {
				$contents = str_replace( "\\$prefix\\intVal", '\\intval', $contents );
			}
			if ( false !== strpos( $file_path, 'vendor/google/apiclient/' ) || false !== strpos( $file_path, 'vendor/google/auth/' ) ) {
				$prefix   = str_replace( '\\', '\\\\', $prefix );
				$contents = str_replace( "'\\\\GuzzleHttp\\\\ClientInterface", "'\\\\" . $prefix . '\\\\GuzzleHttp\\\\ClientInterface', $contents );
				//$contents = str_replace( '"\\\\GuzzleHttp\\\\ClientInterface', '"\\\\' . $prefix . '\\\\GuzzleHttp\\\\ClientInterface', $contents );
				$contents = str_replace( "'GuzzleHttp\\\\ClientInterface", "'" . $prefix . '\\\\GuzzleHttp\\\\ClientInterface', $contents );
				//$contents = str_replace( '"GuzzleHttp\\\\ClientInterface', '"' . $prefix . '\\\\GuzzleHttp\\\\ClientInterface', $contents );
			}
			if ( false !== strpos( $file_path, 'vendor/google/apiclient/' ) ) {
				$contents = str_replace( "'Google_", "'" . $prefix . '\Google_', $contents );
				//$contents = str_replace( '"Google_', '"' . $prefix . '\Google_', $contents );
			}
			if (
				false !== strpos( $file_path, 'vendor/symfony/polyfill-intl-idn/bootstrap80.php' ) ||
				false !== strpos( $file_path, 'vendor/symfony/polyfill-intl-normalizer/bootstrap80.php' )
			) {
				$contents = str_replace( ': string|false', '', $contents );
			}
			return $contents;
		},
	),
	'exclude-classes'         => $wp_classes,
	'exclude-functions'       => $wp_functions,
	'exclude-constants'       => $wp_constants,
	'expose-global-constants' => false,
	'expose-global-classes'   => false,
	'expose-global-functions' => false,
);
