jQuery(document).ready(function($) {
	var ewww_error_counter = 30;
	var sleep_action = 'ewww_sleep';
	if (!ewww_vars.attachments) {
		$('#webp-rewrite').submit(function() {
			var webp_rewrite_action = 'ewww_webp_rewrite';
			var webp_rewrite_data = {
				action: webp_rewrite_action,
				_wpnonce: ewww_vars._wpnonce,
			};
			$.post(ajaxurl, webp_rewrite_data, function(response) {
				$('#webp-rewrite-status').html('<b>' + response + '</b>');
				ewww_webp_image = document.getElementById("webp-image").src;
				document.getElementById("webp-image").src = ewww_webp_image + '#' + new Date().getTime();
			});
			return false;
		});
		$('#ewww-status-expand').click(function() {
			$('#ewww-collapsible-status').show();
			$('#ewww-status-expand').hide();
			$('#ewww-status-collapse').show();
		});
		$('#ewww-status-collapse').click(function() {
			$('#ewww-collapsible-status').hide();
			$('#ewww-status-expand').show();
			$('#ewww-status-collapse').hide();
		});
		$('#cloud-settings').hide();
		$('#general-settings').show();
		$('li.general-nav').addClass('ewww-selected');
		$('#optimization-settings').hide();
		$('#conversion-settings').hide();
		$('.cloud-nav').click(function() {
			$('.ewww-tab-nav li').removeClass('ewww-selected');
			$('li.cloud-nav').addClass('ewww-selected');
			$('.ewww-tab a').blur();
			$('#cloud-settings').show();
			$('#general-settings').hide();
			$('#optimization-settings').hide();
			$('#conversion-settings').hide();
		});
		$('.general-nav').click(function() {
			$('.ewww-tab-nav li').removeClass('ewww-selected');
			$('li.general-nav').addClass('ewww-selected');
			$('.ewww-tab a').blur();
			$('#cloud-settings').hide();
			$('#general-settings').show();
			$('#optimization-settings').hide();
			$('#conversion-settings').hide();
		});
		$('.optimization-nav').click(function() {
			$('.ewww-tab-nav li').removeClass('ewww-selected');
			$('li.optimization-nav').addClass('ewww-selected');
			$('.ewww-tab a').blur();
			$('#cloud-settings').hide();
			$('#general-settings').hide();
			$('#optimization-settings').show();
			$('#conversion-settings').hide();
		});
		$('.conversion-nav').click(function() {
			$('.ewww-tab-nav li').removeClass('ewww-selected');
			$('li.conversion-nav').addClass('ewww-selected');
			$('.ewww-tab a').blur();
			$('#cloud-settings').hide();
			$('#general-settings').hide();
			$('#optimization-settings').hide();
			$('#conversion-settings').show();
		});
		if (!ewww_vars.savings_todo) {
			$('#total_savings').text('0');
			return false;
		}
		var savings_counter = 0;
		var savings_total = 0;
		var savings_todo = parseInt(ewww_vars.savings_todo);
		var savings_action = 'ewww_savings_loop';
		var savings_data = {
		        action: savings_action,
			_wpnonce: ewww_vars._wpnonce,
			savings_counter: savings_counter,
			savings_todo: savings_todo,
		};
		loopSavings();
		return false;
	} else {
	$(function() {
		$("#ewww-delay-slider").slider({
			min: 0,
			max: 30,
			value: $("#ewww-delay").val(),
			slide: function(event, ui) {
				$("#ewww-delay").val(ui.value);
			}
		});
	});
	// cleanup the attachments array
	var attachpost = ewww_vars.attachments.replace(/&quot;/g, '"');
	var attachments = $.parseJSON(attachpost);
	var i = 0;
	var k = 0;
	var import_total = 0;
	var ewww_force = 0;
	var ewww_delay = 0;
	var ewww_aux = false;
	var ewww_main = false;
	// initialize the ajax actions for the appropriate bulk page
	if (ewww_vars.gallery == 'flag') {
		var init_action = 'bulk_flag_init';
		var filename_action = 'bulk_flag_filename';
		var loop_action = 'bulk_flag_loop';
		var cleanup_action = 'bulk_flag_cleanup';
	} else if (ewww_vars.gallery == 'nextgen') {
		var preview_action = 'bulk_ngg_preview';
		var init_action = 'bulk_ngg_init';
		var filename_action = 'bulk_ngg_filename';
		var loop_action = 'bulk_ngg_loop';
		var cleanup_action = 'bulk_ngg_cleanup';
		// this loads inline on the nextgen gallery management pages
		if (!document.getElementById('bulk-loading')) {
			var preview_data = {
			        action: preview_action,
				inline: 1,
			};
			$.post(ajaxurl, preview_data, function(response) {
        	               	$('.wrap').prepend(response);
				$('#bulk-start').submit(function() {
					startOpt();
					return false;
				});
			});
		}
	} else {
		var scan_action = 'bulk_aux_images_scan';
		var init_action = 'bulk_init';
		var filename_action = 'bulk_filename';
		var loop_action = 'bulk_loop';
		var cleanup_action = 'bulk_cleanup';
		ewww_main = true;
	}
	var init_data = {
	        action: init_action,
		_wpnonce: ewww_vars._wpnonce,
	};
	var table_action = 'bulk_aux_images_table';
	var table_count_action = 'bulk_aux_images_table_count';
	var import_init_action = 'bulk_import_init';
	var import_loop_action = 'bulk_import_loop';
	$('#aux-start').submit(function() {
		ewww_aux = true;
		init_action = 'bulk_aux_images_init';
		filename_action = 'bulk_aux_images_filename';
		loop_action = 'bulk_aux_images_loop';
		cleanup_action = 'bulk_aux_images_cleanup';
		if ($('#ewww-force:checkbox:checked').val()) {
			ewww_force = 1;
		}
		var scan_data = {
			action: scan_action,
			force: ewww_force,
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
	        })
		.fail(function() { 
			$('#ewww-scanning').html('<p style="color: red"><b>Operation timed out, you may need to increase the max_execution_time for PHP</b></p>');
		});
		return false;
	});
	$('#import-start').submit(function() {
		$('.bulk-info').hide();
		$('#import-start').hide();
	        $('#ewww-loading').show();
		var import_init_data = {
			action: import_init_action,
			_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, import_init_data, function(response) {
			import_total = response;
			bulkImport();
		});
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
	}
	function loopSavings() {
	        $.post(ajaxurl, savings_data, function(response) {
			savings_total = savings_total + parseInt(response);
			if (savings_todo < 0) {
				savings_action = 'ewww_savings_finish';
				savings_data = {
				        action: savings_action,
					_wpnonce: ewww_vars._wpnonce,
					savings_total: savings_total,
				};
	        		$.post(ajaxurl, savings_data, function(response) {
					$('#total_savings').text(response);
				});
			} else {
				savings_data = {
				        action: savings_action,
					_wpnonce: ewww_vars._wpnonce,
					savings_counter: savings_counter,
					savings_todo: savings_todo,
				};
				savings_todo -= 1000;
				savings_counter += 1000;
				loopSavings();
			}
	        });
	}
	function startOpt () {
		k = 0;
		$('#bulk-stop').submit(function() {
			k = 9;
			$('#bulk-stop').hide();
			return false;
		});
		if ( ! $('#ewww-delay').val().match( /^[1-9][0-9]*$/) ) {
			ewww_delay = 0;
		} else {
			ewww_delay = $('#ewww-delay').val();
		}
		$('.aux-table').hide();
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
		attachment_id = attachments[i];
	        var filename_data = {
	                action: filename_action,
			_wpnonce: ewww_vars._wpnonce,
			attachment: attachment_id,
	        };
		$.post(ajaxurl, filename_data, function(response) {
			if (k != 9) {
		        	$('#bulk-loading').html(response);
			}
		});
		if ($('#ewww-force:checkbox:checked').val()) {
			ewww_force = 1;
		}
	        var loop_data = {
	                action: loop_action,
			_wpnonce: ewww_vars._wpnonce,
			attachment: attachment_id,
			sleep: ewww_delay,
			force: ewww_force,
	        };
	        var jqxhr = $.post(ajaxurl, loop_data, function(response) {
			i++;
			$('#bulk-progressbar').progressbar("option", "value", i );
			$('#bulk-counter').html('Optimized ' + i + '/' + attachments.length);
	                $('#bulk-status').append( response );
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
				ewww_error_counter = 30;
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
					auxCleanup();
			        });
			}
	        })
		.fail(function() { 
			if (ewww_error_counter == 0) {
				$('#bulk-loading').html('<p style="color: red"><b>Operation Interrupted</b></p>');
			} else {
				$('#bulk-loading').html('<p style="color: red"><b>Temporary failure, retrying for ' + ewww_error_counter + ' more seconds.</b></p>');
				ewww_error_counter--;
				setTimeout(function() {
					processImage();
				}, 1000);
			}
		});
	}
	function bulkImport() {
		var import_loop_data = {
			action: import_loop_action,
			_wpnonce: ewww_vars._wpnonce,
		};
	        var jqxhr = $.post(ajaxurl, import_loop_data, function(response) {
			var unfinished=/^\d+$/m;
			if (unfinished.test(response)) {
				$('#bulk-status').html(response + '/' + import_total);
				ewww_error_counter = 30;
				bulkImport();
			}
			else {
				$('#bulk-status').html(response);
				$('#ewww-loading').hide();
			}
	        })
		.fail(function() { 
			if (ewww_error_counter == 0) {
				$('#ewww-loading').hide();
				$('#bulk-status').html('<p style="color: red"><b>Operation Interrupted</b></p>');
			} else {
				$('#bulk-status').html('<p style="color: red"><b>Temporary failure, retrying for ' + ewww_error_counter + ' more seconds.</b></p>');
				ewww_error_counter--;
				setTimeout(function() {
					bulkImport();
				}, 1000);
			}
		});
	}
	function auxCleanup() {
		if (ewww_main == true) {
			var table_count_data = {
				action: table_count_action,
				inline: 1,
			};
			$.post(ajaxurl, table_count_data, function(response) {
				ewww_vars.image_count = response;
			});
			$('#show-table').show();
			$('#empty-table').show();
			$('#table-info').show();
			$('.bulk-form').show();
			$('.media-info').show();
			$('h3').show();
			if (ewww_aux == true) {
				$('#aux-first').hide();
				$('#aux-again').show();
			} else {
				$('#bulk-first').hide();
				$('#bulk-again').show();
			}
			attachpost = ewww_vars.attachments.replace(/&quot;/g, '"');
			attachments = $.parseJSON(attachpost);
			init_action = 'bulk_init';
			filename_action = 'bulk_filename';
			loop_action = 'bulk_loop';
			cleanup_action = 'bulk_cleanup';
			init_data = {
			        action: init_action,
				_wpnonce: ewww_vars._wpnonce,
			};
			ewww_aux = false;
			i = 0;
			ewww_force = 0;
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
