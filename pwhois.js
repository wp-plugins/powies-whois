function WhoisCheck(){
	if ( jQuery("#domain").val().length < 2 ) {
		alert(pWhoisAjax.enter_domain);
		return false;
	}
	return true;
}

jQuery(document).ready(function(){
    jQuery("#whoissubmit").click(function(e){
        e.preventDefault();
        if ( WhoisCheck() ) {
        	whoispost();
        }
    });
});

function whoispost() {
	jQuery('#whoissubmit').attr("disabled", true);
	jQuery("#pwhois_result").html('');
	jQuery("#pwhois_work").slideToggle(500);
	jQuery.post(pWhoisAjax.ajaxurl, jQuery("#whois").serialize(), function(data) {
		if (data.success) {
			jQuery("#pwhois_result").html(data.msg);
			jQuery("#pwhois_work").slideToggle(500);
			jQuery('#whoissubmit').attr("disabled", false);
		}
	});
}
