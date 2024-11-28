<?php
/**
 * Main Class file for `WP_OSA`
 *
 * Main class that deals with all other classes.
 *
 * @since   1.0.0
 * @package WPOSA
 */

namespace Mihdan\IndexNow\Views;

use Mihdan\IndexNow\Utils;

/**
 * WP_OSA.
 *
 * WP Settings API Class.
 *
 * @since 1.0.0
 */
class WPOSA
{

	/**
	 * Allowed HTML tags and attributes for wp_kses().
	 */
	private const ALLOWED_HTML = [
		'strong'   => [],
		'b'        => [],
		'i'        => [],
		'code'     => [],
		'ul'       => [
			'class' => true,
		],
		'ol'       => [],
		'li'       => [],
		'br'       => [
			'class' => true,
		],
		'fields'   => [],
		'label'    => [
			'for' => true,
		],
		'select'   => [
			'class' => true,
			'name'  => true,
			'id'    => true,
		],
		'option'   => [
			'value'    => true,
			'selected' => true,
		],
		'div'      => [
			'id'     => true,
			'style'  => true,
			'class'  => true,
			'data-w' => true,
		],
		'a'        => [
			'id'      => true,
			'class'   => true,
			'href'    => true,
			'style'   => true,
			'title'   => true,
			'onclick' => true,
			'target'  => true,
		],
		'img'      => [
			'src'    => true,
			'width'  => true,
			'height' => true,
		],
		'ul'       => [
			'class' => true,
		],
		'li'       => [
			'class' => true,
		],
		'p'        => [
			'class' => true,
		],
		'h1'       => [
			'class' => true,
		],
		'h2'       => [
			'class' => true,
		],
		'nav'      => [
			'class'      => true,
			'aria-label' => true,
		],
		'span'     => [
			'class' => true,
			'style' => true,
		],
		'table'    => [
			'class' => true,
		],
		'thead'    => [
			'class' => true,
		],
		'tfoot'    => [
			'class' => true,
		],
		'tbody'    => [
			'class' => true,
		],
		'tr'       => [
			'class' => true,
		],
		'th'       => [
			'class' => true,
		],
		'td'       => [
			'class' => true,
		],
		'textarea' => [
			'name'  => true,
			'class' => true,
			'id'    => true,
			'rows'  => true,
			'cols'  => true,
		],
		'input'    => [
			'id'          => true,
			'class'       => true,
			'type'        => true,
			'name'        => true,
			'value'       => true,
			'placeholder' => true,
			'checked'     => true,
			'readonly'    => true,
			'disabled'    => true,
		],
		'script'   => [
			'src'   => true,
			'async' => true,
		],
		'svg'      => array(
			'class'   => true,
			'xmlns'   => true,
			'width'   => true,
			'height'  => true,
			'viewBox' => true,
			'fill'    => true,
		),
		'g'        => array(
			'clip-path' => true,
		),
		'path'     => array(
			'd'            => true,
			'stroke'       => true,
			'stroke-width' => true,
		),
		'defs'     => true,
		'clipPath' => array(
			'id' => true,
		),
		'rect'     => array(
			'width'  => true,
			'height' => true,
			'rx'     => true,
			'fill'   => true,
		),
	];

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $plugin_version;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Submenu slug.
	 *
	 * @var string
	 */
	private $sub_menu_slug;

	/**
	 * Submenu page title.
	 *
	 * @var string
	 */
	private $sub_page_title;

	/**
	 * Plugin prefix.
	 *
	 * @var string
	 */
	private $plugin_prefix;

	/**
	 * Sections array.
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $sections_array = array();

	/**
	 * Sections array.
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $header_menu_array = array();

	/**
	 * Fields array.
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $fields_array = array();

	/**
	 * Sidebar card array.
	 *
	 * @var array $sidebar_cards
	 */
	private $sidebar_cards = [];

	private $enable_blank_mode = false;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $plugin_version Pluign version.
	 * @param string $plugin_slug Plugin slug.
	 *
	 * @since  1.0.0
	 */
	public function __construct(string $plugin_name = 'WPOSA', string $plugin_version = '0.1', string $plugin_slug = 'WPOSA', string $plugin_prefix = 'WPOSA', $sub_menu_slug = '', $sub_page_title = '')
	{
		$this->plugin_name    = $plugin_name;
		$this->plugin_version = $plugin_version;
		$this->plugin_slug    = $plugin_slug;
		$this->plugin_prefix  = $plugin_prefix;
		$this->sub_page_title = $sub_page_title;
		$this->sub_menu_slug  = $sub_menu_slug;
	}

	public function enable_blank_mode()
	{
		$this->enable_blank_mode = true;

		return $this;
	}

	public function get_prefix(): string
	{
		return $this->plugin_prefix;
	}

	public function setup_hooks()
	{
		// Enqueue the admin scripts.
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

		// Hook it up.
		add_action('admin_init', array($this, 'admin_init'));

		// Menu.
		add_action('admin_menu', array($this, 'admin_menu'));

		// Ajax.
		add_action('wp_ajax_' . Utils::get_plugin_prefix() . '_reset_form', [$this, 'reset_form']);
	}

	/**
	 * Admin Scripts.
	 *
	 * @since 1.0.0
	 */
	public function admin_scripts()
	{
		global $wp_version;

		// jQuery is needed.
		wp_enqueue_script('jquery');

		// Color Picker.
		wp_enqueue_script(
			'iris',
			admin_url('js/iris.min.js'),
			array('jquery-ui-draggable', 'jquery-ui-slider', 'jquery-touch-punch'),
			$wp_version,
			true
		);
	}


	/**
	 * Set Sections.
	 *
	 * @param array $sections
	 *
	 * @since 1.0.0
	 */
	public function set_sections($sections)
	{
		// Bail if not array.
		if ( ! is_array($sections)) {
			return false;
		}

		// Assign to the sections array.
		$this->sections_array = $sections;

		return $this;
	}


	/**
	 * Add a single section.
	 *
	 * @param array $menu
	 *
	 * @return false|WPOSA
	 */
	public function add_header_menu($menu)
	{
		// Bail if not array.
		if ( ! is_array($menu)) {
			return false;
		}

		$menu['id'] = $this->get_prefix() . '_' . $menu['id'];

		// Assign the section to sections array.
		$this->header_menu_array[] = $menu;

		return $this;
	}


	/**
	 * Add a single section.
	 *
	 * @param array $section
	 *
	 * @since 1.0.0
	 */
	public function add_section($section)
	{
		// Bail if not array.
		if ( ! is_array($section)) {
			return false;
		}

		$section['id'] = $this->get_prefix() . '_' . $section['id'];
		if (isset($section['header_menu_id'])) {
			$section['header_menu_id'] = $this->get_prefix() . '_' . $section['header_menu_id'];
		}
		// Assign the section to sections array.
		$this->sections_array[] = $section;

		return $this;
	}


	/**
	 * Set Fields.
	 *
	 * @since 1.0.0
	 */
	public function set_fields($fields)
	{
		// Bail if not array.
		if ( ! is_array($fields)) {
			return false;
		}

		// Assign the fields.
		$this->fields_array = $fields;

		return $this;
	}


	/**
	 * Add a single field.
	 *
	 * @since 1.0.0
	 */
	public function add_field($section, $field_array)
	{
		// Set the defaults
		$defaults = array(
			'id'   => '',
			'name' => '',
			'desc' => '',
			'type' => 'text',
		);

		// Combine the defaults with user's arguments.
		$arg = wp_parse_args($field_array, $defaults);

		// Each field is an array named against its section.
		$this->fields_array[$this->get_prefix() . '_' . $section][] = $arg;

		return $this;
	}

	/**
	 * Add sidebar cards.
	 *
	 * @param array $card
	 *
	 * @return $this
	 */
	public function add_sidebar_card(array $card): WPOSA
	{
		$this->sidebar_cards[] = $card;

		return $this;
	}

	/**
	 * Remove or empty out all sidebar cards.
	 *
	 * @return $this
	 */
	public function remove_all_sidebar_cards(): WPOSA
	{
		$this->sidebar_cards = [];

		return $this;
	}

	public function get_sidebar_cards()
	{
		return $this->sidebar_cards;
	}

	public function get_sidebar_cards_total()
	{
		return count($this->get_sidebar_cards());
	}

	private function convert_array_to_attributes(array $args): string
	{
		$result = [];

		if (count($args)) {
			foreach ($args as $attr_key => $attr_value) {
				if ($attr_value === true || $attr_value === false) {
					if ($attr_value === true) {
						$result[] = esc_attr($attr_key);
					}
				} else {
					$result[] = sprintf(
						'%s="%s"',
						esc_attr($attr_key),
						esc_attr($attr_value)
					);
				}
			}
		}

		return implode(' ', $result);
	}

	/**
	 * @return string
	 */
	public function get_active_header_menu()
	{
		return ! empty($_GET['wposa-menu']) ?
			sanitize_text_field($_GET['wposa-menu']) :
			($this->header_menu_array[0]['id'] ?? '');
	}

	/**
	 * Initialize API.
	 *
	 * Initializes and registers the settings sections and fields.
	 * Usually this should be called at `admin_init` hook.
	 *
	 * @since  1.0.0
	 */
	function admin_init()
	{
		if (isset($_REQUEST['action'], $_REQUEST['mihdan_index_now_options_save']) && $_REQUEST['action'] == 'update' && current_user_can('manage_options')) {

			$option_page = ! empty($_REQUEST['option_page']) ? sanitize_text_field($_REQUEST['option_page']) : '';

			check_admin_referer($option_page . '-options');

			foreach ($_POST as $k => $v) {

				if (strstr($k, 'submit_') !== false) {
					$name = str_replace('submit_', '', $k);

					$db_options = get_option($name, []);

					$db_options = ! is_array($db_options) ? [] : $db_options;

					$value = array_replace($db_options, wp_unslash(Utils::clean_data($_POST[$name])));

					update_option($name, $value);

					wp_safe_redirect(Utils::get_current_url_query_string());
					exit;
				}
			}

		}
		/**
		 * Register the sections.
		 *
		 * Sections array is like this:
		 *
		 * $sections_array = array (
		 *   $section_array,
		 *   $section_array,
		 *   $section_array,
		 * );
		 *
		 * Section array is like this:
		 *
		 * $section_array = array (
		 *   'id'    => 'section_id',
		 *   'title' => 'Section Title'
		 * );
		 *
		 * @since 1.0.0
		 */
		foreach ($this->sections_array as $section) {

			if (get_option($section['id']) === false) {
				// Add a new field as section ID.
				add_option($section['id'], '', '', false);
			}

			// Deals with sections description.
			if (isset($section['desc']) && ! empty($section['desc'])) {
				// Build HTML.
				$section['desc'] = '<div class="inside wposa-section-description">' . wp_kses($section['desc'], self::ALLOWED_HTML) . '</div>';

				// Create the callback for description.
				$callback = function () use ($section) {
					echo wp_kses(str_replace('"', '\"', $section['desc']), self::ALLOWED_HTML);
				};

			} elseif (isset($section['callback'])) {
				$callback = $section['callback'];
			} else {
				$callback = null;
			}

			/**
			 * Add a new section to a settings page.
			 *
			 * @param string $id
			 * @param string $title
			 * @param callable $callback
			 * @param string $page | Page is same as section ID.
			 *
			 * @since 1.0.0
			 */
			add_settings_section($section['id'], $section['title'] ?? '', $callback, $section['id']);
		} // foreach ended.
		/**
		 * Register settings fields.
		 *
		 * Fields array is like this:
		 *
		 * $fields_array = array (
		 *   $section => $field_array,
		 *   $section => $field_array,
		 *   $section => $field_array,
		 * );
		 *
		 *
		 * Field array is like this:
		 *
		 * $field_array = array (
		 *   'id'   => 'id',
		 *   'name' => 'Name',
		 *   'type' => 'text',
		 * );
		 *
		 * @since 1.0.0
		 */
		foreach ($this->fields_array as $section => $field_array) {
			foreach ($field_array as $field) {
				// ID.
				$id = isset($field['id']) ? $field['id'] : false;

				// Type.
				$type = isset($field['type']) ? $field['type'] : 'text';

				// Name.
				$name = isset($field['name']) ? $field['name'] : 'No Name Added';

				// Label for.
				$label_for = "{$section}[{$field['id']}]";

				// Description.
				$description = isset($field['desc']) ? $field['desc'] : '';

				// Size.
				$size = isset($field['size']) ? $field['size'] : null;

				// Options.
				$options = isset($field['options']) ? $field['options'] : '';

				// Standard default value.
				$default = isset($field['default']) ? $field['default'] : '';

				// Standard default placeholder.
				$placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';

				// Readonly attribute.
				$readonly = $field['readonly'] ?? false;

				// Sanitize Callback.
				$sanitize_callback = isset($field['sanitize_callback']) ? $field['sanitize_callback'] : '';

				$help_tab = $field['help_tab'] ?? '';
				$class    = $field['class'] ?? "wposa-form-table__row wposa-form-table__row_type_{$type} wposa-form-table__row_{$section}_{$id}";

				$args = array(
					'id'                => $id,
					'type'              => $type,
					'name'              => $name,
					'label_for'         => $label_for,
					'desc'              => $description,
					'section'           => $section,
					'size'              => $size,
					'options'           => $options,
					'std'               => $default,
					'placeholder'       => $placeholder,
					'sanitize_callback' => $sanitize_callback,
					'attributes'        => [
						'readonly' => $readonly,
					],
					'class'             => $class,
				);

				if (isset($field['value'])) {
					$args['value'] = $field['value'];
				}

				if (isset($field['attributes'])) {
					$args['attributes'] = array_merge($args['attributes'], $field['attributes']);
				}

				if ( ! empty($field['button_class'])) {
					$args['button_class'] = $field['button_class'];
				}

				if ($help_tab) {
					$name .= $this->show_help_tab_toggle($help_tab);
				}

				/**
				 * Add a new field to a section of a settings page.
				 *
				 * @param string $id
				 * @param string $title
				 * @param callable $callback
				 * @param string $page
				 * @param string $section = 'default'
				 * @param array $args = array()
				 *
				 * @since 1.0.0
				 */
				// @param string 	$id
				$field_id = $section . '[' . $field['id'] . ']';

				add_settings_field(
					$field_id,
					$name,
					array($this, 'callback_' . $type),
					$section,
					$section,
					$args
				);
			} // foreach ended.
		} // foreach ended.

		// Creates our settings in the fields table.
		foreach ($this->sections_array as $section) {
			/**
			 * Registers a setting and its sanitization callback.
			 *
			 * @param string $field_group | A settings group name.
			 * @param string $field_name | The name of an option to sanitize and save.
			 * @param callable $sanitize_callback = ''
			 *
			 * @since 1.0.0
			 */
			register_setting($section['id'], $section['id'], array($this, 'sanitize_fields'));
		} // foreach ended.

	} // admin_init() ended.


	/**
	 * Sanitize callback for Settings API fields.
	 *
	 * @since 1.0.0
	 */
	public function sanitize_fields($fields)
	{

		if (is_array($fields)) {
			foreach ($fields as $field_slug => $field_value) {
				$sanitize_callback = $this->get_sanitize_callback($field_slug);

				// If callback is set, call it.
				if ($sanitize_callback) {
					$fields[$field_slug] = call_user_func($sanitize_callback, $field_value);
					continue;
				}
			}
		}

		return $fields;
	}


	/**
	 * Get sanitization callback for given option slug
	 *
	 * @param string $slug option slug.
	 *
	 * @return mixed string | bool false
	 * @since  1.0.0
	 */
	function get_sanitize_callback($slug = '')
	{
		if (empty($slug)) {
			return false;
		}

		// Iterate over registered fields and see if we can find proper callback.
		foreach ($this->fields_array as $section => $field_array) {
			foreach ($field_array as $field) {
				if ($field['name'] != $slug) {
					continue;
				}

				// Return the callback name.
				return isset($field['sanitize_callback']) && is_callable($field['sanitize_callback']) ? $field['sanitize_callback'] : false;
			}
		}

		return false;
	}


	/**
	 * Get field description for display
	 *
	 * @param array $args settings field args
	 */
	public function get_field_description($args)
	{
		if ( ! empty($args['desc'])) {
			$desc = sprintf(
				'<p class="description">%s</p>',
				is_callable($args['desc'])
					? call_user_func($args['desc'])
					: $args['desc']
			);
		} else {
			$desc = '';
		}

		return wp_kses($desc, self::ALLOWED_HTML);
	}


	/**
	 * Displays a title field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_title($args)
	{
		$value = esc_attr($this->get_option($args['id'], $args['section'], $args['std']));

		echo esc_html($value);
	}


	/**
	 * Displays a text field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_text($args)
	{
		$value = isset($args['value']) ? esc_attr($args['value']) : esc_attr($this->get_option($args['id'], $args['section'], $args['std']));
		$size  = isset($args['size']) && ! is_null($args['size']) ? $args['size'] : 'regular';
		$type  = isset($args['type']) ? $args['type'] : 'text';

		$attributes = $this->convert_array_to_attributes($args['attributes']);

		$html = sprintf(
			'<input type="%1$s" class="%2$s-text" id="%3$s[%4$s]" name="%3$s[%4$s]" value="%5$s" placeholder="%6$s" %7$s/>',
			esc_attr($type),
			esc_attr($size),
			esc_attr($args['section']),
			esc_attr($args['id']),
			esc_attr($value),
			esc_attr($args['placeholder']),
			$attributes
		);

		$html .= $this->get_field_description($args);

		echo $html;
	}


	/**
	 * Displays a url field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_url($args)
	{
		$this->callback_text($args);
	}

	/**
	 * Displays a number field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_number($args)
	{
		$this->callback_text($args);
	}

	/**
	 * Displays a checkbox for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_checkbox($args)
	{

		$value = esc_attr($this->get_option($args['id'], $args['section'], $args['std']));
		$html  = '<fieldset>';
		$html  .= sprintf('<label for="wposa-%1$s[%2$s]">', $args['section'], $args['id']);
		$html  .= sprintf('<input type="hidden" name="%1$s[%2$s]" value="off" />', $args['section'], $args['id']);
		$html  .= sprintf('<input type="checkbox" class="checkbox" id="wposa-%1$s[%2$s]" name="%1$s[%2$s]" value="on" %3$s />', $args['section'], $args['id'], checked($value, 'on', false));
		$html  .= sprintf('%1$s</label>', $args['desc']);
		$html  .= '</fieldset>';

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	/**
	 * Displays a iOS switch checkbox for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_switch($args)
	{

		$value = esc_attr($this->get_option($args['id'], $args['section'], $args['std']));
		$html  = '<fieldset>';
		$html  .= sprintf('<label for="wposa-%1$s[%2$s]">', $args['section'], $args['id']);
		$html  .= sprintf('<input type="hidden" name="%1$s[%2$s]" value="off" />', $args['section'], $args['id']);
		$html  .= sprintf('<input type="checkbox" class="wposa-field wposa-field--switch" id="wposa-%1$s[%2$s]" name="%1$s[%2$s]" value="on" %3$s />', $args['section'], $args['id'], checked($value, 'on', false));
		$html  .= sprintf('%1$s</label>', $args['desc']);
		$html  .= '</fieldset>';

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	/**
	 * Displays a multicheckbox a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_multicheck($args)
	{

		$value = $this->get_option($args['id'], $args['section'], $args['std']);

		$html = '<fieldset>';
		$html .= sprintf(
			'<input type="hidden" name="%s[%s][]" value="" />',
			$args['section'], $args['id']
		);
		foreach ($args['options'] as $key => $label) {
			$checked = isset($value[$key]) ? $value[$key] : '0';
			$html    .= sprintf('<label for="wposa-%1$s[%2$s][%3$s]">', $args['section'], $args['id'], $key);
			$html    .= sprintf('<input type="checkbox" class="checkbox" id="wposa-%1$s[%2$s][%3$s]" name="%1$s[%2$s][%3$s]" value="%3$s" %4$s />', $args['section'], $args['id'], $key, checked($checked, $key, false));
			$html    .= sprintf('%1$s</label><br>', $label);
		}
		$html .= $this->get_field_description($args);
		$html .= '</fieldset>';

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	/**
	 * Displays a multicheckbox a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_radio($args)
	{

		$value = $this->get_option($args['id'], $args['section'], $args['std']);

		$html = '<fieldset>';
		foreach ($args['options'] as $key => $label) {
			$html .= sprintf('<label for="wposa-%1$s[%2$s][%3$s]">', $args['section'], $args['id'], $key);
			$html .= sprintf('<input type="radio" class="radio" id="wposa-%1$s[%2$s][%3$s]" name="%1$s[%2$s]" value="%3$s" %4$s />', $args['section'], $args['id'], $key, checked($value, $key, false));
			$html .= sprintf('%1$s</label><br>', $label);
		}
		$html .= $this->get_field_description($args);
		$html .= '</fieldset>';

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	/**
	 * Displays a selectbox for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_select($args)
	{

		$value = esc_attr($this->get_option($args['id'], $args['section'], $args['std']));
		$size  = isset($args['size']) && ! is_null($args['size']) ? $args['size'] : 'regular';

		$html = sprintf('<select class="%1$s" name="%2$s[%3$s]" id="%2$s[%3$s]">', $size, $args['section'], $args['id']);
		foreach ($args['options'] as $key => $label) {
			$html .= sprintf('<option value="%s"%s>%s</option>', $key, selected($value, $key, false), $label);
		}
		$html .= '</select>';
		$html .= $this->get_field_description($args);

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	/**
	 * Displays a textarea for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_textarea($args)
	{

		$value = esc_textarea($this->get_option($args['id'], $args['section'], $args['std']));
		$size  = isset($args['size']) && ! is_null($args['size']) ? $args['size'] : 'regular';

		$html = sprintf('<textarea rows="5" cols="55" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]">%4$s</textarea>', $size, $args['section'], $args['id'], $value);
		$html .= $this->get_field_description($args);

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	/**
	 * Displays a textarea for a settings field
	 *
	 * @param array $args settings field args.
	 */
	function callback_html($args)
	{
		echo is_callable($args['desc']) ? call_user_func($args['desc']) : $args['desc'];
	}

	/**
	 * Displays a file upload field for a settings field
	 *
	 * @param array $args settings field args.
	 */
	function callback_file($args)
	{

		$value = esc_attr($this->get_option($args['id'], $args['section'], $args['std']));
		$size  = isset($args['size']) && ! is_null($args['size']) ? $args['size'] : 'regular';
		$id    = $args['section'] . '[' . $args['id'] . ']';
		$label = isset($args['options']['button_label']) ?
			$args['options']['button_label'] :
			__('Choose File');

		$html = sprintf('<input type="text" class="%1$s-text wpsa-url" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value);
		$html .= '<input type="button" class="button wpsa-browse" value="' . $label . '" />';
		$html .= $this->get_field_description($args);

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	/**
	 * Displays an image upload field with a preview
	 *
	 * @param array $args settings field args.
	 */
	function callback_image($args)
	{

		$value = esc_attr($this->get_option($args['id'], $args['section'], $args['std']));
		$size  = isset($args['size']) && ! is_null($args['size']) ? $args['size'] : 'regular';
		$id    = $args['section'] . '[' . $args['id'] . ']';
		$label = isset($args['options']['button_label']) ?
			$args['options']['button_label'] :
			__('Choose Image');

		$html = sprintf('<input type="text" class="%1$s-text wpsa-url" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value);
		$html .= '<input type="button" class="button wpsa-browse" value="' . $label . '" />';
		$html .= $this->get_field_description($args);
		$html .= '<p class="wpsa-image-preview"><img src=""/></p>';

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	/**
	 * Displays a password field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_password($args)
	{

		$value = esc_attr($this->get_option($args['id'], $args['section'], $args['std']));
		$size  = isset($args['size']) && ! is_null($args['size']) ? $args['size'] : 'regular';

		$html = sprintf('<input type="password" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value);
		$html .= $this->get_field_description($args);

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	/**
	 * Displays a color picker field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_color($args)
	{

		$value = esc_attr($this->get_option($args['id'], $args['section'], $args['std'], $args['placeholder']));
		$size  = isset($args['size']) && ! is_null($args['size']) ? $args['size'] : 'regular';

		$html = sprintf('<input type="text" class="%1$s-text color-picker" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s" data-default-color="%5$s" placeholder="%6$s" />', $size, $args['section'], $args['id'], $value, $args['std'], $args['placeholder']);
		$html .= $this->get_field_description($args);

		echo wp_kses($html, self::ALLOWED_HTML);
	}


	/**
	 * Displays a separator field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_separator($args)
	{
		?>
		<div class="wposa-field wposa-field--separator"></div>
		<?php
	}

	/**
	 * Displays a Button field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_button($args)
	{
		$value = $args['placeholder'] ?? __('Submit');
		$class = $args['button_class'] ?? 'button-secondary';
		$id    = $args['id'] ?? time();
		?>
		<input
			type="button"
			id="<?php echo esc_attr($id); ?>"
			value="<?php echo esc_attr($value); ?>"
			class="button <?php echo esc_attr($class); ?>"
		/>
		<?php echo wp_kses($this->get_field_description($args), self::ALLOWED_HTML); ?>
		<?php
	}

	/**
	 * Displays a Button field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_hidden($args)
	{
		$value = $this->get_option($args['id'], $args['section'], $args['std']);
		?>
		<input
			type="hidden"
			name="<?php echo esc_attr($args['section']); ?>[<?php echo esc_attr($args['id']); ?>]"
			value="<?php echo esc_attr($value); ?>"
		/>
		<?php
	}

	/**
	 * Get the value of a settings field
	 *
	 * @param string $option settings field name.
	 * @param string $section the section name this field belongs to.
	 * @param mixed $default default text if it's not found.
	 *
	 * @return mixed
	 */
	public function get_option(string $option, string $section, $default = '')
	{
		$section = str_replace($this->get_prefix() . '_', '', $section);
		$options = get_option($this->get_prefix() . '_' . $section);

		if (isset($options[$option])) {
			return apply_filters('wposa/get_option', $options[$option], $option, $section, $default);
		}

		return apply_filters('wposa/get_option', $default, $option, $section, $default);
	}

	public function set_option(string $option, $value, string $section): bool
	{
		$name = $this->get_prefix() . '_' . $section;

		// Get option.
		$options = get_option($name);

		if ( ! $options) {
			return false;
		}

		// Update option.
		$options[$option] = $value;

		return update_option($name, $options);
	}

	private function getMenuIcon()
	{
		return 'data:image/svg+xml;base64,' . base64_encode('<svg viewBox="0 0 109.05 109" xmlns="http://www.w3.org/2000/svg"><g fill="#fff"><path d="m76.41 0h-68.28c-2.24 0-4.27.91-5.74 2.38s-2.39 3.5-2.39 5.74v78.24c0 2.23.92 4.25 2.39 5.72v.02c1.47 1.46 3.5 2.38 5.74 2.38h6.43v6.4c0 2.23.92 4.27 2.39 5.74h.01c1.47 1.46 3.5 2.38 5.73 2.38h78.23c2.24 0 4.27-.92 5.74-2.38 1.47-1.47 2.39-3.51 2.39-5.74v-68.42zm27.63 100.88c0 .82-.33 1.62-.91 2.2-.6.6-1.38.92-2.21.92h-78.23c-.84 0-1.63-.33-2.22-.93-.57-.57-.9-1.37-.9-2.19v-78.24c0-.84.34-1.64.92-2.22s1.36-.9 2.2-.9h63.43v17.26c0 .73.6 1.33 1.33 1.33h16.59z" fill-rule="evenodd"/><path d="m71.66 79.72c-1.34 0-2.43 1.09-2.43 2.43v3.33c0 1.34 1.09 2.42 2.43 2.42s2.43-1.08 2.43-2.42v-3.33c0-1.34-1.09-2.43-2.43-2.43z"/><path d="m79.08 71.05c-1.34 0-2.42 1.08-2.42 2.42v12.01c0 1.34 1.08 2.42 2.42 2.42s2.43-1.08 2.43-2.42v-12.01c0-1.34-1.08-2.42-2.43-2.42z"/><path d="m64.24 76.25c-1.34 0-2.43 1.09-2.43 2.43v6.8c0 1.34 1.09 2.42 2.43 2.42s2.42-1.08 2.42-2.42v-6.8c0-1.34-1.08-2.43-2.42-2.43z"/><path d="m87.45 40.11c-1.84 0-3.33-1.5-3.33-3.33v-15.26h-61.43c-.3 0-.59.11-.8.32s-.32.5-.32.8v78.24c0 .29.12.57.32.78.22.22.5.34.8.34h78.23c.29 0 .57-.12.79-.34s.33-.49.33-.78v-60.77zm-59.53.56h48.78c1.19 0 2.15.96 2.15 2.15s-.96 2.15-2.15 2.15h-48.77c-1.19 0-2.15-.96-2.15-2.15s.97-2.15 2.15-2.15zm14.59 47.53h-14.59c-1.18 0-2.15-.96-2.15-2.15s.97-2.15 2.15-2.15h14.59c1.19 0 2.15.96 2.15 2.15s-.96 2.15-2.15 2.15zm6.95-14.4h-21.54c-1.18 0-2.15-.97-2.15-2.16s.97-2.15 2.15-2.15h21.54c1.19 0 2.15.96 2.15 2.15s-.96 2.16-2.15 2.16zm-21.54-14.41c-1.18 0-2.15-.97-2.15-2.15s.97-2.16 2.15-2.16h27.08c1.19 0 2.15.97 2.15 2.16s-.96 2.15-2.15 2.15zm71.11 39.04c-.79.89-2.14.98-3.04.19l-6.29-5.54c-3.63 3.04-8.31 4.88-13.42 4.88-5.77 0-11.01-2.35-14.8-6.14-3.78-3.78-6.13-9.02-6.13-14.79s2.3-10.9 6.02-14.68l.11-.12c3.79-3.79 9.03-6.13 14.8-6.13s11.01 2.34 14.8 6.13 6.13 9.03 6.13 14.8c0 4.89-1.69 9.39-4.51 12.95l6.13 5.41c.9.79.98 2.15.2 3.04z"/><path d="m86.51 65.84c-1.34 0-2.43 1.09-2.43 2.43v17.21c0 1.34 1.09 2.42 2.43 2.42s2.43-1.08 2.43-2.42v-17.21c0-1.34-1.09-2.43-2.43-2.43z"/></g></svg>');
	}

	/**
	 * Add submenu page to the Settings main menu.
	 */
	public function admin_menu()
	{
		if ( ! empty($this->sub_menu_slug)) {

			$hook = add_submenu_page(
				$this->plugin_slug,
				$this->sub_page_title,
				$this->sub_page_title,
				'manage_options',
				$this->sub_menu_slug,
				array($this, 'plugin_page')
			);

		} else {

			$hook = add_menu_page(
				$this->plugin_name,
				$this->plugin_name,
				'manage_options',
				$this->plugin_slug,
				array($this, 'plugin_page'),
				$this->getMenuIcon()
			);

			add_submenu_page(
				$this->plugin_slug,
				$this->plugin_name,
				esc_html__('Settings', 'mihdan-index-now'),
				'manage_options',
				$this->plugin_slug,
				array($this, 'plugin_page')
			);
		}

		add_action("load-" . $hook, function () {
			do_action('wpposa_load_menu_hook', $this->sub_menu_slug, $this->plugin_slug);
		});
	}

	public function plugin_page()
	{
		$this->css();

		$review_url  = 'https://wordpress.org/support/plugin/mihdan-index-now/reviews/?filter=5#new-post';
		$upgrade_url = 'https://crawlwp.com/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=crawlwp-header-top';

		$active_header_menu_id = $this->get_active_header_menu();

		$flag = false;

		foreach ($this->sections_array as $section) {

			if ( ! empty($active_header_menu_id)) {
				$header_menu_id = $section['header_menu_id'] ?? '';
				if ($header_menu_id != $active_header_menu_id) continue;
			}

			$flag = true;
		}

		if ( ! $flag) return;

		?>
		<div class="wposa">
			<div class="wposa-new-header">
				<div class="wposa-branding">
					<img class="wposa-logo" src="<?php echo esc_url(Utils::get_plugin_asset_url('images/icons/crawlwp-favicon.svg')); ?>" width="80" alt=""/>
					<h1><?php echo $this->sub_page_title ?></h1>
				</div>

				<nav class="wposa-tabs">
					<?php foreach ($this->header_menu_array as $menu) :
						$url = esc_url(remove_query_arg(wp_removable_query_args(), add_query_arg(['wposa-menu' => $menu['id']])));
						$active_class = $this->get_active_header_menu() == $menu['id'] ? ' wposa-tab-active' : '';
						?>
						<a href="<?php echo $url ?>" class="wposa-tab<?php echo $active_class; ?>"><?php echo $menu['title'] ?></a>
					<?php endforeach; ?>
				</nav>

				<div class="wposa-header-right">
					<a href="<?php echo $review_url ?>" target="_blank" class="wposa-header-action review">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24">
							<path fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="2.5" d="m12 2l3.104 6.728l7.358.873l-5.44 5.03l1.444 7.268L12 18.28L5.534 21.9l1.444-7.268L1.538 9.6l7.359-.873L12 2Z"></path>
						</svg>
						<?php esc_html_e('Review', 'mihdan-index-now'); ?>
					</a> <a href="https://crawlwp.com/docs/" target="_blank" class="wposa-header-action translate">
						<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 56 56">
							<path fill="currentColor" d="M15.555 53.125h24.89c4.852 0 7.266-2.461 7.266-7.336V24.508c0-3.024-.328-4.336-2.203-6.258L32.57 5.102c-1.78-1.829-3.234-2.227-5.882-2.227H15.555c-4.828 0-7.266 2.484-7.266 7.36v35.554c0 4.898 2.438 7.336 7.266 7.336m.187-3.773c-2.414 0-3.68-1.29-3.68-3.633V10.305c0-2.32 1.266-3.657 3.704-3.657h10.406v13.618c0 2.953 1.5 4.406 4.406 4.406h13.36v21.047c0 2.343-1.243 3.633-3.68 3.633ZM31 21.132c-.914 0-1.29-.374-1.29-1.312V7.375l13.5 13.758Z"/>
						</svg>
						<?php esc_html_e('Documentation', 'mihdan-index-now'); ?>
					</a>
					<?php if ( ! defined('CRAWLWP_DETACH_LIBSODIUM')) { ?>
						<a href="<?php echo $upgrade_url; ?>" target="_blank" class="button-primary plugin-upgrade">
							<?php esc_html_e('CrawlWP Premium', 'mihdan-index-now'); ?>
						</a>
					<?php } ?>
				</div>
			</div>
			<div class="wrap">
				<?php // useful to keep admin notices at the top
				?>
				<h2 style="display:none">CrawlWP</h2>
				<?php if ($this->get_sidebar_cards_total() === 0) : ?>
					<?php $this->show_forms(); ?>
				<?php else : ?>
					<div class="wposa__grid">
						<div class="wposa__column wposa__content">
							<?php $this->show_forms(); ?>
						</div>
						<?php if ($this->get_sidebar_cards_total()) : ?>
							<div class="wposa__column" style="padding-right: 10px">
								<?php foreach ($this->get_sidebar_cards() as $card) : ?>
									<div class="card wposa-card wposa-card--<?php echo esc_attr($this->get_prefix()) ?>_<?php echo esc_attr($card['id']) ?>">
										<?php if ( ! empty($card['title'])) : ?>
											<h2 class="title wposa__title wposa__title--h2 wposa-card__title"><?php echo esc_html($card['title']) ?></h2>
										<?php endif; ?>
										<div class="wposa-card__content">
											<?php echo wp_kses($card['desc'], self::ALLOWED_HTML); ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Show navigations as tab
	 *
	 * Shows all the settings section labels as tab
	 */
	function show_navigation()
	{
		$html = sprintf(
			'<nav class="wposa-nav-tab-wrapper" aria-label="%s">',
			esc_html__('Secondary Navigation', 'wposa')
		);

		foreach ($this->sections_array as $tab) {
			if (isset($tab['disabled']) && $tab['disabled'] === true) {
				if (isset($tab['badge'])) {
					$html .= sprintf('<span class="wposa-nav-tab wposa-nav-tab--disabled" id="%1$s-tab">%2$s <span class="wposa-badge">%3$s</span></span>', $tab['id'], $tab['title'], $tab['badge']);
				} else {
					$html .= sprintf('<span class="wposa-nav-tab wposa-nav-tab--disabled" id="%1$s-tab">%2$s</span>', $tab['id'], $tab['title']);
				}
			} else {
				$html .= sprintf('<a href="#%1$s" class="wposa-nav-tab" id="%1$s-tab">%2$s</a>', $tab['id'], $tab['title']);
			}
		}

		$html .= '</nav>';

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	public function blank_mode_do_settings_sections($page)
	{

		global $wp_settings_sections, $wp_settings_fields;

		foreach ((array)$wp_settings_sections[$page] as $section) {

			$section = $section['id'];

			foreach ((array)$wp_settings_fields[$page][$section] as $field) {

				call_user_func($field['callback'], $field['args']);
			}
		}
	}

	public function enable_blank_mode_show_forms($default)
	{
		?>
		<div>
			<h2 style="display:none"><?php echo $this->sub_page_title ?></h2>
			<?php foreach ($this->sections_array as $form) :
				$form = wp_parse_args($form, $default);
				$this->blank_mode_do_settings_sections($form['id']);
			endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Show the section settings forms
	 *
	 * This function displays every sections in a different form
	 */
	function show_forms()
	{
		$default = array(
			'label_submit' => null,
			'submit_type'  => 'primary',
			'wrap'         => false,
			'attributes'   => null,
			'reset_button' => true,
		);

		if ($this->enable_blank_mode):
			$this->enable_blank_mode_show_forms($default);
		else:
			$this->show_navigation();
			?>
			<div class="metabox-holder">
				<?php foreach ($this->sections_array as $form) : ?>
					<?php
					$form = wp_parse_args($form, $default);
					?>
					<!-- style="display: none;" -->
					<div id="<?php echo esc_attr($form['id']); ?>" class="group">
						<form class="wposa__form" method="post">
							<?php
							do_action('wsa_form_top_' . $form['id'], $form);
							settings_fields($form['id']);
							do_settings_sections($form['id']);
							do_action('wsa_form_bottom_' . $form['id'], $form);
							?>
							<div class="wposa-footer">
								<div class="wposa-footer__column wposa-footer__column--left">
									<input type="hidden" name="mihdan_index_now_options_save" value="true">
									<?php submit_button($form['label_submit'], $form['submit_type'], 'submit_' . $form['id'], $form['wrap'], $form['attributes']); ?>
								</div>
								<div class="wposa-footer__column wposa-footer__column--right">
									<?php if ($form['reset_button']) : ?>
										<input type="button"
											   class="button button-danger button-link"
											   data-section="<?php echo esc_attr($form['id']); ?>"
											   id="<?php echo esc_attr($form['id']); ?>_reset_form"
											   value="<?php echo esc_attr(__('Reset Form', 'mihdan-index-now')); ?>"
										/>
									<?php endif; ?>
								</div>
							</div>
						</form>
					</div>
				<?php endforeach; ?>
			</div>
		<?php
		endif;
		$this->script();
	}

	/**
	 * Show help tab toggle.
	 *
	 * @param string $tab_id Tab identified.
	 * @param string $tab_icon Tab icon.
	 */
	private function show_help_tab_toggle($tab_id, $tab_icon = '?')
	{
		$is_url = strstr($tab_id, 'http');
		$href = $is_url ? $tab_id : '#';
		$title = $is_url ? esc_attr__('Click to view help guide', 'mihdan-index-now') : esc_attr__('Click to show Help tab', 'mihdan-index-now');

		$class = 'wpsa-help-tab-toggle' . ($is_url ? ' is-url' : '');
		$target = $is_url ? '_blank' : '_self';
		ob_start();
		?>
		<a href="<?php echo $href ?>" target="<?php echo $target ?>" title="<?php echo $title ?>" class="<?php echo $class ?>" data-tab="<?php echo esc_attr($tab_id); ?>"><?php echo esc_html($tab_icon); ?></a>
		<?php
		return ob_get_clean();
	}

	/**
	 * Tabbable JavaScript codes & Initiate Color Picker
	 *
	 * This code uses localstorage for displaying active tabs
	 */
	public function script()
	{
		?>
		<script>
			(function ($) {

				$(document).ready(function () {

					const
						$show_settings_toggler = $('.show-settings'),
						$help = $('.wpsa-help-tab-toggle').not('.is-url'),
						wp = window.wp;

					$help.on(
						'click',
						function () {
							var $this = $(this);
							var tab = '#tab-link-<?php echo esc_js(MIHDAN_INDEX_NOW_PREFIX); ?>_' + $this.data('tab');

							if ($show_settings_toggler.attr('aria-expanded') === 'false') {
								$show_settings_toggler.trigger('click');
							}

							$(tab).find('a').trigger('click');
						}
					);

					//Initiate Color Picker.
					$('.color-picker').iris();

					// Switches option sections
					$('.group').hide();
					var activetab = '';
					if ('undefined' != typeof localStorage) {
						activetab = localStorage.getItem('activetab');
					}
					if ('' != activetab && $(activetab).length) {
						$(activetab).fadeIn();
					} else {
						$('.group:first').fadeIn();
					}
					$('.group .collapsed').each(function () {
						$(this)
							.find('input:checked')
							.parent()
							.parent()
							.parent()
							.nextAll()
							.each(function () {
								if ($(this).hasClass('last')) {
									$(this).removeClass('hidden');
									return false;
								}
								$(this)
									.filter('.hidden')
									.removeClass('hidden');
							});
					});

					if ('' != activetab && $(activetab + '-tab').length) {
						$(activetab + '-tab').addClass('wposa-nav-tab-active');
					} else {
						$('.wposa-nav-tab-wrapper a:first').addClass('wposa-nav-tab-active');
					}
					$('.wposa-nav-tab-wrapper a').click(function (evt) {
						$('.wposa-nav-tab-wrapper a').removeClass('wposa-nav-tab-active');
						$(this)
							.addClass('wposa-nav-tab-active')
							.blur();
						var clicked_group = $(this).attr('href');
						if ('undefined' != typeof localStorage) {
							localStorage.setItem('activetab', $(this).attr('href'));
						}
						$('.group').hide();
						$(clicked_group).fadeIn();
						evt.preventDefault();
					});

					$('.wpsa-browse').on('click', function (event) {
						event.preventDefault();

						var self = $(this);

						// Create the media frame.
						var file_frame = (wp.media.frames.file_frame = wp.media({
							title: self.data('uploader_title'),
							button: {
								text: self.data('uploader_button_text')
							},
							multiple: false
						}));

						file_frame.on('select', function () {
							attachment = file_frame
								.state()
								.get('selection')
								.first()
								.toJSON();

							self
								.prev('.wpsa-url')
								.val(attachment.url)
								.change();
						});

						// Finally, open the modal
						file_frame.open();
					});

					$('input.wpsa-url')
						.on('change keyup paste input', function () {
							var self = $(this);
							self
								.next()
								.parent()
								.children('.wpsa-image-preview')
								.children('img')
								.attr('src', self.val());
						})
						.change();

					var REDIRECT_URL = '<?php echo esc_url(admin_url('admin.php?page=' . Utils::get_plugin_slug())); ?>';
					var CODE_ENDPOINT = 'https://oauth.yandex.com/authorize?state=yandex-webmaster&response_type=code&force_confirm=yes&redirect_uri=' + REDIRECT_URL + '&client_id=';

					$('#button_get_token').on(
						'click',
						function () {
							var CLIENT_ID = document.getElementById('mihdan_index_now_yandex_webmaster[client_id]').value;

							window.location.href = CODE_ENDPOINT + CLIENT_ID;
						}
					);

					$('input:button[id$="_reset_form"]').on(
						'click',
						function () {
							var $button = $(this),
								$nonce = $(this).parents('form').find('#_wpnonce');

							if (confirm('<?php echo esc_attr(__('Are you sure?', 'mihdan-index-now')); ?>')) {
								wp.ajax.post(
									'<?php echo esc_html(Utils::get_plugin_prefix()); ?>_reset_form',
									{
										section: $button.data('section'),
										nonce: $nonce.val(),
									}
								).always(function (response) {
									if (response === 'ok') {
										document.location.reload();
									} else {
										console.log(response);
									}
								});
							}
						}
					);
				});

			})(jQuery);

		</script>
		<?php
	}

	public function css()
	{
		?>
		<style>
			#wpbody-content .wposa .metabox-holder {
				padding-left: 0;
			}

			.toplevel_page_mihdan-index-now #screen-meta-links {
				position: relative;
				z-index: 10;
			}

			.wposa .button-danger {
				color: #d63638;
				border-color: #d63638;
			}

			.wposa-logo {
				display: block;
			}

			#wpbody-content .metabox-holder {
				padding-top: 0px;
			}

			.wpsa-image-preview img {
				height: auto;
				max-width: 70px;
			}

			.wposa-field--separator {
				background: #ccc;
				border: 0;
				color: #ccc;
				height: 1px;
				position: absolute;
				left: 0;
				width: 99%;
			}

			.group .form-table input.color-picker {
				max-width: 100px;
			}

			.wpsa-help-tab-toggle {
				display: inline-block;
				width: 14px;
				height: 14px;
				line-height: 14px;
				text-align: center;
				border-radius: 50%;
				border: 2px solid #2271b1;
				cursor: help;
				font-size: 12px;
				vertical-align: text-bottom;
				user-select: none;
				text-decoration: none;
			}

			.wpsa-help-tab-toggle.is-url {
				cursor: pointer;
			}

			.wposa__grid {
				display: grid;
				grid-gap: 20px;
				grid-template-columns: auto 300px;
				min-height: 0;
				min-width: 0;
			}

			.wposa .description {
				max-width: 350px;
			}

			input.wposa-field--switch {
				position: relative;
				-webkit-appearance: none;
				appearance: none;
				outline: none;
				width: 40px;
				height: 20px;
				background-color: #ffffff;
				border: 1px solid #D9DADC;
				border-radius: 50px;
				box-shadow: inset -20px 0 0 0 #ffffff;
			}

			input.wposa-field--switch:before {
				display: none !important;
			}

			input.wposa-field--switch:after {
				content: "";
				position: absolute;
				top: 0;
				left: 1px;
				width: 18px;
				height: 18px;
				background-color: transparent;
				border-radius: 50%;
				box-shadow: 2px 0 6px rgba(0, 0, 0, 0.2);
				transition-property: left;
				transition-duration: 3s;
			}

			input.wposa-field--switch:checked {
				border-color: #135e96;
				box-shadow: inset 20px 0 0 0 #135e96;
			}

			input.wposa-field--switch:checked:after {
				left: auto;
				right: 1px;
				box-shadow: -2px 0px 3px rgba(0, 0, 0, 0.05);
			}

			input.wposa-field--switch:hover:after {
				/*box-shadow: 0 0 3px rgba(0,0,0,0.3);*/
			}

			.wposa-nav-tab--disabled {
				cursor: not-allowed;
			}

			.wposa-badge {
				font-size: 0.8em;
				background-color: #d63638;
				color: #fff;
				border-radius: 2px;
				padding: 0 5px;
				display: inline-block;
				font-weight: normal;
			}

			.wposa-section-description {
				max-width: 600px;
			}

			.wposa-form-table__row_type_hidden {
				display: none;
			}

			.wposa-form-table__row_type_number .regular-text {
				width: 50px;
			}

			.wposa-form-table__row_mihdan_index_now_logs_enable label,
			.wposa-form-table__row_mihdan_index_now_yandex_webmaster_enable label,
			.wposa-form-table__row_mihdan_index_now_google_webmaster_enable label,
			.wposa-form-table__row_mihdan_index_now_bing_webmaster_enable label,
			.wposa-form-table__row_mihdan_index_now_index_now_enable label {
				color: #135e96 !important;
			}

			.wrap-column--form form {
				max-width: 600px;
			}

			.wpsa-card img {
				display: block;
				border: 0;
			}

			.wposa__table {
				border: 1px solid #c3c4c7;
				border-collapse: collapse;
			}

			.wposa__table th {
				text-align: left;
				vertical-align: top;
				line-height: 1.2em;
			}

			.wposa__table th,
			.wposa__table td {
				padding: 7px;
			}

			.wposa-card--mihdan_index_now_wpshop {
				padding: 0;
				border: 0;
			}

			.wposa__table tr:nth-child(even) {
				background-color: #f0f0f1;
			}

			.wposa__table tr:nth-child(odd) {
				background-color: #fff;
			}

			.wposa-card--mihdan_index_now_rtfm {
				position: sticky;
				top: 50px;
			}

			.wposa-form-table__row_mihdan_index_now_plugins_plugins th {
				display: none;
			}

			.wposa-form-table__row_mihdan_index_now_plugins_plugins td {
				padding: 0;
			}

			.wposa-plugins {
				display: grid;
				grid-gap: 20px;
				grid-template-columns: repeat(2, 1fr);
			}

			.wposa-plugins a {
				text-decoration: none;
			}

			.wposa-plugins__item {
				border: 1px solid #c3c4c7;
				background: #fff;
			}

			.wposa-plugin {
				display: flex;
				flex-direction: column;
				justify-content: space-between;
			}

			.wposa-plugin__content {
				display: grid;
				grid-gap: 20px;
				grid-template-columns: 100px auto;
				padding: 20px;
			}

			.wposa-plugin__icon {
			}

			.wposa-plugin__data {
			}

			.wposa-plugin__name {
				font-weight: bold;
				margin-bottom: 5px;
				font-size: 1.2em;
			}

			.wposa-plugin__description {
				font-size: 0.9em;
			}

			.wposa-plugin__footer {
				background: #f6f7f7;
				padding: 20px 20px;
				display: grid;
				grid-gap: 20px;
				grid-template-columns: 1fr 1fr;
			}

			.wposa-plugin__install {
				align-self: end;
				text-align: right;
			}

			.wposa-plugin__meta {
				margin: 0;
				padding: 0;
				font-size: 0.9em;
			}

			.wposa-plugin__meta > li {
				padding: 0;
				margin-bottom: 2px;
			}

			@media (max-width: 1480px) {
				.wposa-plugins {
					grid-template-columns: 1fr 1fr;
				}
			}

			@media (max-width: 782px) {
				.wposa__grid {
					grid-template-columns: 1fr;
				}

				.wposa__column {
					padding-right: 10px;
				}
			}

			@media (max-width: 992px) {
				.wposa-plugins {
					grid-template-columns: 1fr;
				}
			}

			@media (max-width: 544px) {
				.toplevel_page_mihdan-index-now #wpcontent {
					/*padding-left: 0;*/
				}

				.wposa {
					top: -60px;
				}

				.form-table th {
					padding: 10px 0;
				}

				.wposa-plugins {
					grid-template-columns: 1fr;
				}
			}

			.wposa__helptab {
				max-width: 600px;
			}

			.wposa code {
				white-space: nowrap;
			}

			.wposa-overflow {
				overflow-x: auto;
				max-width: 300px;
			}

			.wposa-footer {
				display: grid;
				grid-gap: 20px;
				grid-template-columns: 1fr 1fr;
				padding-top: 50px;
			}

			.wposa-footer__column {
			}

			.wposa-footer__column--left {
			}

			.wposa-footer__column--right {
				text-align: right;
			}

			/*	new header */
			[class*=mihdan-].wp-admin #screen-meta-links {
				position: absolute;
				right: 0
			}

			.wposa-new-header {
				display: flex;
				justify-content: space-between;
				flex-wrap: wrap;
				gap: 24px;
				border-bottom: 1px solid #e0e0e0;
				padding: 16px 24px 12px;
				background: #fff;
				margin-left: -20px;
			}

			.wposa-new-header .wposa-branding {
				display: flex;
				align-items: center;
				gap: 8px;
			}

			.wposa-new-header .wposa-logo {
				width: 40px;
				height: 40px;
			}

			.wposa-new-header .wposa-branding h1 {
				font-size: 20px;
				margin: 0;
			}

			.wposa-new-header .wposa-tabs {
				align-self: flex-end;
				margin-bottom: -12px;
				display: flex;
				gap: 24px;
			}

			.wposa-new-header .wposa-tab {
				cursor: pointer;
				display: inline-block;
				padding: 12px 0 24px;
				border-bottom: 3px solid transparent;
				line-height: 1;
				color: inherit;
				text-decoration: none;
			}

			.wposa-new-header .wposa-tab.wposa-tab-active {
				border-color: #007cba;
			}

			.wposa-new-header .wposa-header-right {
				position: relative;
				display: flex;
				justify-content: flex-end;
				min-width: 240px;
			}

			.wposa-new-header .wposa-header-action {
				display: flex;
				align-items: center;
				box-sizing: border-box;
				padding-left: 8px;
				padding-right: 8px;
				text-decoration: none;
				transition: .25s;
				font-weight: 500;
				line-height: 30px;
				color: #2271b1;
				border-radius: 4px;
			}

			.wposa-new-header .wposa-header-action:first-child {
				margin-left: 0;
			}

			.wposa-new-header .wposa-header-action svg {
				margin-right: 4px;
				transition: .25s;
			}

			.wposa-new-header .button-primary {
				font-size: 14px;
				font-weight: 500;
				transition: .1s;
				min-height: 40px;
				line-height: 40px;
				padding: 0 15px;
			}

			/*	new body design */
			.wposa-nav-tab-wrapper {
				border-bottom: 1px solid #e0e0e0;
				padding-inline: 24px;
				display: flex;
				align-items: center;
				gap: 24px;
			}

			.wposa-nav-tab {
				display: inline-block;
				padding-block: 18px;
				font-weight: 500;
				transition: box-shadow .1s linear;
				text-decoration: none;
				color: inherit;
				white-space: nowrap;
			}


			.wposa__content {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 6px;
				box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
				margin-top: 20px;
				overflow: scroll;
				min-width: 0;
			}

			.wposa-nav-tab.wposa-nav-tab-active {
				box-shadow: inset 0 0 0 1.5px rgba(0, 0, 0, 0), inset 0 -3.5px 0 0 currentColor;
				color: #007cba;
			}

			.metabox-holder .group {
				padding: 24px;
			}

			#screen-meta-links {
				position: relative;
				top: 70px;
				z-index: 99999;
			}

			/* CSS to relocate settings page top nav to sidebar	*/
			.wposa__column.wposa__content {
				display: flex;
			}

			.wposa-nav-tab-wrapper {
				display: flex;
				flex-direction: column;
				width: 25%;
				overflow: hidden;
				gap: 8px;
				padding-inline: 0;
				align-items: normal;
				background: rgb(250 250 250 / 63%);
				border: 0;
				border-right: 1px solid #ccd0d4;
			}

			.wposa-nav-tab-wrapper a {
				display: block;
				overflow: hidden;
				padding: 16px 24px;
			}

			.wposa-nav-tab.wposa-nav-tab-active {
				box-shadow: none;
				border-left: 2px solid #007cba;
			}

			#wpbody-content .wposa__content .metabox-holder {
				padding: 24px;
				padding-top: 6px;
				width: 75%;
			}

			.wposa-nav-tab-wrapper .wposa-nav-tab:first-child {
				padding-top: 24px;
			}

			.wposa-nav-tab-wrapper .wposa-nav-tab:last-child {
				padding-bottom: 24px;
			}

			.crawlwp-license-page {
				padding: 2rem;
			}

			.crawlwp-license-page .crawlwp-banner {
				display: block;
				margin: 0 0 20px;
				width: 100%;
				border-left: 2px solid #2271b1;
				font: 300 30px/60px '';
				text-align: center;
				color: #ffffff;
				background: #2271b1;
				border-radius: 4px;
			}

			/* upsell sidebar */
			.cwp-premium-sidebar-upsell-ul {
				margin-top: 1rem;
				margin-bottom: 1.5rem;
				list-style-type: none;
				color: #374151;
			}

			.cwp-premium-sidebar-upsell-li {
				display: flex;
				margin-bottom: 0.75rem;
				align-items: center;
			}

			.cwp-premium-sidebar-upsell-li svg {
				width: 1.25rem;
				height: 1.25rem;
				color: #2271b1;
			}

			.cwp-premium-sidebar-upsell-li span {
				margin-left: 0.5rem;
			}

			.card.wposa-card.wposa-card--mihdan_index_now_upsell_card {
				border-radius: 0.375rem;
				border-color: #2271b1;
				padding: 1.5rem;
			}

			.wposa-card.wposa-card--mihdan_index_now_upsell_card h2 {
				margin: 0;
			}

			.cwp-premium-sidebar-upsell-cta .button-primary {
				padding-top: 6px;
				padding-bottom: 6px;
				line-height: inherit;
				margin-right: 6px;
			}

			/* upsell page */
			.crawlwp-full-feature-upsell-page-wrap {
				background: #fff;
				border: 1px solid #d6e2ed;
				text-align: center;
				margin-top: 25px;
				margin-left: auto;
				margin-right: auto;
				max-width: 900px;
			}

			.crawlwp-full-feature-upsell-page-wrap a {
				border-style: solid;
				border-width: 1px 1px 2px 1px;
				border-radius: 3px;
				cursor: pointer;
				display: inline-block;
				font-weight: 400;
				text-decoration: none;
				background: #2271b1;
				border-color: #2271b1;
				color: #fff;
				font-size: 16px;
				padding: 14px 27px;
			}

			.btn-higher-up {
				position: absolute;
				top: 0;
				left: 50%;
				transform: translate(-50%, -50%);
			}

			.crawlwp-upsell-top {
				padding: 20px;
			}

			.crawlwp-upsell-featured-image {
				padding: 10px 0;
			}

			.crawlwp-full-feature-upsell-page-wrap h2 {
				font-size: 20px;
				color: #000;
				margin: 36px 0 14px;
			}

			.crawlwp-full-feature-upsell-page-wrap h4 {
				font-size: 18px;
				color: #4c6577;
				font-weight: 400;
				margin: 0;
				line-height: 2;
			}

			.crawlwp-full-feature-upsell-page-wrap h3 {
				font-size: 18px;
				color: #000;
				margin: 0 0 14px;
			}

			.crawlwp-upsell-bottom {
				background: rgb(250 250 250 / 63%);
				border-top: 1px solid #d6e2ed;
				padding: 36px 50px 60px;
				position: relative;
			}

			.crawlwp-upsell-featured-image img {
				width: 100%;
				height: auto;
			}

			.crawlwp-full-feature-upsell-page-wrap p {
				font-size: 14px;
				color: #4c6577;
				padding: 6px 0;
			}
		</style>
		<?php
	}

	/**
	 * Reset settings for given section.
	 *
	 * @return void
	 * @link https://wpmag.ru/2015/nonces-wordpress-security/
	 */
	public function reset_form(): void
	{
		if ( ! current_user_can('manage_options')) {
			wp_send_json_error(
				__('You have no rights to do this', 'mihdan-index-now')
			);
		}

		$nonce   = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
		$section = sanitize_text_field(wp_unslash($_POST['section'] ?? ''));

		if ( ! $section) {
			wp_send_json_error(
				__('Invalid section name', 'mihdan-index-now')
			);
		}

		if ( ! $nonce) {
			wp_send_json_error(
				__('Invalid nonce', 'mihdan-index-now')
			);
		}

		if ( ! wp_verify_nonce($nonce, $section . '-options')) {
			wp_send_json_error(
				__('Invalid nonce', 'mihdan-index-now')
			);
		}

		delete_option($section);

		wp_send_json_success('ok');
	}
}
