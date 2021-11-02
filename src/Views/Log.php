<?php
/**
 * Log class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Views;

use WP_List_Table;

class Log extends WP_List_Table {

	function __construct(){
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

	private function get_items( $per_page, $cur_page, $orderby, $order ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}index_now_log ORDER BY {$orderby} {$order} LIMIT %d, %d",
				$cur_page,
				$per_page
			)
		);
	}

	// создает элементы таблицы
	function prepare_items(){
		global $wpdb;

		// пагинация
		$per_page = get_user_meta( get_current_user_id(), get_current_screen()->get_option( 'per_page', 'option' ), true ) ?: 20;

		$this->set_pagination_args( array(
			'total_items' => 3,
			'per_page'    => $per_page,
		) );
		$cur_page = (int) $this->get_pagenum(); // желательно после set_pagination_args()

		$orderby = 'created_at';
		$order   = 'DESC';

		// элементы таблицы
		// обычно элементы получаются из БД запросом
		$this->items = $this->get_items( $per_page, $cur_page, $orderby, $order );
	}

	// колонки таблицы
	function get_columns(){
		return array(
			'cb'          => '<input type="checkbox" />',
			'log_id'      => 'ID',
			'post_id'     => 'Link',
			'created_at'  => 'Date',
			'level'       => 'Level',
			'status_code' => 'Status',
			'message'     => 'Message',
		);
	}

	// сортируемые колонки
	function get_sortable_columns(){
		return array(
			'status_code' => array( 'status_code', 'desc' ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'delete' => 'Delete',
		);
	}

	// Элементы управления таблицей. Расположены между групповыми действиями и панагией.
	function extra_tablenav( $which ){
		//echo '<div class="alignleft actions">HTML код полей формы (select). Внутри тега form...</div>';
	}

	// вывод каждой ячейки таблицы -------------

	static function _list_table_css(){
		?>
		<style>
			table.logs .column-log_id{ width:2em; }
			table.logs .column-level{ width:4em; }
			table.logs .column-status_code{ width:6em; }
			table.logs .column-created_at{ width:10em; }
			table.logs .level {
				font-size: 20px;
			}
			table.logs .level--error {
				color: #f00;
			}
			table.logs .level--debug {
				color: #0f0;
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
		} else if ( $colname === 'post_id' ) {
			return sprintf(
				'%d: <a href="%s" target="_blank">%s</a>',
				$item->$colname,
				get_permalink( $item->$colname ),
				get_the_title( $item->$colname ) );
		} else if ( $colname === 'level' ) {
			return sprintf( '<span class="level level--%s" title="%s">•</span>', $item->$colname, $item->$colname );
		} else {
			return isset($item->$colname) ? $item->$colname : print_r($item, 1);
		}

	}

	// заполнение колонки cb
	function column_cb( $item ){
		echo '<input type="checkbox" name="licids[]" id="cb-select-'. $item->log_id .'" value="'. $item->log_id .'" />';
	}

	// остальные методы, в частности вывод каждой ячейки таблицы...

	// helpers -------------

	private function bulk_action_handler(){
		if( empty($_POST['licids']) || empty($_POST['_wpnonce']) ) return;

		if ( ! $action = $this->current_action() ) return;

		if( ! wp_verify_nonce( $_POST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) )
			wp_die('nonce error');

		// делает что-то...
		die( $action ); // delete
		die( print_r($_POST['licids']) );

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
