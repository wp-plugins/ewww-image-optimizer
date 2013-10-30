jQuery(document).ready(function($) {
	var attachpost = ewww_vars.attachments.replace(/&quot;/g, '"');
	var attachments = $.parseJSON(attachpost);
	if (ewww_vars.gallery == 'flag') {
		var init_action = 'bulk_flag_init';
		var filename_action = 'bulk_flag_filename';
		var loop_action = 'bulk_flag_loop';
		var cleanup_action = 'bulk_flag_cleanup';
	} else if (ewww_vars.gallery == 'nextgen') {
		var init_action = 'bulk_ngg_init';
		var filename_action = 'bulk_ngg_filename';
		var loop_action = 'bulk_ngg_loop';
		var cleanup_action = 'bulk_ngg_cleanup';
		if (!document.getElementById('bulk-loading')) {
                       	$('.wrap').prepend('<h2>Bulk Optimize</h2><div id="bulk-loading"></div><div id="bulk-progressbar"></div><div id="bulk-counter"></div><div id="bulk-status"></div><div id="bulk-forms"><p>We have ' + attachments.length + ' images to optimize.</p><form id="bulk-start" method="post" action=""><input type="submit" class="button-secondary action" value="Start optimizing" /></form></div>');
		}
	} else if (ewww_vars.gallery == 'aux') {
		var table_action = 'bulk_aux_images_table';
		var import_action = 'bulk_aux_images_import';
		var loading_action = 'bulk_aux_images_loading';
		var init_action = 'bulk_aux_images_init';
		var filename_action = 'bulk_aux_images_filename';
		var loop_action = 'bulk_aux_images_loop';
		var cleanup_action = 'bulk_aux_images_cleanup';
	} else {
		var init_action = 'bulk_init';
		var filename_action = 'bulk_filename';
		var loop_action = 'bulk_loop';
		var cleanup_action = 'bulk_cleanup';
	}
	$('#import-start').submit(function() {
		document.getElementById('bulk-forms').style.display='none';
	        var loading_data = {
	                action: loading_action,
//			_wpnonce: ewww_vars._wpnonce,
	        };
//		var i = 0;
	        $.post(ajaxurl, loading_data, function(response) {
	                $('#bulk-loading').html(response);
//			$('#bulk-progressbar').progressbar({ max: attachments.length });
//			$('#bulk-counter').html('Optimized 0/' + attachments.length);
//			processImage();
		        var import_data = {
		                action: import_action,
				_wpnonce: ewww_vars._wpnonce,
		        };
		        $.post(ajaxurl, import_data, function(response) {
		                $('#bulk-status').html(response);
		        	$('#bulk-loading').html('');
		        });
	        });
		return false;
	});	
	$('#show-table').submit(function() {
		var pointer = 0;
		var total_pages = Math.ceil(ewww_vars.image_count / 50);
		$('#table-button').hide();
		if (ewww_vars.image_count >= 50) {
			$('.tablenav').show();
			$('#next-images').show();
			$('.last-page').show();
		}
	        var table_data = {
	                action: table_action,
			_wpnonce: ewww_vars._wpnonce,
			offset: pointer,
	        };
		$('.displaying-num').text(ewww_vars.image_count + ' total images');
		$.post(ajaxurl, table_data, function(response) {
			$('#bulk-table').html(response);
		});
		$('.current-page').text(pointer + 1);
		$('.total-pages').text(total_pages);
		$('#pointer').text(pointer);
		return false;
	});
	$('#next-images').click(function() {
		var pointer = $('#pointer').text();
		pointer++;
	        var table_data = {
	                action: table_action,
			_wpnonce: ewww_vars._wpnonce,
			offset: pointer,
	        };
		$.post(ajaxurl, table_data, function(response) {
			$('#bulk-table').html(response);
		});
		if (ewww_vars.image_count <= ((pointer + 1) * 50)) {
			$('#next-images').hide();
			$('.last-page').hide();
		}
		$('.current-page').text(pointer + 1);
		$('#pointer').text(pointer);
		$('#prev-images').show();
		$('.first-page').show();
		return false;
	});
	$('#prev-images').click(function() {
		var pointer = $('#pointer').text();
		pointer--;
	        var table_data = {
	                action: table_action,
			_wpnonce: ewww_vars._wpnonce,
			offset: pointer,
	        };
		$.post(ajaxurl, table_data, function(response) {
			$('#bulk-table').html(response);
		});
		if (!pointer) {
			$('#prev-images').hide();
			$('.first-page').hide();
		}
		$('.current-page').text(pointer + 1);
		$('#pointer').text(pointer);
		$('#next-images').show();
		$('.last-page').show();
		return false;
	});
	$('.last-page').click(function() {
		var pointer = $('.total-pages').text();
		pointer--;
	        var table_data = {
	                action: table_action,
			_wpnonce: ewww_vars._wpnonce,
			offset: pointer,
	        };
		$.post(ajaxurl, table_data, function(response) {
			$('#bulk-table').html(response);
		});
		$('#next-images').hide();
		$('.last-page').hide();
		$('.current-page').text(pointer + 1);
		$('#pointer').text(pointer);
		$('#prev-images').show();
		$('.first-page').show();
		return false;
	});
	$('.first-page').click(function() {
		var pointer = 0;
	        var table_data = {
	                action: table_action,
			_wpnonce: ewww_vars._wpnonce,
			offset: pointer,
	        };
		$.post(ajaxurl, table_data, function(response) {
			$('#bulk-table').html(response);
		});
		$('#prev-images').hide();
		$('.first-page').hide();
		$('.current-page').text(pointer + 1);
		$('#pointer').text(pointer);
		$('#next-images').show();
		$('.last-page').show();
		return false;
	});
	$('#bulk-start').submit(function() {
		document.getElementById('bulk-forms').style.display='none';
	        var init_data = {
	                action: init_action,
			_wpnonce: ewww_vars._wpnonce,
	        };
		var i = 0;
	        $.post(ajaxurl, init_data, function(response) {
	                $('#bulk-loading').html(response);
			$('#bulk-progressbar').progressbar({ max: attachments.length });
			$('#bulk-counter').html('Optimized 0/' + attachments.length);
			processImage();
	        });
		function processImage () {
			attachment_id = attachments[i];
		        var filename_data = {
		                action: filename_action,
				_wpnonce: ewww_vars._wpnonce,
				attachment: attachment_id,
		        };
			$.post(ajaxurl, filename_data, function(response) {
			        $('#bulk-loading').html(response);
			});
		        var loop_data = {
		                action: loop_action,
				_wpnonce: ewww_vars._wpnonce,
				attachment: attachment_id,
		        };
		        var jqxhr = $.post(ajaxurl, loop_data, function(response) {
				i++;
				$('#bulk-progressbar').progressbar("option", "value", i );
				$('#bulk-counter').html('Optimized ' + i + '/' + attachments.length);
		                $('#bulk-status').append(response);
				if (i < attachments.length) {
					processImage();
				}
				else {
				        var cleanup_data = {
				                action: cleanup_action,
						_wpnonce: ewww_vars._wpnonce,
				        };
				        $.post(ajaxurl, cleanup_data, function(response) {
				                $('#bulk-loading').html(response);
				        });
				}
				
		        })
			.fail(function() { 
				$('#bulk-loading').html('<p style="color: red"><b>Operation Interrupted</b></p>');
			});
		}
		return false;
	});
});
