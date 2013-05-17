<?php
require_once __DIR__ . '/form-controls.php';

// Make sure class name doesn't exist
if ( !class_exists( 'Lift_Search_Form' ) ) {

	/**
	 * Lift_Search_Form is a class for building the fields and html used in the Lift Search plugin. 
	 * This class uses the singleton design pattern, and shouldn't be called more than once on a document.
	 * 
	 * There are three filters within the class that can be used to modify the output:
	 * 
	 * 'lift_filters_default_fields' can be used to remove default search fields on the form.
	 * 
	 * 'lift_filters_form_field_objects' can be used to add or remove Voce Search Field objects.
	 * 
	 * 'lift_search_form' can be used to modify the form html output
	 */
	class Lift_Search_Form {

		private static $instances;

		/**
		 * Returns an instance of the search form based on the given WP_Query instance
		 * @global WP_Query $wp_query
		 * @param WP_Query $a_wp_query
		 * @return Lift_Search_Form
		 */
		public static function GetInstance( $a_wp_query = null ) {
			global $wp_query;
			if ( is_null( $a_wp_query ) ) {
				$a_wp_query = $wp_query;
			}

			$query_id = spl_object_hash( $a_wp_query );
			if ( !isset( self::$instances ) ) {
				self::$instances = array( );
			}

			if ( !isset( self::$instances[$query_id] ) ) {
				self::$instances[$query_id] = new Lift_Search_Form( $a_wp_query );
			}
			return self::$instances[$query_id];
		}

		private $fields = array( );

		/**
		 * WP_Query instance reference for search
		 * @var Lift_WP_Query 
		 */
		public $lift_query;

		/**
		 * Lift_Search_Form constructor.
		 */
		private function __construct( $wp_query ) {
			$this->lift_query = Lift_WP_Query::GetInstance( $wp_query );
			$this->fields = apply_filters( 'lift_form_filters', array(), $this );
		}

		/**
		 * Returns the base url for the search.
		 * 
		 * @todo allow the instance to be detached from the global site search
		 * @return string
		 */
		public function getSearchBaseURL() {
			return user_trailingslashit( site_url() );
		}

		/**
		 * Returns the current state of the search form.
		 * 
		 * @todo allow instances to be detached from the global request vars
		 * @return array
		 */
		public function getStateVars() {
			return array_merge( $_GET, $_POST );
		}

		/**
		 * Builds the sort by dropdown/select field.
		 */
		public function add_sort_field() {
			if ( !$selected = $this->lift_query->wp_query->get( 'orderby' ) ) {
				$selected = 'relevancy';
			}
			$options = array(
				'label' => ($selected) ? ucwords( $selected ) : 'Sort By',
				'value' => array(
					'Date' => 'date',
					'Relevancy' => 'relevancy'
				),
				'selected' => $selected,
			);
			$this->add_field( 'orderby', 'select', $options );
		}

		/**
		 * Builds the taxonomy checkboxes.
		 * @param type $tax_id Wordpress Taxonomy ID
		 * @param array $terms Array of Wordpress term objects
		 */
		public function add_taxonomy_checkbox_fields( $taxonomy ) {
			return;
			$selected_terms = Lift_Search_Form::get_query_var( $taxonomy );

			foreach ( $terms as $term ) {
				$facets = $wp_query->get( 'facets' );
				$label = (!empty( $facets ) && isset( $facets[$tax_id] ) && isset( $facets[$tax_id][$term->term_id] )) ? sprintf( '%s (%s)', $term->name, $facets[$tax_id][$term->term_id] ) : $term->name;
				$options = array(
					'label' => $label
				);
				if ( is_array( $selected_terms ) && in_array( $term->term_id, $selected_terms ) ) {
					$options['selected'] = true;
				}
				$this->add_field( $tax_id, 'select', $options );
			}
		}

		/**
		 * Builds the post type dropdown/select field.
		 */
		public function add_posttype_field() {

			$types = Lift_Search::get_indexed_post_types();
			$selected_types = Lift_Search_Form::get_query_var( 'lift_post_type' );
			$label = (!$selected_types ) ? 'All Types' : '';
			$selected_labels = array( );
			if ( !is_array( $selected_types ) ) {
				$selected_types = array( $selected_types );
			}

			$values = array(
				'All Types' => ''
			);

			foreach ( $types as $type ) {
				$num = (isset( $wp_query->query_vars['facets'] ) && isset( $wp_query->query_vars['facets']['post_type'][$type] )) ? sprintf( '(%s)', $wp_query->query_vars['facets']['post_type'][$type] ) : '';

				$type_object = get_post_type_object( $type );
				$values[sprintf( '%s %s', $type_object->label, $num )] = $type;
			}


			foreach ( $values as $k => $v ) {
				if ( in_array( $v, $selected_types ) ) {
					$selected_labels[] = $k;
				}
			}

			if ( !$label ) {
				$label = implode( ' / ', $selected_labels );
			}

			$options = array(
				'label' => $label,
				'value' => $values,
				'selected' => $selected_types,
			);
			$this->add_field( 'lift_post_type', 'select', $options );
		}

		/**
		 * Builds the start and end date fields. 
		 * The date_end field is hidden and always set to current time.
		 */
		public function add_date_fields() {
			$query_end = Lift_Search_Form::get_query_var( 'date_end' );
			$date_end = $query_end ? $query_end : time();
			$this->add_field( 'date_end', 'hidden', array(
				'value' => $date_end,
				'selected' => $date_end,
			) );
			$query_start = Lift_Search_Form::get_query_var( 'date_start' );
			$date_start = $query_start ? $query_start : 0;
			$values = array(
				'All Dates' => '',
				'24 Hours' => $date_end - DAY_IN_SECONDS,
				'7 Days' => $date_end - (DAY_IN_SECONDS * 7),
				'30 Days' => $date_end - (DAY_IN_SECONDS * 30)
			);

			$selected_label = 'Date';
			foreach ( $values as $k => $v ) {
				if ( $v == $date_start ) {
					$selected_label = $k;
				}
			}
			$label = ( $date_start ) ? $selected_label : 'All Dates';

			$this->add_field( 'date_start', 'select', array(
				'label' => $label,
				'value' => $values,
				'selected' => ( int ) $date_start,
			) );
		}

		/**
		 * Get custom query var from the wp_query
		 * @param string $var query variable
		 * @return array|boolean query variable if it exists, else false
		 */
		public function get_query_var( $var ) {
			return ( $val = get_query_var( $var ) ) ? $val : false;
		}

		/**
		 * Builds the html form using all fields in $this->fields.
		 * @return string search form
		 */
		public function form() {
			$search_term = (is_search()) ? get_search_query( false ) : "";
			$html = '<form role="search" class="lift-search" id="searchform" action="' . esc_url( $this->getSearchBaseURL() ) . '"><div>';
			$html .= sprintf( "<input type='text' name='s' id='s' value='%s' />", esc_attr( $search_term ) );
			$html .= ' <input type="submit" id="searchsubmit" value="' . esc_attr__( 'Search' ) . '" />';
			$html .= '<fieldset class="lift-search-form-filters"><ul>';
			foreach ( $this->fields as $field ) {
				$html .= apply_filters( 'lift_form_field_' . $field, '', $this, array( 'before_field' => '<li>', 'after_field' => '</li>' ) );
			}
			$html .= "</ul></fieldset>";
			$html .= "</div></form>";
			apply_filters( 'lift_search_form', $html );
			return $html;
		}

		public function loop() {
			$path = dirname( __DIR__ ) . '/lift-search/templates/lift-loop.php';
			include_once $path;
		}

		/**
		 * Renders the 
		 * @return string, The HTML output for the rendered filters
		 */
		public function form_filters() {
			if ( !is_search() ) {
				return;
			}

			$html = '<fieldset class="lift-search-form-filters">';
			foreach ( $fields as $field ) {
				if ( is_a( $field, 'Lift_Search_Field' ) ) {
					$html .= $field->element();
				}
			}
			$html .= "</fieldset>";
			// @TODO setting to add JS form controls
			if ( 1 == 1 ) {
				$html .= $this->js_form_controls();
			}
			return $html;
		}

		/**
		 * Build additional elements to override standard form controls
		 * @return string 
		 */
		public function js_form_controls() {
			$fields = apply_filters( 'lift_filters_form_field_objects', $this->fields );
			$counter = 1;
			$html = "<div class='lift-js-filters lift-hidden' style='display: none'><ul id='lift-filters'>";
			$html .= "<li class='first'>Filter by: </li>";
			foreach ( $fields as $field ) {
				if ( is_a( $field, 'Lift_Search_Field' ) ) {
					$html .= $field->faux_element( $counter == count( $fields ) );
				}
				$counter++;
			}
			$html .= "</ul></div>";
			return $html;
		}

	}

}

/**
 * Template Tags 
 */
if ( !function_exists( 'lift_search_form' ) ) {

	function lift_search_form() {
		echo Lift_Search_Form::GetInstance()->form();
	}

}

if ( !function_exists( 'lift_search_filters' ) ) {

	function lift_search_filters() {
		echo Lift_Search_Form::GetInstance()->form_filters();
	}

}

if ( !function_exists( 'lift_loop' ) ) {

	function lift_loop() {
		echo Lift_Search_Form::GetInstance()->loop();
	}

}

/**
 * Embed the Lift Search form in a sidebar
 * @class Lift_Form_Widget 
 */
class Lift_Form_Widget extends WP_Widget {

	/**
	 * @constructor 
	 */
	public function __construct() {
		parent::__construct(
			'lift_form_widget', "Lift Search Form", array( 'description' => "Add a Lift search form" )
		);
	}

	/**
	 * Output the widget
	 * 
	 * @method widget
	 */
	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );

		echo $before_widget;
		if ( $title )
			echo $before_title . esc_html( $title ) . $after_title;

		if ( class_exists( 'Lift_Search_Form' ) ) {
			echo Lift_Search_Form::GetInstance()->form();
		}
		echo $after_widget;
	}

	function form( $instance ) {
		$instance = wp_parse_args( ( array ) $instance, array( 'title' => '' ) );
		$title = $instance['title'];
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label></p>
		<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$new_instance = wp_parse_args( ( array ) $new_instance, array( 'title' => '' ) );
		$instance['title'] = strip_tags( $new_instance['title'] );
		return $instance;
	}

}

add_action( 'widgets_init', function() {
		register_widget( 'Lift_Form_Widget' );
	} );

