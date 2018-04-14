//
// Protected media file admin 
//
jQuery(document).ready(function($){
	// show/hide the input fields required for the selected action
	$(function () { $('#login_action').trigger('change'); });
	$("#login_action").on('change', function () {
           var act = $(this).val();
		   $("#custom_url").closest("tr").hide();
		   $("[id^=wp_login_form]").closest("tr").hide();
		   if(act == "custom") $("#custom_url").closest("tr").show();
		   else if(act == "custom_shortcode") {
			   $("#custom_url").closest("tr").show();
			   $("[id^=wp_login_form]").closest("tr").show();
		   }
	});

	// extract selected page to custom_url input 
	$("#all_pages").on('change', function () {
		$("#custom_url").val($(this).val());
	});
});
