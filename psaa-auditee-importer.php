<?php
/**
 * Plugin Name: PSAA - Audited Body Importer
 * Plugin URI: http://www.helpfultechnology.com
 * Description: Imports PSAA's Audited Bodies from a specially-formatted CSV file to the custom post type.
 * Author: Phil Banks
 * Version: 0.2
 * Author URI: http://www.helpfultechnology.com
 *
 * @package psaa-auditee-importer
 */

// Updated for new PSAA Audited Body IDs in Sep 2018

if ( ! function_exists( 'parse_csv' ) ) {
	/**
	 * Parse CSV into array for processing, handles \n within fields.
	 * From http://php.net/manual/en/function.str-getcsv.php#111665
	 *
	 * @param  string  $csv_string       CSV data for processing.
	 * @param  string  $delimiter        Delimiter character used in CSV.
	 * @param  boolean $skip_empty_lines Whether to parse empty lines in CSV.
	 * @param  boolean $trim_fields      Whether to trim CSV cels before processing.
	 * @return array                     two-dimensional array or rows and fields.
	 */
	function parse_csv( $csv_string, $delimiter = ',', $skip_empty_lines = true, $trim_fields = true ) {
	    $enc = preg_replace( '/(?<!")""/', '!!Q!!', $csv_string );
	    $enc = preg_replace_callback(
	        '/"(.*?)"/s',
	        function( $field ) {
	            return urlencode( utf8_encode( $field[1] ) );
	        },
	        $enc
	    );
	    $lines = preg_split( $skip_empty_lines ? ( $trim_fields ? '/( *\R)+/s' : '/\R+/s' ) : '/\R/s', $enc );
	    return array_map(
	        function( $line ) use ( $delimiter, $trim_fields ) {
	            $fields = $trim_fields ? array_map( 'trim', explode( $delimiter, $line ) ) : explode( $delimiter, $line );
	            return array_map(
	                function( $field ) {
	                    return str_replace( '!!Q!!', '"', utf8_decode( urldecode( $field ) ) );
	                },
	                $fields
	            );
	        },
	        $lines
	    );
	}
}


/**
 * Create plugin menu pages.
 */
function ht_psaa_auditee_importer_menu() {
	global $ht_psaa_auditee_importer_hook;
	$ht_psaa_auditee_importer_hook = add_submenu_page( 'tools.php','PSAA - Audited Body Importer', 'PSAA - Audited Body Importer', 'manage_options', 'psaa-auditee-importer', 'ht_psaa_auditee_importer_options' );
}
add_action( 'admin_menu', 'ht_psaa_auditee_importer_menu' );


/**
 * Generate plugin options / action page content.
 */
function ht_psaa_auditee_importer_options() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
	}

	echo '<div class="wrap">';
	screen_icon();
	echo '<h1>' . esc_html__( ' PSAA - Audited Body Importer' ) . '</h1>';

	echo '<p>Select a CSV file from your computer - with no header row.</p>
	<pre>
	 * CSV Structure
	 *
	 * [0 : A] (New) Body ID
	 * [1 : B] Name
	 * [2 : C] Body type
	 * [3 : D] Opted In ("Yes" means, check box and insert Year from Year col.)
	 * [4 : E] Audit Firm
	 * [5 : F] Engagement Lead name
	 * [6 : G] Engagement Lead email
	 * [7 : H] Fee
	 * [8 : I] Year being imported
	 *
	</pre>
	<div id="ht_psaa_inputs" class="clearfix">
		<label>Select your CSV file (no header row): <input type="file" id="ht_psaa_files" name="files[]" multiple /></label>
	</div>
	<hr />
	<output id="ht_psaa_list"></output>
	<hr />
	<span id="ht_psaa_waiting" style="display:none;">Importing... <img src="' . esc_url( admin_url( '/images/wpspin_light.gif' ) ) . '" /> <span id="ht_psaa_count"></span></span>
	<ul id="ht_psaa_contents"></ul>';

	echo '</div>';
}



/**
 * Import a single row of data / entry into WP.
 *
 * @param  array $csv_row Single row of data from the CSV to import.
 * @return bool           True/False depending on outcome.
 */
function ht_psaa_auditee_importer_import( $csv_row ) {

	/**
	 * CSV Structure
	 *
	 * [0 : A] (New) Body ID
	 * [1 : B] Name
	 * [2 : C] Body type
	 * [3 : D] Opted In ("Yes" means, check box and insert Year from Year col.)
	 * [4 : E] Audit Firm
	 * [5 : F] Engagement Lead name
	 * [6 : G] Engagement Lead email
	 * [7 : H] Fee
	 * [8 : I] Year being imported
	 *
	 * /

	/**
	 * Create / update Auditee.
	 */
	$auditee_id = 0;
	// First check for existing entry.
	global $wpdb;
	$meta_key = 'new_body_id';
	$meta_value = trim( $csv_row[0] );
	$existing_auditee = $wpdb->get_var(
		$wpdb->prepare(
			"
				SELECT post_id
				FROM $wpdb->postmeta
				WHERE meta_key = %s
				AND meta_value = %s
			",
			$meta_key,
			$meta_value
		)
	);
	
	if ( ! empty( $existing_auditee ) && is_numeric( $existing_auditee ) ) {
		$auditee_id = $existing_auditee;
	}
	// Create new Auditee / update existing title.
	$auditee = array(
		'post_title' => trim( $csv_row[1] ),
		'post_content' => '',
		'post_excerpt' => '',
		'post_type' => 'auditedbody',
		'post_status' => 'publish',
		'ID' => $auditee_id,
	);
	$auditee_id = wp_insert_post( $auditee );

	// Store the ACF fields.
	//update_field( 'field_592341e96c5e9', trim( $csv_row[0] ), $auditee_id ); // Body ID.
	
	// might still need this for new additions
	update_field( 'field_5bac06669663f', trim( $csv_row[0] ), $auditee_id ); // New Body ID.
	
	if ( ! empty( trim( $csv_row[3] ) ) && 'Yes' === trim( $csv_row[3] ) ) { // Yes = Opted In.
		update_field( 'field_593e3c1d4212e', true, $auditee_id ); // Opted In?
		update_field( 'field_593e3c724212f', trim( $csv_row[8] ), $auditee_id ); // Year opted in from Year col.
	}

	if ( trim( $csv_row[4] ) && trim( $csv_row[5] ) && trim( $csv_row[6] ) && trim( $csv_row[7] ) ) {

		// Create Audit Firm if needed.
		$existing_audit_firm = get_page_by_path( trim( sanitize_title( $csv_row[4] ) ), OBJECT, array( 'auditfirm' ) );
		if ( ! empty( $existing_audit_firm ) && is_numeric( $existing_audit_firm->ID ) ) {
			$auditfirm_id = $existing_audit_firm->ID;
		} else { // Create new.
			$auditfirm = array(
				'post_title' => trim( $csv_row[4] ),
				'post_content' => '',
				'post_excerpt' => '',
				'post_type' => 'auditfirm',
				'post_status' => 'publish',
			);
			$auditfirm_id = wp_insert_post( $auditfirm );
		}

		// Store more ACF fields.
		$this_years_data = array(
				'field_5963e28612fba' => trim( $csv_row[8] ),
				'field_593e38b142125' => trim( $csv_row[7] ),
				'field_592341f96c5ea' => array( trim( $auditfirm_id ) ),
				'field_592342046c5eb' => trim( $csv_row[5] ),
				'field_593e4dfdae015' => trim( $csv_row[6] ),
		);
		$created = add_row( 'field_5963e130247ee', $this_years_data, $auditee_id );
	}

	// Taxonomies.
	wp_set_object_terms( $auditee_id, trim( $csv_row[2] ), 'bodytype' ); // Body Type.

	//update_field( 'field_593e3b3b4212a', trim( $csv_row[] ), $auditee_id ); // Audit Letters.
	//update_field( 'field_593e557555cd5', trim( $csv_row[] ), $auditee_id ); // Other reports.

	if ( is_wp_error( $auditee_id ) ) {
		return false;
	}
	return true;

}


/**
 * Load scripts for ajax importing.
 */
function ht_psaa_auditee_importer_script() {
	global $ht_psaa_auditee_importer_hook;
	$screen = get_current_screen();
	if ( $screen->id !== $ht_psaa_auditee_importer_hook ) {
		return;
	}

	wp_enqueue_script( 'jquery-csv', plugin_dir_url( __FILE__ ) . 'js/jquery.csv.min.js', array( 'jquery' ), '201701031622', true );
	wp_enqueue_script( 'ht-psaa-ab-ajax', plugin_dir_url( __FILE__ ) . 'js/psaa-auditee-importer-ajax.js', array( 'jquery' ), '201701031622', true );
	wp_localize_script( 'ht-psaa-ab-ajax', 'ht_psaa_ab_vars', array(
		'ht_psaa_ab_nonce' => wp_create_nonce( 'ht-psaa-ab-nonce' ),
	) );

}
add_action( 'admin_enqueue_scripts', 'ht_psaa_auditee_importer_script' );


/**
 * Ajax processing of import data.
 * Handles single rows of data only.
 */
function ht_psaa_auditee_importer_ajax() {

	if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'ht-psaa-ab-nonce' ) ) {
		die( 'Something looks wrong here.' );
	}

	$csv_row = $_REQUEST['csvrow'];

	if ( strlen( $csv_row[1] ) > 0 ) { // Avoid empty entries being created.

		$imported = ht_psaa_auditee_importer_import( $csv_row );

		if ( false === $imported ) {
			echo wp_kses_post( 'Processed: ' . $csv_row[1] . '<br/>' );
		} else {
			echo wp_kses_post( 'Processed: ' . $csv_row[1] . '<br/>' );
		}

	} // end sanity check

	wp_die();
}
add_action( 'wp_ajax_ht_psaa_auditee_results', 'ht_psaa_auditee_importer_ajax' );
