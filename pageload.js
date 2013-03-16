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
	        $.post(ajaxurl, init_data, function(response) {
	                $('#bulk-loading').html(response);
			$('#bulk-progressbar').progressbar({ max: ewww_vars.attachments.length });
			$('#bulk-counter').html('Optimized 0/' + ewww_vars.attachments.length);
			processImage();
	        });
		function processImage () {
			attachment_id = ewww_vars.attachments[i];
		        var loop_data = {
		                action: 'bulk_loop',
				_wpnonce: ewww_vars._wpnonce,
				attachment: attachment_id,
		        };
		        var jqxhr = $.post(ajaxurl, loop_data, function(response) {
				i++;
				$('#bulk-progressbar').progressbar("option", "value", i );
				$('#bulk-counter').html('Optimized ' + i + '/' + ewww_vars.attachments.length);
		                $('#bulk-status').append(response);
				if (i < ewww_vars.attachments.length) {
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
