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
		]
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

					$value = array_replace($db_options, $_POST[$name]);

					update_option($name, wp_unslash($value));

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
			add_settings_section($section['id'], $section['title'], $callback, $section['id']);
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

		$value = esc_attr($this->get_option($args['id'], $args['section'], $args['std']));
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
		return 'data:image/svg+xml;base64,' . base64_encode('<svg fill="none" height="200" width="200" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><linearGradient id="a"><stop offset="0" stop-color="#fff" stop-opacity=".99"/><stop offset="1" stop-color="#fff"/></linearGradient><linearGradient id="b" gradientUnits="userSpaceOnUse" x1="190.095" x2="217.005" xlink:href="#a" y1="202.96" y2="196.591"/><linearGradient id="c" gradientUnits="userSpaceOnUse" x1="93.737" x2="123.702" xlink:href="#a" y1="217.848" y2="168.504"/><linearGradient id="d" gradientUnits="userSpaceOnUse" x1="107.362" x2="141.14" xlink:href="#a" y1="160.703" y2="116.433"/><linearGradient id="e" gradientUnits="userSpaceOnUse" x1="105.19" x2="135.728" xlink:href="#a" y1="155.972" y2="127.017"/><linearGradient id="f" gradientUnits="userSpaceOnUse" x1="116.36" x2="122.344" xlink:href="#a" y1="144.43" y2="137.465"/><linearGradient id="g" gradientUnits="userSpaceOnUse" x1="165.663" x2="172.418" xlink:href="#a" y1="146.349" y2="137.939"/><linearGradient id="h" gradientUnits="userSpaceOnUse" x1="78.365" x2="206.525" xlink:href="#a" y1="224.988" y2="156.512"/><linearGradient id="i" gradientUnits="userSpaceOnUse" x1="150.589" x2="192.959" xlink:href="#a" y1="167.149" y2="127.965"/><linearGradient id="j" gradientUnits="userSpaceOnUse" x1="110.283" x2="124.953" xlink:href="#a" y1="71.012" y2="51.166"/><linearGradient id="k" gradientUnits="userSpaceOnUse" x1="115.356" x2="135.109" xlink:href="#a" y1="61.773" y2="54.624"/><linearGradient id="l" gradientUnits="userSpaceOnUse" x1="114.339" x2="130.822" xlink:href="#a" y1="34.517" y2="20.71"/><g clip-rule="evenodd" fill-rule="evenodd"><path d="m187.351 139.188 2.449 1.543c.717.452 2.006 1.264 3.17 1.878.463.244.865.435 1.179.558.739-.862 1.515-1.454 2.494-1.784 1.036-.348 2.191-.347 3.38-.346h.16c1.181 0 2.265.62 3.064 1.348a7.148 7.148 0 0 1 1.924 2.938c.394 1.163.49 2.522-.026 3.85-.435 1.12-1.258 2.094-2.46 2.861l-4.201 56.577-2.992-.222 4.109-55.34c-.12.007-.241.012-.361.014-.936.013-1.972-.147-2.891-.579a6.47 6.47 0 0 1 -1.491-.99 12.654 12.654 0 0 1 -.216-.189l-6.488 3.269zm3.295 5.565.265 5.073 4.106-2.07.745.563c.288.218.593.491.823.697l.228.202c.272.234.511.409.813.551.414.195.976.303 1.57.294.602-.009 1.097-.135 1.377-.276 1.093-.551 1.572-1.177 1.776-1.701.21-.539.199-1.157-.019-1.799a4.14 4.14 0 0 0 -1.104-1.684c-.499-.455-.893-.566-1.043-.566-1.417 0-2.069.017-2.584.19-.398.134-.836.41-1.538 1.353-.503.677-1.253.73-1.578.722a3.626 3.626 0 0 1 -1.018-.194c-.589-.191-1.264-.513-1.895-.846a31.491 31.491 0 0 1 -.924-.509z" fill="url(#b)"/><path d="m138.027 182.028-.521 18.753a378.81 378.81 0 0 0 12.242-.587c.73-.047 1.33-.089 1.779-.121l.503-17.102 1.999.059-.512 17.417.116 1.471-.16.013-.503 17.098-1.999-.059.497-16.887c-.426.029-.96.066-1.591.107a380.82 380.82 0 0 1 -12.426.594l-.479 17.243-1.999-.055.475-17.13c-4.505.117-9.489.162-14.423.04-.834-.021-1.689-.047-2.56-.078l-.492 18.223-2-.054.494-18.248c-5.575-.24-11.637-.656-17-1.093l-.498 16.398-1.999-.061.5-16.503a385.472 385.472 0 0 1 -10.494-1.01 73.005 73.005 0 0 1 -2.502-.314 6.763 6.763 0 0 1 -.652-.122 1.116 1.116 0 0 1 -.361-.155 1.007 1.007 0 0 1 .202-1.793c.127-.048.169-.043.278-.065.383-.079.994-.116.994-.116l.035.302c.513.074 1.268.167 2.231.276 2.184.247 5.389.565 9.123.891.397.035.8.07 1.207.104l.5-16.494 1.999.061-.503 16.6c5.364.437 11.428.854 16.994 1.094l.506-18.752 2 .054-.508 18.778c.87.031 1.723.057 2.555.078 4.933.121 9.923.075 14.43-.044l.524-18.866z" fill="url(#c)"/><path d="m110.983 125.286c1.373-.674 5.497-1.786 10.641-1.786h.063l35.44 3.005-.254 2.99-35.312-2.995c-4.805.009-8.416 1.066-9.257 1.479-6.857 3.365-10.804 11.096-10.804 18.242 0 1.07.007 2.595.662 4.288.642 1.661 1.95 3.584 4.732 5.322 1.867 1.166 3.247 1.772 5.481 2.236 2.332.483 5.549.804 11.133 1.352 11.035 1.083 27.281 1.581 28.992 1.581v3c-1.796 0-18.141-.502-29.285-1.595-5.505-.541-8.921-.876-11.449-1.401-2.625-.545-4.333-1.299-6.462-2.629-3.317-2.071-5.058-4.501-5.94-6.785-.864-2.233-.864-4.231-.864-5.342v-.027c0-8.09 4.429-16.984 12.483-20.935z" fill="url(#d)"/><path d="m122.309 157.515.083.011c10.071 1.273 16.437-4.194 17.349-11.105.461-3.494-.541-6.542-3.28-8.991-2.863-2.56-7.874-4.673-15.769-5.446l-.042-.004-.042-.006c-5.102-.645-9.163.423-12.007 2.386-2.829 1.954-4.65 4.921-5.125 8.516-.212 1.61-.338 3.525-.19 5.317.152 1.851.57 3.23 1.162 4.059 1.016 1.425 3.081 2.657 6.39 3.58 3.236.904 7.188 1.384 11.387 1.677zm-.309 2.985c-8.513-.595-16.779-1.985-20-6.5-2.091-2.931-1.959-8.024-1.5-11.5 1.165-8.83 9.076-14.945 20.5-13.5 16.466 1.612 22.882 8.968 21.717 17.797-1.165 8.83-9.293 15.148-20.717 13.703z" fill="url(#e)"/></g><rect fill="url(#f)" height="7" rx="2" width="9" x="115" y="138"/><rect fill="url(#g)" height="8" rx="2" width="11" x="164" y="139"/><g clip-rule="evenodd" fill-rule="evenodd"><path d="m110.791 66.17-2.481.31c-9.424 1.179-16.897 5.15-22.686 11.45-5.815 6.331-10.033 15.131-12.68 26.096-1.201 6.367-1.763 13.784-2.263 20.386a504.65 504.65 0 0 1 -.473 5.956c-.243 2.764-.84 25.277-1.474 49.24-.41 15.482-.836 31.57-1.193 43.328 5.29 3.382 18.765 9.608 37.343 11.172 18.589 1.565 36.829-1.904 45.116-4.216v-8.858l-10.307.85-.092.01-.346.034c-.3.03-.738.071-1.295.118-1.115.094-2.71.214-4.646.316-3.866.204-9.12.342-14.634.068-5.567-.278-11.291-1.159-16.203-2.292-4.855-1.118-9.13-2.53-11.691-3.953-2.444-1.357-4.37-2.862-5.817-4.669-1.46-1.826-2.339-3.844-2.843-6.089-1.23-5.474-.057-12.238 3.81-16.701 3.795-4.378 8.606-6.077 10.346-6.651 1.393-.459 7.102-1.738 15.457-1.738 6.158 0 35.594.396 49.942 1.045l.646-9.046-24.099-1.519c-7.03-.357-23.219-1.509-30.86-3.287l-.287-.067c-4.351-1.012-6.795-1.581-8.576-2.242-1.938-.719-3.128-1.56-4.942-2.842l-.114-.081c-4.372-3.089-10.297-10.796-10.297-21.396a27.43 27.43 0 0 1 2.394-11.239 24.376 24.376 0 0 1 1.667-3.11 17.148 17.148 0 0 1 .776-1.116l.027-.039c.048-.066.113-.156.196-.265.166-.218.403-.516.71-.869a20.956 20.956 0 0 1 2.712-2.587c2.379-1.898 5.977-3.925 10.701-4.237 2.734-.18 17.765-.371 30.774.538 4.81.336 11.007.875 16.219 1.328l.026.002c5.333.463 9.412.815 10.211.815h2.935v-9.237c-.982-13.379-4.366-21.47-7.144-26.1-2.822-4.704-10.355-14.078-22.946-17.138l-2.43-.59 1.181-4.86 2.429.591c14.409 3.502 22.876 14.129 26.054 19.425 3.216 5.36 6.823 14.243 7.849 28.4l.007.091v14.418h-7.935c-1.053 0-5.266-.366-10.188-.794l-.455-.04c-5.231-.454-11.394-.99-16.161-1.323-12.824-.896-27.624-.7-30.096-.537-3.428.226-6.08 1.694-7.913 3.156a16.01 16.01 0 0 0 -2.061 1.966 11.432 11.432 0 0 0 -.606.753l-.016.022-.001.003-.002.002-.054.08-.061.076-.001.001-.002.003-.014.018a12.051 12.051 0 0 0 -.506.735c-.351.55-.834 1.383-1.322 2.468a22.43 22.43 0 0 0 -1.954 9.19c0 8.755 4.945 15.025 8.182 17.313 1.926 1.36 2.628 1.843 3.911 2.318 1.441.535 3.549 1.031 8.257 2.127 7.135 1.661 22.885 2.803 29.994 3.164l.016.001 29.161 1.839-1.355 18.967-2.455-.135c-12.974-.713-45.615-1.159-52.124-1.159-7.949 0-13.11 1.229-13.89 1.487-1.522.501-5.254 1.853-8.133 5.176-2.655 3.064-3.656 8.128-2.712 12.331.366 1.626.955 2.918 1.87 4.061.928 1.16 2.284 2.28 4.34 3.423 1.939 1.077 5.664 2.363 10.385 3.451 4.664 1.075 10.093 1.909 15.33 2.17 5.29.263 10.362.132 14.121-.067a140.984 140.984 0 0 0 5.719-.417l.239-.024.078-.008.077-.008.022-.002m0 0 .033-.004 15.782-1.301v17.991l-1.752.55c-7.658 2.402-27.953 6.698-48.784 4.944-20.86-1.756-35.828-9.143-40.929-12.83l-1.075-.777.041-1.326c.357-11.574.792-27.978 1.213-43.879.644-24.308 1.258-47.442 1.513-50.347.153-1.738.297-3.653.45-5.679.504-6.666 1.099-14.532 2.367-21.218l.011-.06.014-.059c2.777-11.536 7.304-21.216 13.873-28.367 6.606-7.191 15.172-11.707 25.748-13.029l2.481-.31.62 4.962" fill="url(#h)"/><path d="m168.101 171.149c13.862 0 25.1-11.002 25.1-24.574 0-13.573-11.238-24.575-25.1-24.575-13.863 0-25.101 11.002-25.101 24.575 0 13.572 11.238 24.574 25.101 24.574zm-.107-4.48c10.866 0 19.675-8.809 19.675-19.675 0-10.867-8.809-19.676-19.675-19.676-10.867 0-19.676 8.809-19.676 19.676 0 10.866 8.809 19.675 19.676 19.675z" fill="url(#i)"/><g><path d="m122.87 55.624a19.796 19.796 0 0 1 4.863 1.29c4.05 1.67 6.697 4.556 6.575 7.736-.185 4.795-6.597 8.44-14.321 8.142-7.725-.297-13.837-4.425-13.652-9.22.122-3.172 2.969-5.84 7.12-7.198a19.757 19.757 0 0 1 4.922-.918l-.239 2.797a1.303 1.303 0 0 0 .05.58c.079.257.234.494.445.699.427.413 1.087.692 1.837.72.722.029 1.379-.18 1.835-.535.29-.226.499-.51.592-.829a1.29 1.29 0 0 0 .048-.499zm3.957 6.185a6.804 6.804 0 0 1 -1.916 1.978c-1.386.943-3.017.868-4.608.806-1.592-.061-3.224-.111-4.533-1.158a6.807 6.807 0 0 1 -1.758-2.12 8.73 8.73 0 0 0 -1.417.754c-1.345.897-1.433 1.574-1.437 1.689-.005.118.054.974 1.738 2.11 1.613 1.09 4.167 1.982 7.276 2.101 3.11.12 5.725-.572 7.417-1.534 1.766-1.004 1.891-1.853 1.896-1.97.004-.116-.032-.802-1.313-1.803a8.769 8.769 0 0 0 -1.345-.853z" fill="url(#j)"/><path d="m116.383 32.14 9.117.352 2.267 25.665c.012.197.014.395.006.594-.092 2.382-1.478 4.094-2.862 5.036-1.386.943-3.017.868-4.608.806-1.592-.061-3.224-.111-4.533-1.158-1.307-1.046-2.558-2.86-2.466-5.242.005-.142.016-.283.031-.424l3.048-25.628zm1.755 26.114 2.79-24.489 2.017 24.624a1.285 1.285 0 0 1 -.048.5c-.239.82-1.227.927-2.409.881-1.12-.043-2.059-.162-2.3-.937a1.33 1.33 0 0 1 -.05-.58z" fill="url(#k)"/><path d="m120.908 31.865a4.85 4.85 0 1 0 .373-9.695 4.85 4.85 0 0 0 -.373 9.695zm-.17 4.407a9.262 9.262 0 0 0 9.611-8.898 9.261 9.261 0 1 0 -18.509-.713 9.261 9.261 0 0 0 8.898 9.611z" fill="url(#l)"/></g></g></svg>');
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
					<img class="wposa-logo" src="<?php echo esc_url(Utils::get_plugin_asset_url('images/icons/index-now-logo--gradient.svg')); ?>" width="80" alt=""/>
					<h1><?php echo $this->sub_page_title ?></h1>
				</div>

				<nav class="wposa-tabs">
					<?php foreach ($this->header_menu_array as $menu) :
						$url = esc_url(add_query_arg(['wposa-menu' => $menu['id']]));
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
						<?php esc_html_e('Review', 'mihdan-index-now-pro'); ?>
					</a> <a href="https://feedbackwp.com/docs/" target="_blank" class="wposa-header-action translate">
						<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 56 56">
							<path fill="currentColor" d="M15.555 53.125h24.89c4.852 0 7.266-2.461 7.266-7.336V24.508c0-3.024-.328-4.336-2.203-6.258L32.57 5.102c-1.78-1.829-3.234-2.227-5.882-2.227H15.555c-4.828 0-7.266 2.484-7.266 7.36v35.554c0 4.898 2.438 7.336 7.266 7.336m.187-3.773c-2.414 0-3.68-1.29-3.68-3.633V10.305c0-2.32 1.266-3.657 3.704-3.657h10.406v13.618c0 2.953 1.5 4.406 4.406 4.406h13.36v21.047c0 2.343-1.243 3.633-3.68 3.633ZM31 21.132c-.914 0-1.29-.374-1.29-1.312V7.375l13.5 13.758Z"/>
						</svg>
						<?php esc_html_e('Documentation', 'mihdan-index-now-pro'); ?>
					</a>
					<?php /* @todo restrict to lite only */
					if (true) { ?>
						<a href="<?php echo $upgrade_url; ?>" target="_blank" class="button-primary plugin-upgrade">
							<?php esc_html_e('CrawlWP Premium', 'mihdan-index-now-pro'); ?>
						</a>
					<?php } ?>
				</div>
			</div>
			<div class="wrap">
				<?php // useful to keep admin notices at the top ?>
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
		ob_start();
		?>
		<a title="<?php echo esc_attr__('Click to show Help tab', 'mihdan-index-now'); ?>" class="wpsa-help-tab-toggle" data-tab="<?php echo esc_attr($tab_id); ?>"><?php echo esc_html($tab_icon); ?></a>
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

				$(document).on('ready', function () {

					const
						$show_settings_toggler = $('.show-settings'),
						$help = $('.wpsa-help-tab-toggle'),
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
