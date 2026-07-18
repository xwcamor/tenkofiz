/**
 * Turns every <select class="employee-select"> into a searchable autocomplete
 * (Select2 + AJAX). Options are fetched from the server 20 at a time as the
 * user types, so the selectors respond instantly with 50 or 50,000 employees.
 *
 * Supported data- attributes on the <select>:
 *   data-url            search endpoint (required)
 *   data-placeholder    placeholder text; also enables the clear (×) button
 *   data-width          CSS width (default 100%)
 *   data-selected-id    preselected employee id (e.g. active filter, old input)
 *   data-selected-text  label for the preselected employee
 */
(function () {
    if (!window.jQuery || !jQuery.fn.select2) return;

    jQuery(function ($) {
        $('select.employee-select').each(function () {
            const $el = $(this);
            const $modal = $el.closest('.modal');

            if ($el.data('selected-id')) {
                $el.append(new Option($el.data('selected-text') || '#' + $el.data('selected-id'), $el.data('selected-id'), true, true));
            }

            $el.select2({
                theme: 'bootstrap4',
                width: $el.data('width') || '100%',
                language: document.documentElement.lang === 'es' ? 'es' : 'en',
                placeholder: $el.data('placeholder') || '',
                allowClear: !!$el.data('placeholder'),
                dropdownParent: $modal.length ? $modal : $(document.body),
                ajax: {
                    url: $el.data('url'),
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term || '', page: params.page || 1 }),
                    processResults: data => data,
                },
            });
        });
    });
})();
