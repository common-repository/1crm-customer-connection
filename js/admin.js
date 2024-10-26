jQuery(document).ready(function($) {
	"use strict";

	var toggleModuleVisibility = function() {
		var $this = $(this);
		var $tabset = $this.closest('.postbox').find('.onecrm-p-tabset');
		this.checked ? $tabset.show() : $tabset.hide();
		$this.closest('.postbox').find('h2').css('text-decoration', this.checked ? 'none' : 'line-through');

	}

	var toggleFieldVisibility = function() {
		var $this = $(this);
		$this.parent().css('text-decoration', this.checked ? 'none' : 'line-through');

	}

	$('.onecrm-p-tab').on('click', function(e) {
		var $this = $(this);
		var tabId = $this.data('tab');

		var $tabset = $this.closest('.onecrm-p-tabset');
		$tabset.find('.onecrm-p-tab').removeClass('active');
		$this.addClass('active');
		var $containers = $tabset.find('.onecrm-p-tab-content');
		$containers.each(function() {
			var $this = $(this);
			if ($this.data('tab') == tabId)
				$this.addClass('active');
			else
				$this.removeClass('active');
		});
		e.preventDefault();
	});

	$('.onecrm-p-toggle-module').each(toggleModuleVisibility);
	$('.onecrm-p-toggle-module').on('click', toggleModuleVisibility);
	$('.onecrm-p-toggle-field').each(toggleFieldVisibility);
	$('.onecrm-p-toggle-field').on('click', toggleFieldVisibility);

	$( ".onecrm-p-fields" ).sortable({
		opacity: 0.6,
		axis: 'y',
		cursor: 'move',
		helper: 'original',
		placeholder: 'onecrm-p-drag-placeholder',
		forceHelperSize: true,
		forcePlaceholderSize: true,
		handle: '.onecrm-p-handle'
	});
    $(".onecrm-p-fields" ).disableSelection();
	$( ".onecrm-p-fields" ).each(function() {
		$(this).on('sortupdate', function() {
		});
	});

	$('.postbox').removeClass('closed');
	$('.handlediv').hide();
	
	$( ".meta-box-sortables" ).sortable({
		axis: 'y',
		cursor: 'move',
		handle: '.hndle',
		placeholder: 'onecrm-p-drag-placeholder-metabox',
		forcePlaceholderSize: true
	});
	$( ".meta-box-sortables" ).disableSelection();

	$('#submit-dashboard').on('click', function(e) {
		var config = [];
		$( ".postbox" ).each(function() {
			if (this.id == 'onecrm_p_dashboard_subscriptions') return;
			var parts = this.id.split('_');
			var module = parts[parts.length - 1];

			var moduleConfig;
			if(module == 'KBArticles') {
				moduleConfig = {
					module: module,
					onecrm_help_css_color: $('#onecrm_help_css_color').val(),
					onecrm_help_css_font: $('#onecrm_help_css_font').val()
				};
			}
			else {
				moduleConfig = {
					module: module,
					enabled: $('#dashboard-module-' + module)[0].checked,
					list: [],
					detail: [],
					create: [],
					items: []
				};
			}
			config.push(moduleConfig);
			$(this).find('.onecrm-p-tab-content').each(function() {
				var parts = $(this).data('tab').split('-');
				var type = parts[1];
				$(this).find('.onecrm-p-toggle-field').each(function() {
					var parts = this.id.split('-');
					var field = parts[parts.length - 1];
					moduleConfig[type].push({
						enabled: !!this.checked,
						field: field
					});
				});
			});
		});
		$('#onecrm-p-dashboard-config').val(JSON.stringify(config));
		e.preventDefault();
		this.form.submit();
	});

    $("#onecrm-p-resync-booking").on('click',function() {
        $('#onecrm-p-booking-submit').attr('disabled', true);
        $('#booking-msg').html('').removeClass().hide();
        $.ajax('admin-ajax.php?action=onecrm_p_run_booking_sync', {
            method:'POST',
            data:{},
            beforeSend:function() {
                $('#onecrm-p-resync-booking').attr('disabled', true);
                $('#booking-msg').html('<p>'+onecrm_p.booking_sync_running+'<span class="spinner is-active" style="float: inherit;"></span></p>').removeClass().addClass('updated notice').show();
            },
            success:function(body) {
                body = JSON.parse(body);
                $('#booking-msg').html('<p>'+body['result']+'</p>').removeClass().addClass('updated notice').show();
                $('#onecrm-p-resync-booking').attr('disabled', false);
                $('#onecrm-p-booking-submit').attr('disabled', false);
            },
            error: function(x) {
                var errors;
                try {
                    var body = JSON.parse(x.responseText);
                    errors = body['errors'];
                } catch(e) {
                    errors = ['Unknown error'];
                }
                $('#booking-msg').html('<p>'+errors[0]+'</p>').removeClass().addClass('error notice').show();
                $('#onecrm-p-resync-booking').attr('disabled', false);
                $('#onecrm-p-booking-submit').attr('disabled', false);
            }
        });
        return false;
    });

    function toggleThemeColors() {
		if(!$('#onecrm_help_css_borders').is(':checked')) {
			$('#onecrm-p-border-options').hide();
		} else {
			$('#onecrm-p-border-options').show();
		}
		if(!$('#onecrm_help_theme_col').is(':checked')) {
			$('#onecrm-p-theme-options').hide();
			$('#onecrm-p-border-options').hide();
		} else {
			$('#onecrm-p-theme-options').show();
		}
	}
	$('#booking-msg').hide();

	$(document).on('change', '#onecrm_help_theme_col', toggleThemeColors);
	$(document).on('change', '#onecrm_help_css_borders', toggleThemeColors);
	toggleThemeColors();
});
