<?php
/**
 * Log class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Views;

use Mihdan\IndexNow\Logger;
use WP_List_Table;

class Log extends WP_List_Table {

	function __construct() {
		parent::__construct(array(
			'singular' => 'log',
			'plural'   => 'logs',
			'ajax'     => false,
		));

		$this->bulk_action_handler();

		// screen option
		add_screen_option( 'per_page', array(
			'label'   => 'Показывать на странице',
			'default' => 20,
			'option'  => 'logs_per_page',
		) );

		$this->prepare_items();

		add_action( 'wp_print_scripts', [ __CLASS__, '_list_table_css' ] );
	}

	/**
	 * Get total items.
	 *
	 * @return int
	 */
	private function get_total_items() {
		global $wpdb;

		$table_name = Logger::get_table_name();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore
	}

	/**
	 * Get items.
	 *
	 * @param $per_page
	 * @param $cur_page
	 * @param $order_by
	 * @param $order
	 *
	 * @return array|object|null
	 */
	private function get_items( $per_page, $cur_page, $order_by, $order ) {
		global $wpdb;

		$table_name = Logger::get_table_name();

		$order_by = sanitize_sql_orderby( " {$order_by} {$order} " );

		$from = ( $cur_page - 1 ) * $per_page;

		return $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY {$order_by} LIMIT %d, %d",
				$from,
				$per_page
			)
		);
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		global $wpdb;

		$per_page_option = get_current_screen()->get_option( 'per_page' );
		$per_page        = (int) get_user_meta( get_current_user_id(), $per_page_option['option'], true ) ?: $per_page_option['default'];

		$this->set_pagination_args(
			[
				'total_items' => $this->get_total_items(),
				'per_page'    => $per_page,
			]
		);

		$cur_page = (int) $this->get_pagenum();

		$orderby = 'created_at';
		$order   = 'DESC';

		$this->items = $this->get_items( $per_page, $cur_page, $orderby, $order );
	}

	/**
	 * Get table columns.
	 *
	 * @return string[]
	 */
	public function get_columns() {
		return [
			'cb'            => '<input type="checkbox" />',
			'log_id'        => 'ID',
			'post_id'       => 'Link',
			'created_at'    => 'Date',
			'search_engine' => 'SE',
			'direction'     => 'Dir',
			'level'         => 'Level',
			'status_code'   => 'Status',
			'message'       => 'Message',
		];
	}

	// сортируемые колонки
	function get_sortable_columns(){
		return [
			'status_code' => [ 'status_code', 'asc' ],
		];
	}

	protected function get_bulk_actions() {
		return [
			'delete' => 'Delete',
		];
	}

	// Элементы управления таблицей. Расположены между групповыми действиями и панагией.
	function extra_tablenav( $which ){
		//echo '<div class="alignleft actions">HTML код полей формы (select). Внутри тега form...</div>';
	}

	// вывод каждой ячейки таблицы -------------

	static function _list_table_css(){
		?>
		<style>
			table.logs .column-log_id{ width:3em; }
			table.logs .column-level{ width:4em; }
			table.logs .column-direction{ width:4em; }
			table.logs .column-search_engine{ width:6em; }
			table.logs .column-status_code{ width:6em; }
			table.logs .column-created_at{ width:10em; }
			table.logs span.level {
				border-radius: 50%;
				width: 6px;
				height: 6px;
				color: transparent;
				display: inline-block;
				vertical-align: middle;
			}
			table.logs span.level--error {
				background-color: #f00;
			}
			table.logs span.level--debug {
				background-color: #0f0;
			}
		</style>
		<?php
	}

	// вывод каждой ячейки таблицы...
	function column_default( $item, $colname ){

		if ( $colname === 'customer_name' ) {
			// ссылки действия над элементом
			$actions = array();
			$actions['edit'] = sprintf( '<a href="%s">%s</a>', '#', __('edit','hb-users') );

			return esc_html( $item->name ) . $this->row_actions( $actions );
		} elseif ( $colname === 'post_id' ) {
			return sprintf(
				'%d: <a href="%s" target="_blank">%s</a>',
				$item->$colname,
				get_permalink( $item->$colname ),
				get_the_title( $item->$colname ) );
		} elseif ( $colname === 'level' ) {
			return sprintf( '<span class="level level--%s" title="%s">.</span>', $item->$colname, $item->$colname );
		} elseif ( $colname === 'direction' ) {
			return $item->$colname === 'outgoing' ? '<span class="dashicons dashicons-arrow-up-alt" title="Исходящий запрос"></span>' : '<span class="dashicons dashicons-arrow-down-alt" title="Входящий запрос"></span>';
		} elseif ( $colname === 'created_at' ) {
			return get_date_from_gmt( $item->$colname, 'd.m.Y H:i:s' );
		} else {
			return isset($item->$colname) ? $item->$colname : print_r($item, 1);
		}

	}

	// заполнение колонки cb
	function column_cb( $item ){
		echo '<input type="checkbox" name="log_rows[]" id="cb-select-'. $item->log_id .'" value="'. $item->log_id .'" />';
	}

	/**
	 * Bulk action handler for custom table.
	 */
	private function bulk_action_handler() {
		global $wpdb;

		if ( ! empty( $_POST['_wpnonce'] ) && ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		if ( 'delete' === $this->current_action() ) {
			if ( is_array( $_POST['log_rows'] ) ) {
				$log_rows   = implode( ',', array_map( 'absint', $_POST['log_rows'] ) );
				$table_name = Logger::get_table_name();

				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$table_name} WHERE log_id IN ({$log_rows})"
					)
				);
			}
		}
	}

	/*
	// Пример создания действий - ссылок в основной ячейки таблицы при наведении на ряд.
	// Однако гораздо удобнее указать их напрямую при выводе ячейки - см ячейку customer_name...

	// основная колонка в которой будут показываться действия с элементом
	protected function get_default_primary_column_name() {
		return 'disp_name';
	}

	// действия над элементом для основной колонки (ссылки)
	protected function handle_row_actions( $post, $column_name, $primary ) {
		if ( $primary !== $column_name ) return ''; // только для одной ячейки

		$actions = array();

		$actions['edit'] = sprintf( '<a href="%s">%s</a>', '#', __('edit','hb-users') );

		return $this->row_actions( $actions );
	}
	*/

}
