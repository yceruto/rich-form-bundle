(function ($) {
    'use strict';

    // ENTITY2 PUBLIC CLASS DEFINITION
    // ===============================

    let Entity2 = function (element, options) {
        this.$element = $(element);
        this.options = $.extend({}, Entity2.DEFAULTS, this.$element.data(), options);

        this.init(this.options.entity2Options, this.options.select2Options);
    };

    Entity2.DEFAULTS = {};

    Entity2.prototype.init = function (entity2Options, select2Options) {
        const self = this;

        select2Options.ajax.data = function (params) {
            let dynamicParamValues = {}, paramName, $formField;

            if (entity2Options.dynamicParams) {
                for (const id in entity2Options.dynamicParams) {
                    $formField = $(id);
                    if (0 === $formField.length || ('INPUT' !== $formField[0].tagName && 'SELECT' !== $formField[0].tagName)) {
                        continue;
                    }

                    paramName = entity2Options.dynamicParams[id];
                    dynamicParamValues[paramName] = $formField.val();
                }
            }

            return { 'term': params.term, 'page': params.page, 'dyn': dynamicParamValues };
        };

        // to indicate that infinite scrolling can be used
        select2Options.ajax.processResults = function (data, params) {
            return {
                results: data.results,
                pagination: {
                    more: data.has_next_page
                }
            };
        };

        if (null === select2Options.placeholder) {
            delete select2Options.placeholder;
        } else if (typeof select2Options.placeholder === 'string') {
            select2Options.placeholder = {
                'id': '',
                'text': select2Options.placeholder,
            };
        }

        if (select2Options.escapeMarkup) {
            select2Options.escapeMarkup = function (markup) { return markup; };

            if (select2Options.templateResult) {
                const templateResult = select2Options.templateResult;
                select2Options.templateResult = function (object) {
                    if (!object.id) {
                        return object.text;
                    }

                    const parameters = object.data || {};
                    parameters['id'] = object.id;
                    parameters['text'] = object.text;

                    return self.render(templateResult, parameters);
                };
            } else {
                delete select2Options.templateResult;
            }

            if (select2Options.templateSelection) {
                const templateSelection = select2Options.templateSelection;
                select2Options.templateSelection = function (object) {
                    if (!object.text) {
                        return '';
                    }

                    const parameters = object.data || {};
                    parameters['id'] = object.id;
                    parameters['text'] = object.text;

                    return self.render(templateSelection, parameters);
                };
            } else {
                delete select2Options.templateSelection;
            }
        } else {
            delete select2Options.escapeMarkup;
            delete select2Options.templateResult;
            delete select2Options.templateSelection;
        }

        // https://select2.org/troubleshooting/common-problems
        if (this.$element.closest('.modal').length > 0) {
            select2Options.dropdownParent = this.$element.closest('.modal');
        }

        this.$element.removeAttr('data-entity2-options');
        this.$element.removeAttr('data-select2-options');

        this.$element.select2(select2Options);
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
