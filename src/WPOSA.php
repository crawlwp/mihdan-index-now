<?php
/**
 * Main Class file for `WP_OSA`
 *
 * Main class that deals with all other classes.
 *
 * @since   1.0.0
 * @package WPOSA
 */

namespace Mihdan\IndexNow;

/**
 * WP_OSA.
 *
 * WP Settings API Class.
 *
 * @since 1.0.0
 */

class WPOSA {

	/**
	 * Allowed HTML tags and attributes for wp_kses().
	 */
	const ALLOWED_HTML = [
		'ul'       => [],
		'li'       => [],
		'br'       => [],
		'fields'   => [],
		'label'    => [
			'for' => true,
		],
		'div'      => [
			'id'    => true,
			'style' => true,
			'class' => true,
		],
		'a'        => [
			'id'      => true,
			'class'   => true,
			'href'    => true,
			'style'   => true,
			'title'   => true,
			'onclick' => true,
		],
		'img'      => [
			'src' => true,
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
		'span'     => [
			'style' => true,
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
	 * Sections array.
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $sections_array = array();

	/**
	 * Fields array.
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $fields_array = array();

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $plugin_version Pluign version.
	 * @param string $plugin_slug Plugin slug.
	 *
	 * @since  1.0.0
	 */
	public function __construct( $plugin_name = 'WPOSA', $plugin_version = '0.1', $plugin_slug = 'WPOSA' ) {
		$this->plugin_name    = $plugin_name;
		$this->plugin_version = $plugin_version;
		$this->plugin_slug    = $plugin_slug;

		// Enqueue the admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		// Hook it up.
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Menu.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Admin Scripts.
	 *
	 * @since 1.0.0
	 */
	public function admin_scripts() {
		// jQuery is needed.
		wp_enqueue_script( 'jquery' );

		// Color Picker.
		wp_enqueue_script(
			'iris',
			admin_url( 'js/iris.min.js' ),
			array( 'jquery-ui-draggable', 'jquery-ui-slider', 'jquery-touch-punch' ),
			false,
			1
		);

		// Media Uploader.
		wp_enqueue_media();
	}


	/**
	 * Set Sections.
	 *
	 * @param array $sections
	 * @since 1.0.0
	 */
	public function set_sections( $sections ) {
		// Bail if not array.
		if ( ! is_array( $sections ) ) {
			return false;
		}

		// Assign to the sections array.
		$this->sections_array = $sections;

		return $this;
	}


	/**
	 * Add a single section.
	 *
	 * @param array $section
	 * @since 1.0.0
	 */
	public function add_section( $section ) {
		// Bail if not array.
		if ( ! is_array( $section ) ) {
			return false;
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
	public function set_fields( $fields ) {
		// Bail if not array.
		if ( ! is_array( $fields ) ) {
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
	public function add_field( $section, $field_array ) {
		// Set the defaults
		$defaults = array(
			'id'   => '',
			'name' => '',
			'desc' => '',
			'type' => 'text',
		);

		// Combine the defaults with user's arguements.
		$arg = wp_parse_args( $field_array, $defaults );

		// Each field is an array named against its section.
		$this->fields_array[ $section ][] = $arg;

		return $this;
	}



	/**
	 * Initialize API.
	 *
	 * Initializes and registers the settings sections and fields.
	 * Usually this should be called at `admin_init` hook.
	 *
	 * @since  1.0.0
	 */
	function admin_init() {
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
		foreach ( $this->sections_array as $section ) {
			if ( false == get_option( $section['id'] ) ) {
				// Add a new field as section ID.
				add_option( $section['id'] );
			}

			// Deals with sections description.
			if ( isset( $section['desc'] ) && ! empty( $section['desc'] ) ) {
				// Build HTML.
				$section['desc'] = '<div class="inside">' . $section['desc'] . '</div>';

				// Create the callback for description.
				$callback = function() use ( $section ) {
					echo wp_kses( str_replace( '"', '\"', $section['desc'] ), self::ALLOWED_HTML );
				};

			} elseif ( isset( $section['callback'] ) ) {
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
			 * @since 1.0.0
			 */
			add_settings_section( $section['id'], $section['title'], $callback, $section['id'] );
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
		foreach ( $this->fields_array as $section => $field_array ) {
			foreach ( $field_array as $field ) {
				// ID.
				$id = isset( $field['id'] ) ? $field['id'] : false;

				// Type.
				$type = isset( $field['type'] ) ? $field['type'] : 'text';

				// Name.
				$name = isset( $field['name'] ) ? $field['name'] : 'No Name Added';

				// Label for.
				$label_for = "{$section}[{$field['id']}]";

				// Description.
				$description = isset( $field['desc'] ) ? $field['desc'] : '';

				// Size.
				$size = isset( $field['size'] ) ? $field['size'] : null;

				// Options.
				$options = isset( $field['options'] ) ? $field['options'] : '';

				// Standard default value.
				$default = isset( $field['default'] ) ? $field['default'] : '';

				// Standard default placeholder.
				$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';

				// Sanitize Callback.
				$sanitize_callback = isset( $field['sanitize_callback'] ) ? $field['sanitize_callback'] : '';

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
				);

				/**
				 * Add a new field to a section of a settings page.
				 *
				 * @param string   $id
				 * @param string   $title
				 * @param callable $callback
				 * @param string   $page
				 * @param string   $section = 'default'
				 * @param array    $args = array()
				 * @since 1.0.0
				 */

				// @param string 	$id
				$field_id = $section . '[' . $field['id'] . ']';

				add_settings_field(
					$field_id,
					$name,
					array( $this, 'callback_' . $type ),
					$section,
					$section,
					$args
				);
			} // foreach ended.
		} // foreach ended.

		// Creates our settings in the fields table.
		foreach ( $this->sections_array as $section ) {
			/**
			 * Registers a setting and its sanitization callback.
			 *
			 * @param string $field_group   | A settings group name.
			 * @param string $field_name    | The name of an option to sanitize and save.
			 * @param callable  $sanitize_callback = ''
			 * @since 1.0.0
			 */
			register_setting( $section['id'], $section['id'], array( $this, 'sanitize_fields' ) );
		} // foreach ended.

	} // admin_init() ended.


	/**
	 * Sanitize callback for Settings API fields.
	 *
	 * @since 1.0.0
	 */
	public function sanitize_fields( $fields ) {
		foreach ( $fields as $field_slug => $field_value ) {
			$sanitize_callback = $this->get_sanitize_callback( $field_slug );

			// If callback is set, call it.
			if ( $sanitize_callback ) {
				$fields[ $field_slug ] = call_user_func( $sanitize_callback, $field_value );
				continue;
			}
		}

		return $fields;
	}


	/**
	 * Get sanitization callback for given option slug
	 *
	 * @param string $slug option slug.
	 * @return mixed string | bool false
	 * @since  1.0.0
	 */
	function get_sanitize_callback( $slug = '' ) {
		if ( empty( $slug ) ) {
			return false;
		}

		// Iterate over registered fields and see if we can find proper callback.
		foreach ( $this->fields_array as $section => $field_array ) {
			foreach ( $field_array as $field ) {
				if ( $field['name'] != $slug ) {
					continue;
				}

				// Return the callback name.
				return isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ? $field['sanitize_callback'] : false;
			}
		}

		return false;
	}


	/**
	 * Get field description for display
	 *
	 * @param array $args settings field args
	 */
	public function get_field_description( $args ) {
		if ( ! empty( $args['desc'] ) ) {
			$desc = sprintf(
				'<p class="description">%s</p>',
				is_callable( $args['desc'] )
					? call_user_func( $args['desc'] )
					: $args['desc']
			);
		} else {
			$desc = '';
		}

		return $desc;
	}


	/**
	 * Displays a title field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_title( $args ) {
		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		if ( '' !== $args['name'] ) {
			$name = $args['name'];
		} else {
		};
		$type = isset( $args['type'] ) ? $args['type'] : 'title';

		echo esc_html( $value );
	}


	/**
	 * Displays a text field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_text( $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'], $args['placeholder'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
		$type  = isset( $args['type'] ) ? $args['type'] : 'text';

		$html  = sprintf( '<input type="%1$s" class="%2$s-text" id="%3$s[%4$s]" name="%3$s[%4$s]" value="%5$s" placeholder="%6$s"/>', $type, $size, $args['section'], $args['id'], $value, $args['placeholder'] );
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, self::ALLOWED_HTML );
	}


	/**
	 * Displays a url field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_url( $args ) {
		$this->callback_text( $args );
	}

	/**
	 * Displays a number field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_number( $args ) {
		$this->callback_text( $args );
	}

	/**
	 * Displays a checkbox for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_checkbox( $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );

		$html  = '<fieldset>';
		$html .= sprintf( '<label for="wposa-%1$s[%2$s]">', $args['section'], $args['id'] );
		$html .= sprintf( '<input type="hidden" name="%1$s[%2$s]" value="off" />', $args['section'], $args['id'] );
		$html .= sprintf( '<input type="checkbox" class="checkbox" id="wposa-%1$s[%2$s]" name="%1$s[%2$s]" value="on" %3$s />', $args['section'], $args['id'], checked( $value, 'on', false ) );
		$html .= sprintf( '%1$s</label>', $args['desc'] );
		$html .= '</fieldset>';

		echo wp_kses( $html, self::ALLOWED_HTML );
	}

	/**
	 * Displays a multicheckbox a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_multicheck( $args ) {

		$value = $this->get_option( $args['id'], $args['section'], $args['std'] );

		$html = '<fieldset>';
		foreach ( $args['options'] as $key => $label ) {
			$checked = isset( $value[ $key ] ) ? $value[ $key ] : '0';
			$html   .= sprintf( '<label for="wposa-%1$s[%2$s][%3$s]">', $args['section'], $args['id'], $key );
			$html   .= sprintf( '<input type="checkbox" class="checkbox" id="wposa-%1$s[%2$s][%3$s]" name="%1$s[%2$s][%3$s]" value="%3$s" %4$s />', $args['section'], $args['id'], $key, checked( $checked, $key, false ) );
			$html   .= sprintf( '%1$s</label><br>', $label );
		}
		$html .= $this->get_field_description( $args );
		$html .= '</fieldset>';

		echo wp_kses( $html, self::ALLOWED_HTML );
	}

	/**
	 * Displays a multicheckbox a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_radio( $args ) {

		$value = $this->get_option( $args['id'], $args['section'], $args['std'] );

		$html = '<fieldset>';
		foreach ( $args['options'] as $key => $label ) {
			$html .= sprintf( '<label for="wposa-%1$s[%2$s][%3$s]">', $args['section'], $args['id'], $key );
			$html .= sprintf( '<input type="radio" class="radio" id="wposa-%1$s[%2$s][%3$s]" name="%1$s[%2$s]" value="%3$s" %4$s />', $args['section'], $args['id'], $key, checked( $value, $key, false ) );
			$html .= sprintf( '%1$s</label><br>', $label );
		}
		$html .= $this->get_field_description( $args );
		$html .= '</fieldset>';

		echo wp_kses( $html, self::ALLOWED_HTML );
	}

	/**
	 * Displays a selectbox for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_select( $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';

		$html = sprintf( '<select class="%1$s" name="%2$s[%3$s]" id="%2$s[%3$s]">', $size, $args['section'], $args['id'] );
		foreach ( $args['options'] as $key => $label ) {
			$html .= sprintf( '<option value="%s"%s>%s</option>', $key, selected( $value, $key, false ), $label );
		}
		$html .= sprintf( '</select>' );
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, self::ALLOWED_HTML );
	}

	/**
	 * Displays a textarea for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_textarea( $args ) {

		$value = esc_textarea( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';

		$html  = sprintf( '<textarea rows="5" cols="55" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]">%4$s</textarea>', $size, $args['section'], $args['id'], $value );
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, self::ALLOWED_HTML );
	}

	/**
	 * Displays a textarea for a settings field
	 *
	 * @param array $args settings field args.
	 * @return string
	 */
	function callback_html( $args ) {
		echo wp_kses( $this->get_field_description( $args ), self::ALLOWED_HTML );
	}

	/**
	 * Displays a file upload field for a settings field
	 *
	 * @param array $args settings field args.
	 */
	function callback_file( $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
		$id    = $args['section'] . '[' . $args['id'] . ']';
		$label = isset( $args['options']['button_label'] ) ?
			$args['options']['button_label'] :
			__( 'Choose File' );

		$html  = sprintf( '<input type="text" class="%1$s-text wpsa-url" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value );
		$html .= '<input type="button" class="button wpsa-browse" value="' . $label . '" />';
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, self::ALLOWED_HTML );
	}

	/**
	 * Displays an image upload field with a preview
	 *
	 * @param array $args settings field args.
	 */
	function callback_image( $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
		$id    = $args['section'] . '[' . $args['id'] . ']';
		$label = isset( $args['options']['button_label'] ) ?
			$args['options']['button_label'] :
			__( 'Choose Image' );

		$html  = sprintf( '<input type="text" class="%1$s-text wpsa-url" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value );
		$html .= '<input type="button" class="button wpsa-browse" value="' . $label . '" />';
		$html .= $this->get_field_description( $args );
		$html .= '<p class="wpsa-image-preview"><img src=""/></p>';

		echo wp_kses( $html, self::ALLOWED_HTML );
	}

	/**
	 * Displays a password field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_password( $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';

		$html  = sprintf( '<input type="password" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value );
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, self::ALLOWED_HTML );
	}

	/**
	 * Displays a color picker field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_color( $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'], $args['placeholder'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';

		$html  = sprintf( '<input type="text" class="%1$s-text color-picker" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s" data-default-color="%5$s" placeholder="%6$s" />', $size, $args['section'], $args['id'], $value, $args['std'], $args['placeholder'] );
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, self::ALLOWED_HTML );
	}


	/**
	 * Displays a separator field for a settings field
	 *
	 * @param array $args settings field args
	 */
	function callback_separator( $args ) {
		$type = isset( $args['type'] ) ? $args['type'] : 'separator';

		$html  = '';
		$html .= '<div class="wpsa-settings-separator"></div>';

		echo wp_kses( $html, self::ALLOWED_HTML );
	}


	/**
	 * Get the value of a settings field
	 *
	 * @param string $option  settings field name.
	 * @param string $section the section name this field belongs to.
	 * @param string $default default text if it's not found.
	 * @return string
	 */
	function get_option( $option, $section, $default = '' ) {

		$options = get_option( $section );

		if ( isset( $options[ $option ] ) ) {
			return $options[ $option ];
		}

		return $default;
	}

	/**
	 * Add submenu page to the Settings main menu.
	 *
	 * @param string $page_title
	 * @param string $menu_title
	 * @param string $capability
	 * @param string $menu_slug
	 * @param callable $function = ''
	 * @author Ahmad Awais
	 * @since  [version]
	 */

	// public function admin_menu( $page_title = 'Page Title', $menu_title = 'Menu Title', $capability = 'manage_options', $menu_slug = 'settings_page', $callable = 'plugin_page' ) {
	public function admin_menu() {
		// add_options_page( $page_title, $menu_title, $capability, $menu_slug, array( $this, $callable ) );
		add_options_page(
			$this->plugin_name,
			$this->plugin_name,
			'manage_options',
			$this->plugin_slug,
			array( $this, 'plugin_page' )
		);
	}

	public function plugin_page() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $this->plugin_name ) . ' <span style="font-size:50%;">v' . esc_html( $this->plugin_version ) . '</span></h1>';
		$this->show_navigation();
		$this->show_forms();
		echo '</div>';
	}

	/**
	 * Show navigations as tab
	 *
	 * Shows all the settings section labels as tab
	 */
	function show_navigation() {
		$html = '<h2 class="nav-tab-wrapper">';

		foreach ( $this->sections_array as $tab ) {
			$html .= sprintf( '<a href="#%1$s" class="nav-tab" id="%1$s-tab">%2$s</a>', $tab['id'], $tab['title'] );
		}

		$html .= '</h2>';

		echo wp_kses( $html, self::ALLOWED_HTML );
	}

	/**
	 * Show the section settings forms
	 *
	 * This function displays every sections in a different form
	 */
	function show_forms() {
		$default = array(
			'label_submit' => null,
			'submit_type'  => 'primary',
			'wrap'         => true,
			'attributes'   => null,
		);
		?>
		<div class="metabox-holder">
			<?php foreach ( $this->sections_array as $form ) : ?>
				<?php
				$form = wp_parse_args( $form, $default );
				?>
				<!-- style="display: none;" -->
				<div id="<?php echo esc_attr( $form['id'] ); ?>" class="group" >
					<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
						<?php
						do_action( 'wsa_form_top_' . $form['id'], $form );
						settings_fields( $form['id'] );
						do_settings_sections( $form['id'] );
						do_action( 'wsa_form_bottom_' . $form['id'], $form );
						?>
						<div style="padding-left: 10px">
							<?php submit_button( $form['label_submit'], $form['submit_type'], 'submit_' . $form['id'], $form['wrap'], $form['attributes'] ); ?>
						</div>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		$this->script();
	}

	/**
	 * Tabbable JavaScript codes & Initiate Color Picker
	 *
	 * This code uses localstorage for displaying active tabs
	 */
	function script() {
		?>
		<script>
            jQuery( document ).ready( function( $ ) {

                //Initiate Color Picker.
                $('.color-picker').iris();

                // Switches option sections
                $( '.group' ).hide();
                var activetab = '';
                if ( 'undefined' != typeof localStorage ) {
                    activetab = localStorage.getItem( 'activetab' );
                }
                if ( '' != activetab && $( activetab ).length ) {
                    $( activetab ).fadeIn();
                } else {
                    $( '.group:first' ).fadeIn();
                }
                $( '.group .collapsed' ).each( function() {
                    $( this )
                        .find( 'input:checked' )
                        .parent()
                        .parent()
                        .parent()
                        .nextAll()
                        .each( function() {
                            if ( $( this ).hasClass( 'last' ) ) {
                                $( this ).removeClass( 'hidden' );
                                return false;
                            }
                            $( this )
                                .filter( '.hidden' )
                                .removeClass( 'hidden' );
                        });
                });

                if ( '' != activetab && $( activetab + '-tab' ).length ) {
                    $( activetab + '-tab' ).addClass( 'nav-tab-active' );
                } else {
                    $( '.nav-tab-wrapper a:first' ).addClass( 'nav-tab-active' );
                }
                $( '.nav-tab-wrapper a' ).click( function( evt ) {
                    $( '.nav-tab-wrapper a' ).removeClass( 'nav-tab-active' );
                    $( this )
                        .addClass( 'nav-tab-active' )
                        .blur();
                    var clicked_group = $( this ).attr( 'href' );
                    if ( 'undefined' != typeof localStorage ) {
                        localStorage.setItem( 'activetab', $( this ).attr( 'href' ) );
                    }
                    $( '.group' ).hide();
                    $( clicked_group ).fadeIn();
                    evt.preventDefault();
                });

                $( '.wpsa-browse' ).on( 'click', function( event ) {
                    event.preventDefault();

                    var self = $( this );

                    // Create the media frame.
                    var file_frame = ( wp.media.frames.file_frame = wp.media({
                        title: self.data( 'uploader_title' ),
                        button: {
                            text: self.data( 'uploader_button_text' )
                        },
                        multiple: false
                    }) );

                    file_frame.on( 'select', function() {
                        attachment = file_frame
                            .state()
                            .get( 'selection' )
                            .first()
                            .toJSON();

                        self
                            .prev( '.wpsa-url' )
                            .val( attachment.url )
                            .change();
                    });

                    // Finally, open the modal
                    file_frame.open();
                });

                $( 'input.wpsa-url' )
                    .on( 'change keyup paste input', function() {
                        var self = $( this );
                        self
                            .next()
                            .parent()
                            .children( '.wpsa-image-preview' )
                            .children( 'img' )
                            .attr( 'src', self.val() );
                    })
                    .change();
            });

		</script>

		<style type="text/css">
            /** WordPress 3.8 Fix **/
            .form-table th {
                padding: 20px 10px;
            }

            #wpbody-content .metabox-holder {
                padding-top: 5px;
            }

            .wpsa-image-preview img {
                height: auto;
                max-width: 70px;
            }

            .wpsa-settings-separator {
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
		</style>
		<?php
	}
}
