// Helpers

// Render function for key/value replacement in string templates
String.prototype.render = function (parameters) {
    return this.replace(/({{ (\w+) }})/g, function (match, pattern, name) {
        return undefined !== parameters[name] ? parameters[name] : '';
    })
};

// Initialize module
// ------------------------------

// When page is fully loaded
window.addEventListener('load', function() {
    //data-select2-options
    $('select[data-select2-widget=true]').each(function () {
        const options = $(this).data('select2-options') || {};

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

        $(this).select2(options);
    });
});
