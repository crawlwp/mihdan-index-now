<?php
/**
 * PHP-Scoper configuration file.
 *
 * @package   Google\Site_Kit
 * @copyright 2021 Google LLC
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://sitekit.withgoogle.com
 */

use Isolated\Symfony\Component\Finder\Finder;

// Google API services to include classes for.
$google_services = implode(
	'|',
	array(
		'Indexing',
		'Foo',
	)
);

return array(
	'prefix'                     => 'Mihdan\\IndexNow\\Dependencies',
	'finders'                    => array(

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
		      ->path( '#^rdlowrey/#' )
		      ->path( '#^whichbrowser/#' )
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
	'files-whitelist'            => array(

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
	'whitelist'                  => array(),
	'whitelist-global-constants' => false,
	'whitelist-global-classes'   => false,
	'whitelist-global-functions' => false,
);
