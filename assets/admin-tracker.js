(function($){
	'use strict';

	function cleanText(value, max) {
		value = String(value || '').replace(/\s+/g, ' ').trim();
		return value.substring(0, max || 160);
	}

	$(document).on('click.swh', 'a,button,input[type="submit"],input[type="button"]', function(){
		var $el = $(this);
		var name = cleanText($el.attr('name') || '', 80).toLowerCase();

		if (name.indexOf('pass') !== -1 || name.indexOf('password') !== -1) {
			return;
		}

		var href = cleanText($el.attr('href') || '', 300);

		if (href) {
			try {
				var parsed = new URL(href, window.location.origin);
				href = parsed.pathname;
			} catch (e) {
				href = '';
			}
		}

		$.post(SWHTracker.ajaxUrl, {
			action: 'swh_log_admin_click',
			nonce: SWHTracker.nonce,
			tag: String(this.tagName || '').toLowerCase(),
			element_id: cleanText($el.attr('id') || '', 80),
			text: cleanText($el.text() || $el.val() || '', 160),
			name: name,
			href: href,
			screen: cleanText(window.location.pathname, 200)
		});
	});
})(jQuery);
