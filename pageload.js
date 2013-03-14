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
// TODO: implement error handling if something gets interrupted
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
			processImage();
	        });
		function processImage () {
			attachment_id = ewww_vars.attachments[i];
		        var loop_data = {
		                action: 'bulk_loop',
				_wpnonce: ewww_vars._wpnonce,
				attachment: attachment_id,
		        };
			$('#bulk-progressbar').progressbar("option", "value", i );
			$('#bulk-counter').html('Optimized ' + i + '/' + ewww_vars.attachments.length);
		        $.post(ajaxurl, loop_data, function(response) {
				i++;
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
				
		        });
		}
		return false;
	});
/*function processImage (response) {
	$('#bulk-status').html(response);
	var attachment_id = ewww_vars.attachments.unshift();
        var loop_data = {
                action: 'bulk_loop',
		_wpnonce: ewww_vars._wpnonce,
		attachment: attachment_id
        };
	$('#bulk-id').html(attachment_id);
        $.post(ajaxurl, loop_data, processImage(response) );
}*/
});
