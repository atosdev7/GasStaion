/*
 * Select2 (AdminLTE's plugin) on the long selects of the app: a select written with the class js-select2 becomes a box where one types to
 * find the entry (a select with only a few entries stays as it is). The select stays in the form (name, value, form, onchange kept).
 * Options on the select: data-width ("100%" when not given), data-allow-clear (an empty first option can be chosen again),
 * data-no-placeholder (the empty first option is an entry like the others, "general payment").
 * Select2 tells the page about a change with jQuery; a script that listens to the select uses jQuery's .on('change') too.
 */
(function ($) {
    'use strict';

    if (!$ || !$.fn.select2) {
        return; // without the plugin the selects of the browser stay as they are
    }

    $(function () {
        $('select.js-select2').each(function () {
            var select = $(this);
            var empty = select.find('option[value=""]').first();
            var options = {width: select.data('width') || '100%', language: document.documentElement.lang || 'en'};

            if (empty.length && select.data('no-placeholder') === undefined) {
                options.placeholder = empty.text() || ' ';
                options.allowClear = select.data('allow-clear') !== undefined;
            }
            select.select2(options);
        });
    });
}(window.jQuery));
