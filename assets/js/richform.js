(function ($) {
    'use strict';

    // ENTITY2 PUBLIC CLASS DEFINITION
    // ===============================

    let Entity2 = function (element, options) {
        this.$element = $(element);
        this.options = $.extend({}, Entity2.DEFAULTS, this.$element.data(), options);

        this.initSelect2(this.options.select2Options);
    };

    Entity2.DEFAULTS = {};

    Entity2.prototype.initSelect2 = function (options) {
        const self = this;

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
                    if (!object.id) {
                        return object.text;
                    }

                    const parameters = object.data || {};
                    parameters['id'] = object.id;
                    parameters['text'] = object.text;

                    return self.render(templateResult, parameters);
                };
            } else {
                delete options.templateResult;
            }

            if (options.templateSelection) {
                const templateSelection = options.templateSelection;
                options.templateSelection = function (object) {
                    if (!object.text) {
                        return '';
                    }

                    const parameters = object.data || {};
                    parameters['id'] = object.id;
                    parameters['text'] = object.text;

                    return self.render(templateSelection, parameters);
                };
            } else {
                delete options.templateSelection;
            }
        } else {
            delete options.escapeMarkup;
            delete options.templateResult;
            delete options.templateSelection;
        }

        // https://select2.org/troubleshooting/common-problems
        if (this.$element.closest('.modal').length > 0) {
            options.dropdownParent = this.$element.closest('.modal');
        }

        this.$element.removeAttr('data-select2-options');

        this.$element.select2(options);
    };

    Entity2.prototype.render = function (template, parameters) {
        return template.replace(/({{ ([\w.]+) }})/g, function (match, pattern, name) {
            return undefined !== parameters[name] && null !== parameters[name] ? parameters[name] : '';
        })
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
