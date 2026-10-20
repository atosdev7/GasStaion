/*
 * Dates with AdminLTE's date picker (Tempus Dominus for Bootstrap 4, with moment.js), in the language of the page (en, zh-CN, zh-TW).
 * The date boxes of the browser (input type="date" and "datetime-local") show the language of the browser, not the one chosen in the app, so
 * every such box is replaced here by the "date" component of AdminLTE's Advanced Form page: a text box with a calendar button that opens the
 * picker. The original input stays in the form as a hidden field (name, value, form, onchange and so on are kept) and carries the value to
 * the server as before: yyyy-mm-dd, or yyyy-mm-ddThh:mm for a date with time. The `min` and `max` of the original limit the picker.
 * A new date box is written as type="date" (or "datetime-local") and is upgraded by this script.
 */
(function (root) {
    'use strict';

    var $ = root.jQuery;
    var moment = root.moment;

    if (!$ || !moment || !$.fn.datetimepicker) {
        return; // without the plugin the boxes of the browser stay as they are
    }

    var lang = (root.document.documentElement.lang || 'en').toLowerCase();
    if (lang.indexOf('zh') === 0 && moment.locale(lang) !== lang) {
        moment.locale('zh-cn'); // zh-hk, zh-mo and the like use the simplified texts when only those are loaded
    } else if (lang.indexOf('zh') !== 0) {
        moment.locale('en');
    }

    // The texts of the buttons of the picker (the names of months and days come from moment).
    var TOOLTIPS = {
        'zh-cn': {
            today: '今天', clear: '清除', close: '关闭', selectMonth: '选择月份', prevMonth: '上个月', nextMonth: '下个月', selectYear: '选择年份',
            prevYear: '上一年', nextYear: '下一年', selectDecade: '选择年代', prevDecade: '上一个年代', nextDecade: '下一个年代', prevCentury: '上一世纪',
            nextCentury: '下一世纪', pickHour: '选择小时', incrementHour: '增加小时', decrementHour: '减少小时', pickMinute: '选择分钟',
            incrementMinute: '增加分钟', decrementMinute: '减少分钟', pickSecond: '选择秒', incrementSecond: '增加秒', decrementSecond: '减少秒',
            togglePeriod: '切换上午/下午', selectTime: '选择时间', selectDate: '选择日期'
        },
        'zh-tw': {
            today: '今天', clear: '清除', close: '關閉', selectMonth: '選擇月份', prevMonth: '上個月', nextMonth: '下個月', selectYear: '選擇年份',
            prevYear: '上一年', nextYear: '下一年', selectDecade: '選擇年代', prevDecade: '上一個年代', nextDecade: '下一個年代', prevCentury: '上一世紀',
            nextCentury: '下一世紀', pickHour: '選擇小時', incrementHour: '增加小時', decrementHour: '減少小時', pickMinute: '選擇分鐘',
            incrementMinute: '增加分鐘', decrementMinute: '減少分鐘', pickSecond: '選擇秒', incrementSecond: '增加秒', decrementSecond: '減少秒',
            togglePeriod: '切換上午/下午', selectTime: '選擇時間', selectDate: '選擇日期'
        }
    };

    var ICONS = {
        time: 'far fa-clock', date: 'far fa-calendar', up: 'fas fa-arrow-up', down: 'fas fa-arrow-down', previous: 'fas fa-chevron-left',
        next: 'fas fa-chevron-right', today: 'far fa-calendar-check', clear: 'far fa-trash-alt', close: 'far fa-times-circle'
    };

    var counter = 0;

    function fire(node, name) {
        var event;

        if (typeof root.Event === 'function') {
            event = new root.Event(name, {bubbles: true});
        } else {
            event = root.document.createEvent('Event');
            event.initEvent(name, true, true);
        }
        node.dispatchEvent(event);
    }

    function upgrade(original) {
        var timed = original.type === 'datetime-local';
        var shown = timed ? 'YYYY-MM-DD HH:mm' : 'YYYY-MM-DD';
        var sent = timed ? 'YYYY-MM-DD[T]HH:mm' : 'YYYY-MM-DD';
        var key = 'dp' + (++counter);
        var group, visible, hidden, options, start, min, max;

        if (original.getAttribute('data-datepicker') === 'done') {
            return;
        }
        original.setAttribute('data-datepicker', 'done');

        // the box of AdminLTE's "Date" example: an input group with the calendar button at its end
        group = $('<div class="input-group date" data-target-input="nearest"></div>').attr('id', key);
        if (/\bform-control-sm\b/.test(original.className)) {
            group.addClass('input-group-sm');
        }
        visible = $('<input type="text" autocomplete="off">').addClass(original.className + ' datetimepicker-input')
            .attr({'data-target': '#' + key, placeholder: shown.toLowerCase(), maxlength: timed ? 16 : 10});
        group[0].style.cssText = original.style.cssText; // a width or a minimum width belongs to the whole box, the button included
        if (original.id) {
            visible.attr('id', original.id);
            original.removeAttribute('id');
        }
        if (original.title) {
            visible.attr('title', original.title);
        }
        if (original.disabled) {
            visible.prop('disabled', true);
        }
        group.append(visible).append(
            '<div class="input-group-append" data-target="#' + key + '" data-toggle="datetimepicker"><div class="input-group-text"><i class="far fa-calendar-alt"></i></div></div>'
        );

        // the original stays in the form and carries the value to the server
        hidden = original;
        hidden.type = 'hidden';
        hidden.parentNode.insertBefore(group[0], hidden);

        start = moment(String(hidden.value).replace('T', ' '), shown, true);
        min = hidden.getAttribute('min') ? moment(hidden.getAttribute('min'), 'YYYY-MM-DD', true) : null;
        max = hidden.getAttribute('max') ? moment(hidden.getAttribute('max'), 'YYYY-MM-DD', true) : null;
        options = {
            format: shown, locale: moment.locale(), useCurrent: false, allowInputToggle: true, sideBySide: timed, icons: ICONS,
            buttons: {showToday: true, showClear: true, showClose: false},
            tooltips: TOOLTIPS[moment.locale()]
        };
        if (!options.tooltips) {
            delete options.tooltips;
        }
        if (start.isValid()) {
            options.defaultDate = start;
        }
        if (min && min.isValid()) {
            options.minDate = min.startOf('day');
        }
        if (max && max.isValid()) {
            options.maxDate = max.endOf('day');
        }
        group.datetimepicker(options);
        // the picker opens inside its box, so a table that scrolls sideways would cut it: on a wide screen such a table shows what is outside it
        group.closest('.table-responsive').addClass('dp-visible');

        group.on('change.datetimepicker', function (event) {
            var value = event.date ? event.date.format(sent) : '';

            if (hidden.value !== value) {
                hidden.value = value;
                fire(hidden, 'change');
            }
        });
    }

    $(function () {
        $('input[type="date"], input[type="datetime-local"]').each(function () {
            upgrade(this);
        });
    });
}(typeof window !== 'undefined' ? window : this));
