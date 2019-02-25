(function ($) {
    'use strict';

    // Render function for name/value replacement in string templates
    String.prototype.render = function (parameters) {
        return this.replace(/({{ (\w+) }})/g, function (match, pattern, name) {
            return undefined !== parameters[name] ? parameters[name] : '';
        })
    };

    // ENTITY2 PUBLIC CLASS DEFINITION
    // ===============================

    let Entity2 = function (element, options) {
        this.$element = $(element);
        this.options = $.extend({}, Entity2.DEFAULTS, this.$element.data(), options);

        this.initSelect2(this.options.select2Options);
    };

    Entity2.DEFAULTS = {};

    Entity2.prototype.initSelect2 = function (options) {
        options.ajax.data = function (params) {
            return { 'query': params.term, 'page': params.page };
        };

        // to indicate that infinite scrolling can be used
        options.ajax.processResults = function (data, params) {
            return {
                results: data.results,
                pagination: {
                    more: data.has_next_page
                }
            };
        };

        options.placeholder = {
            'id': '',
            'text': options.placeholder,
        };

        if (options.escapeMarkup) {
            options.escapeMarkup = function (markup) { return markup; };

            if (options.templateResult) {
                const templateResult = options.templateResult;
                options.templateResult = function (object) {
                    return object.id ? templateResult.render(object) : object.text;
                };
            } else {
                delete options.templateResult;
            }

            if (options.templateSelection) {
                const templateSelection = options.templateSelection;
                options.templateSelection = function (object) {
                    return object.text ? templateSelection.render(object) : '';
                };
            } else {
                delete options.templateResult;
            }
        } else {
            delete options.escapeMarkup;
            delete options.templateResult;
            delete options.templateSelection;
        }

        this.$element.removeAttr('data-select2-options');

        this.$element.select2(options);
    };

    // ENTITY2 PLUGIN DEFINITION
    // =========================

    function Plugin(option) {
        return this.each(function () {
            let $this = $(this);
            let instance = $this.data('entity2');
            let options = typeof option === 'object' && option;

            if (!instance) $this.data('entity2', (new Entity2(this, options)));
        })
    }

    $.fn.entity2 = Plugin;
    $.fn.entity2.Constructor = Entity2;

})(window.jQuery);
