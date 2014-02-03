/*! Network Attachment Consolidator - v0.1.0
 * http://knowmike.com
 * Copyright (c) 2014; * Licensed GPLv2+ */
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
	var $ignore = $( document.getElementById( 'nac-ignore-galleries' ) ),
		$start  = $( document.getElementById( 'nac-start' ) ),
		$stop   = $( document.getElementById( 'nac-stop' ) ),
		$status = $( document.getElementById( 'nac-status' ) ),
		$step   = $( document.getElementById( 'nac-next-step' ) );
	// Define Handlers
	function ignoreGalleries( e ) {
		e.preventDefault();
		$ignore.prop( 'disabled', true );
		wp.ajax.send({
			'data': {
				'action': 'nac-ignoregalleries-ajax',
				'nonce' : nonce,
				'ignore': ( $ignore.prop('checked') ) ? '1' : '0'
			}
		})
		.done( handleIgnoreDone )
		.fail( handleIgnoreDone );
	}
	function handleIgnoreDone( val ) {
		if ( 'boolean' === typeof val ) {
			$ignore.prop('checked', val );
		}
		$ignore.prop( 'disabled', false );
	}
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
	$ignore.on( 'change', ignoreGalleries );
	$start.on( 'click', handleNacStart );
	$stop.on(  'click', handleNacStop );
	$( window ).on( 'beforeunload', handleUnload );
})( jQuery, this );