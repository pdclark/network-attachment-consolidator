/*! Network Attachment Consolidator - v0.1.0
 * http://knowmike.com
 * Copyright (c) 2014; * Licensed GPLv2+ */
jQuery(document).ready(function($){
	'use strict';
	
	$('#naconsolidator-run').click(function ( event ) {

		$('img#naconsolidator-loading').show();
		$('#naconsolidator-run, p.submit input').attr('disabled', true);

		var json = JSON.parse( $('#naconsolidator_image_object').html() );
		for ( var key in json) {
            var data = {
				action: 'naconsolidator_ajax',
				image_data: json[key],
				nac_nonce: nac_vars.nac_nonce
			};
			// note the async option to process one at a time
			$.ajax({
				 type: 'POST',
				 url: ajaxurl, 
				 data: data, 
				 async: false,
				 global: false,
				 context: document.body,
				 success: function( response ){
					var count = parseInt( $('#naconsolidator_progress').html() );
					++count;
					$('#naconsolidator_progress').html( count );
				}
			});
        };
	});
});