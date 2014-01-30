/**
 * Network Attachment Consolidator
 * http://knowmike.com
 *
 * Copyright (c) 2014 Michael Jordan
 * Licensed under the GPLv2+ license.
 */
// Our script loads in the footer, so no need to wait for DOM ready.
(function( $, window, undefined ){
	'use strict';
	
	// Declar vars.
	var active = false, nonce = window.nacNonce || '', wp = window.wp || { ajax: {} };
	// Make sure the ajax helper is present before trying to use it.
	if ( 'function' !== typeof wp.ajax.send ) {
		return;
	}
	// Cache our needed selectors.
	var $start  = $( document.getElementById( 'nac-start' ) ),
		$stop   = $( document.getElementById( 'nac-stop' ) ),
		$status = $( document.getElementById( 'nac-status' ) ),
		$step   = $( document.getElementById( 'nac-next-step' ) );
	// Define Handlers
	function handleNacStart( e ) {
		e.preventDefault();
		if ( ! $start.is( ':disabled' ) ) {
			$start.attr( 'disabled', 'disabled' );
			$stop.removeAttr( 'disabled' );
			$status.html( 'Running...' );
			active = true;
			run();
		}
	}
	function handleNacStop( e ) {
		if ( 'function' === typeof e.preventDefault ) {
			e.preventDefault();
		}
		if ( ! $stop.is( ':disabled' ) ) {
			$stop.attr( 'disabled', 'disabled' );
			$start.removeAttr( 'disabled' );
			active = false;
			if ( ! e.complete ) {
				$status.html( 'Paused' );
			}
		}
	}
	function run(){
		if ( active ) {
			wp.ajax.send({
				'data': {
					'action': 'naconsolidator-ajax',
					'nonce' : nonce
				}
			})
			.done( handleReturn )
			.fail( handleFail );
		}
	}
	function handleReturn( status ) {
		$step.html( _.escape( status ) );
		if ( status !== 'Finished' ) {
			run();
		} else {
			$status.html( 'Complete' );
			handleNacStop( { complete: true } );
		}
	}
	function handleFail( error ) {
		handleNacStop({});
		$step.html( 'Error: ' + _.escape( error ) );
	}
	function handleUnload( e ) {
		if ( active ) {
			return 'Warning: The image consolidation script is currently running.';
		}
	}
	// Bind handlers
	$start.on( 'click', handleNacStart );
	$stop.on(  'click', handleNacStop );
	$( window ).on( 'beforeunload', handleUnload );
})( jQuery, this );