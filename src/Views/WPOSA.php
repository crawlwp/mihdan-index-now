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
			'id'         => true,
			'style'      => true,
			'class'      => true,
			'data-w'     => true,
			'data-group' => true,
			'data-*'     => true,
		],
		'button'   => [
			'id'            => true,
			'class'         => true,
			'type'          => true,
			'style'         => true,
			'title'         => true,
			'aria-expanded' => true,
			'aria-controls' => true,
			'aria-label'    => true,
			'data-group'    => true,
			'data-*'        => true,
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
			'id'          => true,
			'class'       => true,
			'style'       => true,
			'aria-hidden' => true,
			'data-*'      => true,
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
			'name'            => true,
			'class'           => true,
			'id'              => true,
			'rows'            => true,
			'cols'            => true,
			'style'           => true,
			'placeholder'     => true,
			'readonly'        => true,
			'disabled'        => true,
			'data-cwp-tm'     => true,
			'data-cwp-entity' => true,
			'data-*'          => true,
		],
		'input'    => [
			'id'                 => true,
			'class'              => true,
			'type'               => true,
			'name'               => true,
			'value'              => true,
			'placeholder'        => true,
			'checked'            => true,
			'readonly'           => true,
			'onclick'            => true,
			'disabled'           => true,
			'style'              => true,
			'data-default-color' => true,
			'data-cwp-tm'        => true,
			'data-cwp-entity'    => true,
			'data-*'             => true,
		],
		'script'   => [
			'src'   => true,
			'async' => true,
		],
		'svg'      => array(
			'class'       => true,
			'xmlns'       => true,
			'width'       => true,
			'height'      => true,
			'viewBox'     => true,
			'viewbox'     => true,
			'fill'        => true,
			'stroke'      => true,
			'aria-hidden' => true,
			'focusable'   => true,
		),
		'g'        => array(
			'clip-path' => true,
		),
		'path'     => array(
			'd'               => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'fill-rule'       => true,
			'clip-rule'       => true,
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

	/**
	 * Submenu page title.
	 *
	 * @var string
	 */
	public $sub_page_title;

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

		// Admin stylesheet — only load on CrawlWP admin pages.
		$screen = get_current_screen();
		if ($screen && strpos($screen->id, 'crawlwp') !== false) {
			wp_enqueue_style(
				'crawlwp-admin',
				CRAWLWP_PLUGIN_URL . 'src/Views/assets/admin.css',
				array(),
				CRAWLWP_VERSION
			);
		}
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
	 */
	function admin_init()
	{
		if (isset($_REQUEST['action'], $_REQUEST['crawlwp_options_save']) && $_REQUEST['action'] == 'update' && current_user_can('manage_options')) {

			$option_page = ! empty($_REQUEST['option_page']) ? sanitize_text_field($_REQUEST['option_page']) : '';

			check_admin_referer($option_page . '-options');

			foreach ($_POST as $k => $v) {

				if (strstr($k, 'submit_') !== false) {
					$name = str_replace('submit_', '', $k);

					$db_options = get_option($name, []);

					$db_options = ! is_array($db_options) ? [] : $db_options;

					$submitted_data = apply_filters('wposa_submitted_data', $_POST[$name], $name, $_POST);

					$value = array_replace($db_options, wp_unslash(Utils::clean_data($submitted_data)));

					update_option($name, $value);

					wp_safe_redirect(add_query_arg(['settings-updated' => 'true'], Utils::get_current_url_query_string()));
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
				$id = $field['id'] ?? false;

				// Type.
				$type = $field['type'] ?? 'text';

				// Name.
				$name = $field['name'] ?? 'No Name Added';

				// Label for.
				$label_for = "{$section}[{$field['id']}]";

				// Description.
				$description = $field['desc'] ?? '';

				// Size.
				$size = $field['size'] ?? null;

				// Options.
				$options = $field['options'] ?? '';

				// Standard default value.
				$default = $field['default'] ?? '';

				// Standard default placeholder.
				$placeholder = $field['placeholder'] ?? '';

				// Readonly attribute.
				$readonly = $field['readonly'] ?? false;

				// Sanitize Callback.
				$sanitize_callback = $field['sanitize_callback'] ?? '';

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
					'rows'              => $field['rows'] ?? null,
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
			}
		}

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
		}

	}


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
		$rows  = isset($args['rows']) && ! is_null($args['rows']) ? absint($args['rows']) : 5;

		$attributes = $this->convert_array_to_attributes($args['attributes'] ?? []);

		$html = sprintf(
			'<textarea rows="%1$s" cols="55" class="%2$s-text" id="%3$s[%4$s]" name="%3$s[%4$s]" placeholder="%5$s" %6$s>%7$s</textarea>',
			esc_attr($rows),
			esc_attr($size),
			esc_attr($args['section']),
			esc_attr($args['id']),
			esc_attr($args['placeholder'] ?? ''),
			$attributes,
			$value
		);
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
		$label = $args['options']['button_label'] ?? __('Choose Image');

		$attributes = $this->convert_array_to_attributes($args['attributes'] ?? []);

		$html = sprintf('<input type="text" class="%1$s-text wpsa-url" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s" %5$s/>', $size, $args['section'], $args['id'], $value, $attributes);
		$html .= '<input type="button" class="button wpsa-browse" value="' . $label . '" />';
		$html .= $this->get_field_description($args);

		if ($value !== '') {
			$html .= sprintf('<p class="wpsa-image-preview"><img src="%s"/></p>', esc_url($value));
		}

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
		$value   = $args['placeholder'] ?? __('Submit');
		$class   = $args['button_class'] ?? 'button-secondary';
		$id      = $args['id'] ?? time();
		$onclick = $args['attributes']['onclick'] ?? '';
		?>
		<input
			type="button"
			id="<?php echo esc_attr($id); ?>"
			onclick="<?php echo esc_attr($onclick); ?>"
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
			add_action('admin_notices', [$this, 'admin_notices']);
		});
	}

	public function admin_notices()
	{
		if (Utils::_GET_var('settings-updated') == 'true') {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e('Settings saved.', 'mihdan-index-now'); ?></p>
			</div>
			<?php
		}
	}

	public function plugin_page()
	{
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

		$wp_removable_query_args = array_merge(['paged', 'cwp-page', 'cwp-query', 'preset', 'start_date', 'end_date'], wp_removable_query_args());

		?>
		<div class="wposa">
			<div class="wposa-new-header">
				<div class="wposa-branding">
					<img class="wposa-logo" src="<?php echo esc_url(Utils::get_plugin_asset_url('images/icons/crawlwp-favicon.svg')); ?>" width="80" alt=""/>
					<h1><?php echo esc_html($this->sub_page_title) ?></h1>
				</div>

				<nav class="wposa-tabs">
					<?php foreach ($this->header_menu_array as $menu) :
						$url = esc_url(remove_query_arg($wp_removable_query_args, add_query_arg(['wposa-menu' => $menu['id']])));
						$active_class = $this->get_active_header_menu() == $menu['id'] ? ' wposa-tab-active' : '';
						?>
						<a href="<?php echo $url ?>" class="wposa-tab<?php echo $active_class; ?>"><?php echo esc_html($menu['title']) ?></a>
					<?php endforeach; ?>
				</nav>

				<div class="wposa-header-right">
					<a href="<?php echo esc_url($review_url) ?>" target="_blank" class="wposa-header-action review">
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
						<a href="<?php echo esc_url($upgrade_url); ?>" target="_blank" class="button-primary plugin-upgrade">
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

		$groups = array();

		foreach ($this->sections_array as $tab) {

			$group_id = ! empty($tab['nav_group']) ? $tab['nav_group'] : '';

			// Ungrouped sections keep rendering exactly as before.
			if ($group_id === '') {
				$html .= $this->get_navigation_item($tab);
				continue;
			}

			if ( ! isset($groups[$group_id])) {
				$groups[$group_id] = array(
					'label' => $tab['nav_group_label'] ?? $group_id,
					'items' => '',
				);
			}

			if (empty($groups[$group_id]['label']) && ! empty($tab['nav_group_label'])) {
				$groups[$group_id]['label'] = $tab['nav_group_label'];
			}

			$groups[$group_id]['items'] .= $this->get_navigation_item($tab);
		}

		foreach ($groups as $group_id => $group) {
			$html .= sprintf('<div class="wposa-nav-group" data-group="%s">', esc_attr($group_id));
			$html .= sprintf(
				'<button type="button" class="wposa-nav-group__toggle" aria-expanded="true">%1$s<span class="wposa-nav-group__chevron" aria-hidden="true">%2$s</span></button>',
				esc_html($group['label']),
				$this->get_navigation_group_chevron()
			);
			$html .= '<div class="wposa-nav-group__items">' . $group['items'] . '</div>';
			$html .= '</div>';
		}

		$html .= '</nav>';

		echo wp_kses($html, self::ALLOWED_HTML);
	}

	/**
	 * Build the markup of a single navigation item.
	 *
	 * @param array $tab Section array.
	 *
	 * @return string
	 */
	private function get_navigation_item($tab): string
	{
		if (isset($tab['disabled']) && $tab['disabled'] === true) {
			if (isset($tab['badge'])) {
				return sprintf('<span class="wposa-nav-tab wposa-nav-tab--disabled" id="%1$s-tab">%2$s <span class="wposa-badge">%3$s</span></span>', $tab['id'], $tab['title'], $tab['badge']);
			}

			return sprintf('<span class="wposa-nav-tab wposa-nav-tab--disabled" id="%1$s-tab">%2$s</span>', $tab['id'], $tab['title']);
		}

		return sprintf('<a href="#%1$s" class="wposa-nav-tab" id="%1$s-tab">%2$s</a>', $tab['id'], $tab['title']);
	}

	/**
	 * Chevron icon used by the navigation group toggle.
	 *
	 * @return string
	 */
	private function get_navigation_group_chevron(): string
	{
		return '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	public function blank_mode_do_settings_sections($page)
	{

		global $wp_settings_sections, $wp_settings_fields;

		foreach ((array)$wp_settings_sections[$page] as $section) {

			$section = $section['id'];

			if (isset($wp_settings_fields[$page][$section])) {

				foreach ((array)$wp_settings_fields[$page][$section] as $field) {

					call_user_func($field['callback'], $field['args']);
				}
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
									<input type="hidden" name="crawlwp_options_save" value="true">
									<?php submit_button($form['label_submit'], $form['submit_type'], 'submit_' . $form['id'], $form['wrap'], $form['attributes']); ?>
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
		$href   = $is_url ? $tab_id : '#';
		$title  = $is_url ? esc_attr__('Click to view help guide', 'mihdan-index-now') : esc_attr__('Click to show Help tab', 'mihdan-index-now');

		$class  = 'wpsa-help-tab-toggle' . ($is_url ? ' is-url' : '');
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
							var tab = '#tab-link-<?php echo esc_js(CRAWLWP_PREFIX); ?>_' + $this.data('tab');

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

					// Collapsible navigation groups.
					var navGroupKey = function ($group) {
						return 'wposa-navgroup-' + $group.data('group');
					};

					var setNavGroupCollapsed = function ($group, collapsed, store) {
						var $items = $group.children('.wposa-nav-group__items');

						$group.toggleClass('wposa-nav-group--collapsed', collapsed);
						$group
							.children('.wposa-nav-group__toggle')
							.attr('aria-expanded', collapsed ? 'false' : 'true');

						$items.stop(true, true).css('display', '');

						if (store && 'undefined' != typeof localStorage) {
							localStorage.setItem(navGroupKey($group), collapsed ? '1' : '0');
						}
					};

					// Restore the saved state of every group, default is expanded.
					$('.wposa-nav-group').each(function () {
						var $group = $(this);

						if ('undefined' == typeof localStorage) {
							return;
						}

						if ('1' === localStorage.getItem(navGroupKey($group))) {
							setNavGroupCollapsed($group, true, false);
						}
					});

					// Keep the active item visible even when its group was collapsed.
					if ('' != activetab && $(activetab + '-tab').length) {
						var $active_group = $(activetab + '-tab').closest('.wposa-nav-group');

						if ($active_group.hasClass('wposa-nav-group--collapsed')) {
							setNavGroupCollapsed($active_group, false, true);
						}
					}

					$(document).on('click', '.wposa-nav-group__toggle', function (evt) {
						evt.preventDefault();

						var $toggle = $(this),
							$group = $toggle.closest('.wposa-nav-group'),
							$items = $group.children('.wposa-nav-group__items'),
							collapse = !$group.hasClass('wposa-nav-group--collapsed');

						$toggle.attr('aria-expanded', collapse ? 'false' : 'true');

						if ('undefined' != typeof localStorage) {
							localStorage.setItem(navGroupKey($group), collapse ? '1' : '0');
						}

						if (collapse) {
							$items.stop(true, true).slideUp(150, function () {
								$group.addClass('wposa-nav-group--collapsed');
								$items.css('display', '');
							});
						} else {
							$group.removeClass('wposa-nav-group--collapsed');
							$items
								.hide()
								.stop(true, true)
								.slideDown(150, function () {
									$items.css('display', '');
								});
						}
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
					var YSTATE = '<?php echo wp_create_nonce('yandex_oauth_nonce'); ?>';
					var CODE_ENDPOINT = 'https://oauth.yandex.com/authorize?state=' + YSTATE + '&response_type=code&force_confirm=yes&redirect_uri=' + REDIRECT_URL + '&client_id=';

					$('#button_get_token').on(
						'click',
						function () {
							var CLIENT_ID = document.getElementById('crawlwp_yandex_webmaster[client_id]').value;

							window.location.href = CODE_ENDPOINT + CLIENT_ID;
						}
					);
				});

			})(jQuery);

		</script>
		<?php
	}
}
