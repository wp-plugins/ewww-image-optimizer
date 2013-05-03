/* This script and many more are available free online at
The JavaScript Source!! http://www.javascriptsource.com
Created by: Abraham Joffe :: http://www.abrahamjoffe.com.au/ */

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
		} else {
			var init_action = 'bulk_init';
			var filename_action = 'bulk_filename';
			var loop_action = 'bulk_loop';
			var cleanup_action = 'bulk_cleanup';
		}
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
