<?php
/**
 * IndexNow via Bing.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Providers\Google;


use Mihdan\IndexNow\WebmasterAbstract;
use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Dependencies\Google\Service\Indexing;
use Mihdan\IndexNow\Dependencies\Google\Service\Indexing\UrlNotification;
use Mihdan\IndexNow\Dependencies\Google\Client;
use Mihdan\IndexNow\Dependencies\Google\Service\Exception as Google_Service_Exception;
use Exception;

class GoogleWebmaster extends WebmasterAbstract {
	private const URL_UPDATED      = 'URL_UPDATED';
	private const RECRAWL_ENDPOINT = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrlbatch?apikey=%s';

	public function get_ping_endpoint(): string {
		return self::RECRAWL_ENDPOINT;
	}

	public function get_slug(): string {
		return 'google-webmaster';
	}

	public function get_name(): string {
		return __( 'Google Webmaster', 'mihdan-index-now' );
	}

	public function get_token(): string {
		return $this->wposa->get_option( 'json_key', 'google_webmaster' );
	}

	public function is_enabled(): bool {
		return $this->wposa->get_option( 'enable', 'google_webmaster', 'off' ) === 'on';
	}

	public function setup_hooks() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'mihdan_index_now/post_added', [ $this, 'ping' ] );
		add_action( 'mihdan_index_now/post_updated', [ $this, 'ping' ] );
	}

	/**
	 * Google Webmaster ping.
	 *
	 * @param int $post_id Post ID.
	 *
	 * throws \Google\Exception
	 */
	public function ping( int $post_id ) {
		try {
			/** @var \Mihdan\IndexNow\Dependencies\Google\Client $client */
			$client = new Client();
			$client->setApplicationName( Utils::get_plugin_name() );
			$client->setAuthConfig( json_decode( $this->get_token(), true ) );
			$client->addScope( Indexing::INDEXING );
			$client->setUseBatch( true );


			$body = new UrlNotification();
			$urls = [
				get_permalink( $post_id ),
			];

			$service = new Indexing( $client );
			$batch = $service->createBatch();

			foreach( $urls as $i => $url ) {
				$body->setType( self::URL_UPDATED );
				$body->setUrl( $url );

				$batch->add( $service->urlNotifications->publish( $body ), 'url-' . $i );
			}

			$results = $batch->execute();

			foreach ( $results as $result ) {
				if ( $result instanceof Google_Service_Exception ) {
					$status_code = $result->getCode();
					$message     = $result->getErrors()[0]['message'];
					break;
				} else {
					$status_code = 200;
					$message     = sprintf( '<a href="%s" target="_blank">%s</a> - OK', get_permalink( $post_id ), get_the_title( $post_id ) );
					break;
				}
			}

		} catch ( Exception $e ) {
			$message     = $e->getMessage();
			$status_code = 400;
		}

		$data = [
			'status_code'   => $status_code,
			'search_engine' => $this->get_slug(),
		];

		if ( Utils::is_response_code_success( $status_code ) ) {
			$this->logger->info( $message, $data );
		} else {
			$this->logger->error( $message, $data );
		}
	}

	public function get_quota(): array {
		// TODO: Implement get_quota() method.
		return [];
	}
}
