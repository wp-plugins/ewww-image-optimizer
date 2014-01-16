jQuery(function($) {
	$("#ewww-interval-slider").slider({
		min: 0,
		max: 50,
		step: 5,
		slide: function(event, ui) {
			$("#ewww-interval").val(ui.value);
		}
	});
	$("#ewww-interval").val($("#ewww-interval-slider").slider("value"));
});
jQuery(function($) {
	$("#ewww-delay-slider").slider({
		min: 0,
		max: 30,
		step: 5,
		slide: function(event, ui) {
			$("#ewww-delay").val(ui.value);
		}
	});
	$("#ewww-delay").val($("#ewww-delay-slider").slider("value"));
});
jQuery(document).ready(function($) {
	var k = 0;
	var ewww_force = 0;
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
                       	$('.wrap').prepend('<h2>Bulk Optimize</h2><div id="bulk-loading"></div><div id="bulk-progressbar"></div><div id="bulk-counter"></div><form id="bulk-stop" style="display:none;" method="post" action=""><br /><input type="submit" class="button-secondary action" value="Stop Optimizing" /></form><div id="bulk-status"></div><div id="bulk-forms"><p class="bulk-info">We have ' + attachments.length + ' images to optimize.</p><form id="bulk-start" class="bulk-form" method="post" action=""><input type="submit" class="button-secondary action" value="Start optimizing" /></form></div>');
		}
	} else if (ewww_vars.gallery == 'aux') {
		var init_action = 'bulk_aux_images_init';
		var filename_action = 'bulk_aux_images_filename';
		var loop_action = 'bulk_aux_images_loop';
		var cleanup_action = 'bulk_aux_images_cleanup';
	} else {
		var scan_action = 'bulk_aux_images_scan';
		var init_action = 'bulk_init';
		var filename_action = 'bulk_filename';
		var loop_action = 'bulk_loop';
		var cleanup_action = 'bulk_cleanup';
	}
	var i = 0;
	var ewww_interval = 0;
	var ewww_delay = 0;
	var ewww_countdown = 0;
	var ewww_sleep = 0;
	var init_data = {
	        action: init_action,
		_wpnonce: ewww_vars._wpnonce,
	};
	var table_action = 'bulk_aux_images_table';
	var table_count_action = 'bulk_aux_images_table_count';
	var import_action = 'bulk_aux_images_import';
	var ewww_aux = false;
	$('#aux-start').submit(function() {
		ewww_aux = true;
		init_action = 'bulk_aux_images_init';
		filename_action = 'bulk_aux_images_filename';
		loop_action = 'bulk_aux_images_loop';
		cleanup_action = 'bulk_aux_images_cleanup';
		var scan_data = {
			action: scan_action,
			scan: true,
		};
		$('#aux-start').hide();
		$('#ewww-scanning').show();
		$.post(ajaxurl, scan_data, function(response) {
			attachpost = response.replace(/&quot;/g, '"');
			attachments = $.parseJSON(attachpost);
			init_data = {
			        action: init_action,
				_wpnonce: ewww_vars._wpnonce,
			};
			if (attachments.length == 0) {
				$('#ewww-scanning').hide();
				$('#ewww-nothing').show();
			}
			else {
				startOpt();
			}
		});
		return false;
	});
	$('#import-start').submit(function() {
		$('#bulk-forms').hide();
//		document.getElementById('bulk-forms').style.display='none';
/*	        var loading_data = {
	                action: loading_action,
			loading_text: 'importing',
	        };*/
//	        $.post(ajaxurl, loading_data, function(response) {
	                $('#ewww-loading').show();
	                //$('#bulk-loading').html(response);
		        var import_data = {
		                action: import_action,
				_wpnonce: ewww_vars._wpnonce,
		        };
		        $.post(ajaxurl, import_data, function(response) {
		                $('#bulk-status').html(response);
		        	$('#ewww-loading').hide();
		        });
//	        });
		return false;
	});	
	$('#show-table').submit(function() {
		var pointer = 0;
		var total_pages = Math.ceil(ewww_vars.image_count / 50);
		$('.aux-table').show();
		$('#show-table').hide();
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
		startOpt();
		return false;
	});
	$('#bulk-stop').submit(function() {
		k = 9;
		$('#bulk-stop').hide();
		return false;
	});
	function startOpt () {
		ewww_interval = $('#ewww-interval-slider').slider('value');
		ewww_delay = $('#ewww-delay-slider').slider('value');
		ewww_countdown = ewww_interval;
		if ($('#ewww-force:checkbox:checked').val()) {
			ewww_force = 1;
		}
		$('.aux-table').hide();
//		$('#bulk-forms').hide();
		$('#bulk-stop').show();
				$('.bulk-form').hide();
				$('.bulk-info').hide();
				$('h3').hide();
	        $.post(ajaxurl, init_data, function(response) {
	                $('#bulk-loading').html(response);
			$('#bulk-progressbar').progressbar({ max: attachments.length });
			$('#bulk-counter').html('Optimized 0/' + attachments.length);
			processImage();
	        });
	}
	function processImage () {
//		alert($('#ewww-interval-slider').slider('value'));
		//alert($('#ewww-delay-slider').slider('value'));
		if (ewww_countdown == 0) {
			ewww_sleep = ewww_delay;
			ewww_countdown = ewww_interval;
		}
		attachment_id = attachments[i];
	        var filename_data = {
	                action: filename_action,
			_wpnonce: ewww_vars._wpnonce,
			attachment: attachment_id,
	        };
		$.post(ajaxurl, filename_data, function(response) {
		        $('#bulk-loading').html(response);
		});
//		alert(ewww_countdown + ' ' + ewww_sleep);
//		if (ewww_countdown == 0) {
//		}
	        var loop_data = {
	                action: loop_action,
			_wpnonce: ewww_vars._wpnonce,
			attachment: attachment_id,
			sleep: ewww_sleep,
			force: ewww_force,
	        };
	        var jqxhr = $.post(ajaxurl, loop_data, function(response) {
			i++;
			$('#bulk-progressbar').progressbar("option", "value", i );
			$('#bulk-counter').html('Optimized ' + i + '/' + attachments.length);
	                $('#bulk-status').append(response);
			var exceed=/exceeded/m;
			if (exceed.test(response)) {
				$('#bulk-loading').html('<p style="color: red"><b>License Exceeded</b></p>');
			}
			else if (k == 9) {
				jqxhr.abort();
				auxCleanup();
				$('#bulk-loading').html('<p style="color: red"><b>Optimization stopped, reload page to resume.</b></p>');
			}
			else if (i < attachments.length) {
				if (ewww_countdown > 0) {
					ewww_countdown--;
				}
				ewww_sleep = 0;
				processImage();
			}
			else {
			        var cleanup_data = {
			                action: cleanup_action,
					_wpnonce: ewww_vars._wpnonce,
			        };
			        $.post(ajaxurl, cleanup_data, function(response) {
			                $('#bulk-loading').html(response);
					$('#bulk-stop').hide();
			        });
				auxCleanup();
	/*			if (ewww_aux == true) {
					var table_count_data = {
						action: table_count_action,
					};
					$.post(ajaxurl, table_count_data, function(response) {
						ewww_vars.image_count = response;
					});
					$('#show-table').show();
					$('#empty-table').show();
					$('#table-info').show();
					$('h3').show();
				}*/
			}
	        })
		.fail(function() { 
			$('#bulk-loading').html('<p style="color: red"><b>Operation Interrupted</b></p>');
		});
	}
	function auxCleanup() {
		if (ewww_aux == true) {
			var table_count_data = {
				action: table_count_action,
			};
			$.post(ajaxurl, table_count_data, function(response) {
				ewww_vars.image_count = response;
			});
			$('#show-table').show();
			$('#empty-table').show();
			$('#table-info').show();
			$('h3').show();
		}
	}	
});
function ewwwRemoveImage(imageID) {
	var image_removal = {
		action: 'bulk_aux_images_remove',
		_wpnonce: ewww_vars._wpnonce,
		image_id: imageID,
	};
	jQuery.post(ajaxurl, image_removal, function(response) {
		if(response == '1') {
			jQuery('#image-' + imageID).remove();
			ewww_vars.image_count--;
			jQuery('.displaying-num').text(ewww_vars.image_count + ' total images');
		} else {
			alert("could not remove image from table.");
		}
	});
}
