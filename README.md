RichForm
========

RichForm provides autocomplete form types with optimal performance for your Symfony applications.

[![Build Status](https://travis-ci.org/yceruto/rich-form-bundle.svg?branch=master)](https://travis-ci.org/yceruto/rich-form-bundle)

Requirements
------------

**Backend:**

  * PHP 7.1.3 or higher;
  * Symfony 3.4 applications or higher;

**Frontend:**

  * jQuery v3 or higher - https://jquery.com
  * Select2 v4 or higher - https://select2.org

Installation
------------

```bash
$ composer require yceruto/rich-form-bundle
$ php bin/console assets:install --symlink
```

Configuration
-------------

**Controller**

```yaml
# config/routes/rich_form.yaml
rich_form:
    resource: '@RichFormBundle/Resources/config/routing/entity2.xml'
```

**Form Themes**
```yaml
# config/packages/twig.yaml
twig:
    form_themes:
        - '@RichForm/form/fields.html.twig'
```

**Assets**

This bundle doesn't provide any version of the Select2 plugin.

```html
<!doctype html>
<html>
    <head>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet" />

        <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.full.min.js"></script>
        <script src="{{ asset('bundles/richform/entity2.js') }}"></script>
    </head>
```

**Basic Usage**

```php
$formBuilder->add('category', Entity2Type::class, [
    'class' => Category::class,
]);
```

The `Entity2Type` extends from the built-in `EntityType` by adding the most awesome Select2 features: 
search via Ajax, pagination and custom result templates. All of them combined with the `EntityType`
options like `query_builder` and new options like `search_by`, `order_by`, `max_results` and more.

License
-------

This software is published under the [MIT License](LICENSE)
