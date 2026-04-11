(function ($) {
	'use strict';

	/* ── Source toggle ──────────────────────────────────────────── */
	$('input[name="wclv_product_source"]').on('change', function () {
		var source = $(this).val();
		$('.wclv-source-panel').hide();
		$('.wclv-source-panel[data-source="' + source + '"]').show();
	});

	/* ── Product search (Select2 + AJAX) ────────────────────────── */
	$('#wclv_product_ids').select2({
		ajax: {
			url: wclv_admin.ajax_url,
			dataType: 'json',
			delay: 300,
			data: function (params) {
				return {
					action: 'wclv_search_products',
					term: params.term,
					security: wclv_admin.search_nonce
				};
			},
			processResults: function (data) {
				return data;
			},
			cache: true
		},
		minimumInputLength: 2,
		placeholder: wclv_admin.search_placeholder,
		allowClear: true
	});

	/* ── Taxonomy change → load terms ───────────────────────────── */
	var $termsSelect = $('#wclv_taxonomy_terms');

	$('#wclv_taxonomy').on('change', function () {
		var taxonomy = $(this).val();

		$termsSelect.val(null).trigger('change');

		if ($termsSelect.data('select2')) {
			$termsSelect.select2('destroy');
		}

		if (!taxonomy) {
			return;
		}

		initTermsSelect(taxonomy);
	});

	function initTermsSelect(taxonomy) {
		$termsSelect.select2({
			ajax: {
				url: wclv_admin.ajax_url,
				dataType: 'json',
				delay: 200,
				data: function (params) {
					return {
						action: 'wclv_get_taxonomy_terms',
						taxonomy: taxonomy,
						term: params.term || '',
						security: wclv_admin.taxonomy_nonce
					};
				},
				processResults: function (data) {
					return data;
				},
				cache: true
			},
			minimumInputLength: 0,
			placeholder: wclv_admin.terms_placeholder,
			allowClear: true
		});
	}

	var currentTaxonomy = $('#wclv_taxonomy').val();
	if (currentTaxonomy) {
		initTermsSelect(currentTaxonomy);
	}

	/* ── Drag-and-drop attribute reordering ─────────────────────── */
	$('#wclv-attribute-list').sortable({
		handle: '.wclv-drag-handle',
		placeholder: 'wclv-attribute-item ui-sortable-placeholder',
		axis: 'y',
		cursor: 'move'
	});

})(jQuery);
