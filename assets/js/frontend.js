(function ($) {
	'use strict';

	$(document).on('change', '.wclv-select', function () {
		var url = $(this).val();
		if (url) {
			window.location.href = url;
		}
	});

})(jQuery);
