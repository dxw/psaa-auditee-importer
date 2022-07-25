/**
 * Read CSV file on client side, process into array, then send to server for handling.
 * Uses https://github.com/evanplaice/jquery-csv
 * Based on http://evanplaice.github.io/jquery-csv/examples/file-handling.html
 */

(function ( $ ) {

	// Bind to file upload button.
	$( document ).ready( function( $ ) {
		if ( isAPIAvailable() ) {
			$( '#ht_psaa_files' ).bind( 'change', handleFileSelect );
		}
	});

	// Check File API is available in this browser.
	function isAPIAvailable() {
		if ( window.File && window.FileReader && window.FileList && window.Blob ) {
			// Great success! All the File APIs are supported.
			return true;
		} else {
			// source: File API availability - http://caniuse.com/#feat=fileapi
			// source: <output> availability - http://html5doctor.com/the-output-element/
			document.writeln('The HTML5 APIs used in this form are only available in the following browsers:<br />');
			// 6.0 File API & 13.0 <output>
			document.writeln(' - Google Chrome: 13.0 or later<br />');
			// 3.6 File API & 6.0 <output>
			document.writeln(' - Mozilla Firefox: 6.0 or later<br />');
			// 10.0 File API & 10.0 <output>
			document.writeln(' - Internet Explorer: Not supported (partial support expected in 10.0)<br />');
			// ? File API & 5.1 <output>
			document.writeln(' - Safari: Not supported<br />');
			// ? File API & 9.2 <output>
			document.writeln(' - Opera: Not supported');
			return false;
		}
	}

	// Begin file processing when selected.
	function handleFileSelect( evt ) {
		var files = evt.target.files; // FileList object
		var file = files[0];

		// Read and display the file metadata.
		var output = ''
		output += '<span style="font-weight:bold;">' + escape(file.name) + '</span><br />\n';
		output += ' - FileType: ' + ( file.type || 'n/a' ) + '<br />\n';
		output += ' - FileSize: ' + file.size + ' bytes<br />\n';
		output += ' - LastModified: ' + ( file.lastModifiedDate ? file.lastModifiedDate.toLocaleDateString() : 'n/a' ) + '<br />\n';
		$( '#ht_psaa_list' ).append( output );

		// Read and process the file.
		processFileDate( file );
	}

	// Process the file.
	function processFileDate( file ) {
		// Show loading spinner.
		$( '#ht_psaa_waiting' ).show();

		// Start reading the file.
		var reader = new FileReader();
		reader.readAsText( file );
		reader.onload = function( event ){
			var csv = event.target.result;
			var processed_count = 0;
			// Parse file as CSV into an array.
			var data = $.csv.toArrays( csv );
			for( var row in data ) {
				// Wrap processing in timeout to slow things down.
				setTimeout( function( row ) {
					// Ajax call to processing function.
					ajaxdata = {
						action: 'ht_psaa_auditee_results',
						nonce: ht_psaa_ab_vars.ht_psaa_ab_nonce,
						csvrow: data[row]
					}
					$.post( ajaxurl, ajaxdata, function( response ) {
						$( '#ht_psaa_contents' ).append( '<li>' + response + '</li>\r\n' );
					}).done( function() {
						// Keep track of how many completed.
						processed_count++;
						$( '#ht_psaa_count' ).text( processed_count );
						// Remove loading spinner when processed count matches array length.
						if ( processed_count === data.length ) {
							$( '#ht_psaa_waiting' ).hide();
							$( '#ht_psaa_contents' ).prepend( '<li><strong>Total processed: ' + processed_count + '</strong></li>\r\n' );
						}
					})
				}, 1000 * row, row );
			}
		};
		// Alert if file read error.
		reader.onerror = function(){
			alert( 'Unable to read ' + file.fileName );
		};
	}
}( jQuery ));
