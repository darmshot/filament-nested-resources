# Nested resources plugin for Filament

[![Latest Version on Packagist](https://img.shields.io/packagist/v/darmshot/filament-nested-resources.svg?style=flat-square)](https://packagist.org/packages/darmshot/filament-nested-resources)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/darmshot/filament-nested-resources/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/darmshot/filament-nested-resources/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/darmshot/filament-nested-resources/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/darmshot/filament-nested-resources/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/darmshot/filament-nested-resources.svg?style=flat-square)](https://packagist.org/packages/darmshot/filament-nested-resources)



This package adds support for nested resources in Filament 3.

It provides the base classes and column to provide the nested structure.

It currently is not that configurable, and you need to follow naming conventions usually used by Laravel.

## Installation

You can install the package via composer:

```bash
composer require darmshot/filament-nested-resources
```

## Usage

However, you need to do a couple of changes to make this work. But you start of by creating a
[filament resource](https://filamentphp.com/docs/3.x/panels/resources/getting-started)
(The parent resource should already exist at this point. The resource we are changing is the child one).

Once you have that, you will need to change the `Filament/Resources/ChildModelResource.php` to the `NestedResource`.

```php
use Darmshot\FilamentNestedResources\Columns\ChildResourceLink;
use Darmshot\FilamentNestedResources\NestedResource;

class ChildModelResource extends NestedResource
{
    public static function getParent(): string
    {
        return ParentModelResource::class;
    }
}
```

Then for each of the resource pages, you need to add traits:
```php
use Darmshot\FilamentNestedResources\ResourcePages\NestedCreatePage;
use Darmshot\FilamentNestedResources\ResourcePages\NestedEditPage;
use Darmshot\FilamentNestedResources\ResourcePages\NestedListPage;
```

Finally, on your `ParentModelResource` you can add the column to provide the links:

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            ChildResourceLink::make(ChildModelResource::class),
        ]);
}
```

### Accessing the parent

When you need the parent in livewire context such as the form, you can add the second argument to your form method:

```php
public static function form(Form $form, ?Event $parent = null): Form;
```

Where `Event` is the model that should be the parent.

### Sidebar

By default, when in a "context" the sidebar will register the menu item for that resource.

So if you are inside a Project which has documents, the sidebar will show documents when you are on a project or deeper
level.

If you do not want this, you can set `shouldRegisterNavigationWhenInContext` to false in the child resource.

### Notes

You cannot use a child resource for multiple parents, however, you can have multiple resources pointing to the same
model.

Just make sure you set a custom slug for the resources so that it builds unique routes.

https://filamentphp.com/docs/3.x/panels/pages#customizing-the-page-url

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Stanislav Gerasimenko](https://github.com/darmshot)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
