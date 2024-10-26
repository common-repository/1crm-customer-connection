void function($) {
	"use strict";
	function modifyLinks() {
		$('.onecrm-p-article-content a').each(function(_, a) {
			if (!$(a).hasClass('onecrm-p-internal')) {
				a.target = '_blank';
			}
		});
	}
	function resizeBoxes() {
		$('div.kbbox h2').each(function(idx, el) {
			$(el).css({height: "auto"});
		});
		var byPos = {};
		$('div.kbwrapper div.kbbox h2').each(function(idx, el) {
			var top = $(el).offset().top;
			if (!byPos[top]) {
				byPos[top] = [];
			}
			byPos[top].push(el);
		});
		Object.keys(byPos).forEach(function(top) {
			var elts = byPos[top];
			var tallest = 0;
			elts.forEach(function(el) {
				var $el = $(el);
				if ($el.height() > tallest) {
					tallest = $el.height();
				}
			});
			elts.forEach(function(el) {
				$(el).css({height: tallest + 'px'});
			});
		});
	}
	function changeView(view) {
		var save = true;
		if (!view) {
			view = getCookie('kbgridlist');
			save = false;
		}
		if (view !== 'list' && view !== 'grid')
			view = 'grid';
		$('.onecrm-p-view-icons')
			.removeClass('view-list')
			.removeClass('view-grid')
			.addClass('view-' + view);
		if (save) setCookie('kbgridlist', view, 10);


		if(view == 'list') {
			$('#wrapperdiv').removeClass('kbwrapper').addClass('listkb');
			$('#articlewrapperdiv').removeClass('kbwrapper').addClass('listkb');
			$('#searchwrapperdiv').removeClass('kbwrapper').addClass('listkb');
		} else {
			$('#wrapperdiv').addClass('kbwrapper').removeClass('listkb');
			$('#articlewrapperdiv').addClass('kbwrapper').removeClass('listkb');
			$('#searchwrapperdiv').addClass('kbwrapper').removeClass('listkb');
		}
		resizeBoxes();
	}

	function getArticles() {
		var id = jQuery(this).attr('data-id');
		window.location.href = onecrm_p_detail_url + "?model=KBCategory&id="+id;
	}

	function getArticleSingle() {
		var id = jQuery(this).attr('data-id');
		$('#kbgridlist').hide();
		window.location.href = onecrm_p_detail_url + "?model=KBArticle&id="+id;
	}

	function selectCatagoriesSearch() {
		var dropdown = document.getElementById('searchbycategory');
		if(dropdown.style.display == 'none')
			dropdown.style.display = 'block';
		else
			dropdown.style.display = 'none';
	}

	function setCookie(cname, cvalue, exdays) {
		var d = new Date();
		d.setTime(d.getTime() + (exdays*24*60*60*1000));
		var expires = "expires="+ d.toUTCString();
		document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
	}
			
	function getCookie(cname) {
		var name = cname + "=";
		var decodedCookie = decodeURIComponent(document.cookie);
		var ca = decodedCookie.split(';');
		for(var i = 0; i <ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) == ' ') {
				c = c.substring(1);
			}
			if (c.indexOf(name) == 0) {
				return c.substring(name.length, c.length);
			}
		}
		return "";
	}
	
	if(document.getElementById('wrapperdiv') || document.getElementById('articlewrapperdiv')) {
		$('.onecrm-p-view-icons').show();
		changeView();
	}
	else {
		$('.onecrm-p-view-icons').hide();
	}
	$(document)
		.on('click', 'div.onecrm-p-category', getArticles)
		.on('click', 'div.onecrm-p-article', getArticleSingle)
		.on('click', 'i#onecrm-p-search-toggle', selectCatagoriesSearch)
		.on('click','.onecrm-p-view-grid', function() {changeView('grid')})
		.on('click','.onecrm-p-view-list', function() {changeView('list')})
		.on('focus','.onecrm-p-search-inline', function() {$('.onecrm-p-search-wrapper').addClass('focused')})
		.on('blur','.onecrm-p-search-inline', function() {$('.onecrm-p-search-wrapper').removeClass('focused')})
	;

	var highlight = function(term, text) {
		var reStr = term.replace(/[-[\]{}()*+!<=:?.\/\\^$|#\s,]/g, '\\$&')
		var re = new RegExp(reStr, 'ig')
		return text.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(re, '<b>$&</b>');
	};

	if ($.ui && $.ui.autocomplete) {
		$.widget( "custom.oneccmSeaarchResults", $.ui.autocomplete, {
			_renderItem: function( ul, item ) {
				var summary = '';
				if (item.summary) {
					summary = $('<div>').append(highlight(this.term, item.summary))
					summary.addClass('onecrm-p-summary');
				}
				var category = '';
				if (item.category) {
					category = $('<span>').append(highlight(this.term, item.category))
					category.addClass('onecrm-p-category-badge')
				}
				return $( "<li>" )
					.attr( "data-value", item.value )
					.append( highlight(this.term, item.label) )
					.append( category )
					.append(summary)
					.appendTo( ul )
			},
			_renderMenu: function( ul, items ) {
				var that = this;
				$.each( items, function( index, item ) {
					that._renderItemData( ul, item );
				});
				var iw = $(this.element[0]).width();
				var mw = this.menu.element.outerWidth();
				if (mw > iw) {
					if (mw > 300)
						mw = 300;
				} else {
					mw = iw
				}
				this.menu.element.outerWidth(mw);
			},
			_resizeMenu: function() {
			  var iw = $(this.element[0]).width();
				var mw = this.menu.element.outerWidth();
				if (mw > iw) {
					if (mw > 300)
						mw = 300;
				} else {
					mw = iw
				}
 				this.menu.element.outerWidth(mw);

				var input = this.element[0];
				var ib = input.getBoundingClientRect();
				var mb = this.menu.element[0].getBoundingClientRect();
				var wh = $(window).height();

				var sTop = ib.top - 10;
				var sBottom = wh - ib.bottom - 10;
				var mh = mb.height;
				if (mh > sBottom && sBottom >= 300) {
					mh = sBottom;
				}
				if (mh > sBottom && mh > sTop) {
					mh = Math.max(sTop, sBottom);
				}
				if (mh <= sBottom) {
					this.menu.element.outerHeight(mh);
					this.option('position', { my : "right top", at: "right bottom" });
				} else {
					this.menu.element.outerHeight(mh);
					this.option('position', { my : "right bottom", at: "right top" });
				}
			}
			
		});

	}

	$('.onecrmhelp #search-help').oneccmSeaarchResults({
		minLength: 3,
		source: function(req, resp) {
			jQuery.ajax({
				url: onecrm_p_ajaxurl,
				type: 'post',
				data: { action: 'onecrm_kb_search', id: 1, search: req.term },
				success: function(data) {
					resp(
						data.map(function(el) {
							return {
								label: el.name,
								value: el.id,
								summary: el.summary,
								category: el.category
							}
						})
					);
				}
			});
		},
		select: function( event, ui ) {
			event.preventDefault();
			window.location.href = onecrm_p_detail_url + '?model=KBArticle&id=' + ui.item.value;
		},
		focus: function( event, ui ) { event.preventDefault(); },
		classes: {
			"ui-autocomplete": "highlight"
		},
		position: { my : "right top", at: "right bottom" }
	});

	$(document).on('ready', resizeBoxes);
	$(document).on('ready', modifyLinks);
	$(window).on('resize', resizeBoxes);

}(jQuery);

