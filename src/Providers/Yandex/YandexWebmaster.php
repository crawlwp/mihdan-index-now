<?php
/**
 * Main class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Providers\Yandex;

use Mihdan\IndexNow\WebmasterAbstract;
use Mihdan\IndexNow\Traits\LoggerTrait;

class YandexWebmaster extends WebmasterAbstract {

	use LoggerTrait;

	const CODE_ENDPOINT = 'https://oauth.yandex.ru/authorize';
	const USER_ENDPOINT = 'https://api.webmaster.yandex.net/v4/user/';
	const TOKEN_ENDPOINT = 'https://oauth.yandex.ru/token';
	const HOSTS_ENDPOINT = 'https://api.webmaster.yandex.net/v4/user/%d/hosts';

	public function get_slug(): string {
		return 'yandex';
	}

	public function push(): bool {
		// TODO: Implement push() method.
		return true;
	}

	public function setup_hooks() {
		add_action( 'pre_update_option_mihdan_index_now_yandex_webmaster', [ $this, 'get_response_code' ], 10, 2 );
		//add_action( 'update_option_mihdan_index_now_yandex_webmaster', [ $this, 'get_yandex_token' ], 10, 2 );
		//add_action( 'update_option_mihdan_index_now_yandex_webmaster', [ $this, 'maybe_get_user_id' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'get_token' ] );
	}

	public function maybe_get_user_id( $old_value, $value ) {
		if ( $value['enable'] === 'off' ) {
			return;
		}

		if ( ! empty( $value['user_id'] ) ) {
			return;
		}

		if ( empty( $value['token'] ) ) {
			return;
		}

		$this->get_user_id( $value['token'] );
	}

	public function get_response_code( $value, $old_value ) {

		if ( $value['enable'] === 'off' ) {
			return $value;
		}

		if ( empty( $value['client_id'] ) ) {
			return $value;
		}

		if ( empty( $value['client_secret'] ) ) {
			return $value;
		}

		if ( ! empty( $value['access_token'] ) ) {
			return $value;
		}

		$url = sprintf(
			'%s?response_type=code&client_id=%s&redirect_uri=%s&force_confirm=yes&display=popup&state=%s',
			self::CODE_ENDPOINT,
			$value['client_id'],
			rawurldecode( admin_url( 'admin.php?page=' . MIHDAN_INDEX_NOW_SLUG ) ),
			$this->get_slug()
		);
		wp_redirect( $url );
		die;
	}

	public function get_token() {

		if ( isset( $_GET['code'], $_GET['state'] ) && $_GET['state'] === $this->get_slug() ) {
			$data = [];
			$data['body'] = [
				'grant_type'    => 'authorization_code',
				'code'          => $_GET['code'],
				'client_id'     => '08c41fd597854d47b2911716d7f71e2f',
				'client_secret' => '2a4c5831b44e469f8a86c36fd88101f6',
			];

			$response    = wp_remote_post( self::TOKEN_ENDPOINT, $data );
			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $status_code !== 200 ) {
				$this->error( $body['error_description'], [ 'search_engine' => $this->get_slug(), 'status_code' => $status_code ] );
				return;
			}

			$user_id = $this->get_user_id( $body['access_token'] );
			$host_id = $this->get_host_id( $user_id, $body['access_token'] );

			print_r($body['access_token']);
			print_r($user_id);
			print_r($host_id);

			//$body['access_token']
			//$body['expires_in']
			//$body['refresh_token']
			//$body['token_type']
		}
	}

	/**
	 * Get user ID.
	 *
	 * @param string $token Access token.
	 *
	 * @return int
	 */
	public function get_user_id( string $token ): int {
		$args = [
			'headers' => [
				'Authorization' => 'OAuth ' . $token,
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		];

		$response    = wp_remote_get( self::USER_ENDPOINT, $args );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$this->error( $body['error_message'], [ 'search_engine' => $this->get_slug(), 'status_code' => $status_code ] );
			return 0;
		}

		return $body['user_id'] ?? 0;
	}

	/**
	 * Get user ID.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Access token.
	 *
	 * @return int
	 */
	public function get_host_id( int $user_id, string $token ): array {
		$args = [
			'headers' => [
				'Authorization' => 'OAuth ' . $token,
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		];

		$response    = wp_remote_get( sprintf( self::HOSTS_ENDPOINT, $user_id ), $args );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code !== 200 ) {
			$this->error( $body['error_message'], [ 'search_engine' => $this->get_slug(), 'status_code' => $status_code ] );
			return 0;
		}

		return isset( $body['hosts'] )
			? wp_list_pluck( $body['hosts'], 'unicode_host_url', 'host_id' )
			: [];
	}
}
