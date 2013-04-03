/* This script and many more are available free online at
The JavaScript Source!! http://www.javascriptsource.com
Created by: Abraham Joffe :: http://www.abrahamjoffe.com.au/ */

/*var startTime=new Date();

function currentTime(){
  var a=Math.floor((new Date()-startTime)/100)/10;
  if (a%1==0) a+=".0";
  document.getElementById("endTime").innerHTML=a;
}

window.onload=function(){
  clearTimeout(loopTime);
}*/
jQuery(document).ready(function($) {
	$('#bulk-start').submit(function() {
		document.getElementById('bulk-forms').style.display='none';
	        var init_data = {
	                action: 'bulk_init',
			_wpnonce: ewww_vars._wpnonce,
	        };
		var i = 0;
		  //              $('#bulk-status').append(ewww_vars.attachments + '<br>');
		var attachments = $.parseJSON(ewww_vars.attachments);
		//                $('#bulk-status').append(attachments + '<br>');
	        $.post(ajaxurl, init_data, function(response) {
	                $('#bulk-loading').html(response);
			$('#bulk-progressbar').progressbar({ max: attachments.length });
			$('#bulk-counter').html('Optimized 0/' + attachments.length);
			processImage();
	        });
		function processImage () {
			attachment_id = attachments[i];
		        var filename_data = {
		                action: 'bulk_filename',
				_wpnonce: ewww_vars._wpnonce,
				attachment: attachment_id,
		        };
			$.post(ajaxurl, filename_data, function(response) {
			        $('#bulk-loading').html(response);
			});
		        var loop_data = {
		                action: 'bulk_loop',
				_wpnonce: ewww_vars._wpnonce,
				attachment: attachment_id,
		        };
		        var jqxhr = $.post(ajaxurl, loop_data, function(response) {
				i++;
				$('#bulk-progressbar').progressbar("option", "value", i );
				$('#bulk-counter').html('Optimized ' + i + '/' + attachments.length);
		                $('#bulk-status').append(response);
		    //            $('#bulk-status').append(attachment_id + '<br>');
				if (i < attachments.length) {
					processImage();
				}
				else {
				        var cleanup_data = {
				                action: 'bulk_cleanup',
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
