$ = jQuery;

$( document ).ready( () => {
	$( '#export_reservations' ).unbind().click( () => {
		reservation_export();
	} );
} );

async function reservation_export() {

	fetch( '/wp-json/re/v1/export' )
		.then( function( response ) {
			if ( response.status !== 200 ) {
				console.log( 'Looks like there was a problem. Status Code: ' + response.status );
				return;
			}

			// Examine the text in the response
			response.json().then( function( r ) {
				console.log( r.data.export );
				// window.open( r.data.url, '_blank' );
			} );
		} )
		.catch( function( err ) {
			console.log( 'Fetch Error :-S', err );
		} );

}