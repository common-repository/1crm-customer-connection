void function ($) {
	"use strict";
    $('.onecrm form input.datetime').datetimepicker({
        dateFormat: 'yy-mm-dd',
        timeFormat: 'HH:mm:ss z',
        addSliderAccess: true,
        sliderAccessArgs: {touchonly: false}
    });
    $('.onecrm form input.date').datepicker({
        dateFormat: 'yy-mm-dd'
	});

	var reportErrors = function(errors) {
		var errors_container = $('#onecrm-p-errors');
		errors_container.addClass('active');
		if (errors && typeof errors.forEach === 'function') {
			if (!errors.length) return;
			errors_container.empty();
			errors.forEach(function(err) {
 				var err_elm = $('<div>').addClass('onecrm-p-error').text(err);
				errors_container.append(err_elm);
			});
		}
	};

	var replyReceived = function(reply) {
		if (reply.result && reply.result.redirect) {
			window.location.href = reply.result.redirect;
		}
	};

	$("#onecrm-p-editor-form").on('submit',function() {
		var j=$(this), values=j.serializeArray();
		$('.onecrm-p-save.button').attr('disabled', true);
        $('#onecrm-p-errors').empty();
        $('#onecrm-p-errors').removeClass('active');
        $.ajax(j[0].action, {
			method:'POST',
			data:values, 
			success:function(body) {
                $('.onecrm-p-save.button').removeAttr('disabled');
				$('#onecrm-p-errors').removeClass('active');
				var errors;
				try {
					body = JSON.parse(body);
					errors = body['errors'];
				} catch(e) {
					errors = ['Unknown error'];
				}
				if(errors) {
					reportErrors(errors);
				} else {
					replyReceived(body);
				}
			}, 
			error: function(x) {
                $('.onecrm-p-save.button').removeAttr('disabled');
				var errors;
				$('#onecrm-p-errors').removeClass('active');
				try {
					var body = JSON.parse(x.responseText);
					errors = body['errors'];
				} catch(e) {
					errors = ['Unknown error'];
				}
				reportErrors(errors);
			}
		});
		return false;
	});

}(jQuery);
