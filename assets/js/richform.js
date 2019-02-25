String.prototype.render = function (parameters) {
    return this.replace(/({{ (\w+) }})/g, function (match, pattern, name) {
        return parameters[name];
    })
};
