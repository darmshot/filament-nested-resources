<?php

namespace Darmshot\FilamentNestedResources;

use Filament\Panel;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

abstract class NestedResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static bool $shouldRegisterNavigationWhenInContext = true;

    /**
     * Resource|NestedResource
     *
     * @return string
     */
    abstract public static function getParent(): string;

    public static function getParentAccessor(): string
    {
        return Str::of(static::getParent()::getModel())
            ->afterLast('\\Models\\')
            ->camel();
    }

    public static function getParentId(): int|string|null
    {
        $parentId = Route::current()->parameter(static::getParentAccessor(), Route::current()->parameter('record'));

        return $parentId instanceof Model ? $parentId->getKey() : $parentId;
    }

    public static function getEloquentQuery(string|int|null $parent = null): Builder
    {
        // todo resolved
        $query = parent::getEloquentQuery();
        $parentModel = static::getParent()::getModel();
        $key = (new $parentModel())->getKeyName();
        $query->whereHas(
            static::getParentAccessor(),
            fn(Builder $builder) => $builder->where($key, '=', $parent ?? static::getParentId())
        );

        return $query;
    }

    public static function routes(Panel $panel): void
    {
        //todo resolved
        $slug = static::getSlug();

        $prefix = '';
        foreach (static::getParentTree(static::getParent()) as $parent) {
            $prefix .= $parent->urlPart . '/{' . $parent->urlPlaceholder . '}/';
        }

        Route::name( (string) str($slug)
            ->replace('/', '.')
            ->append('.'))
            ->prefix($prefix . $slug)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->group(function () use ($panel) {
                foreach (static::getPages() as $name => $page) {
                    $page->registerRoute($panel)?->name($name);
                }
            });
    }

    public static function getUrl(string $name = 'index', array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string
    {
        // todo resolved
        if (!is_array($parameters)) {
            $parameters = [$parameters];
        }

        $list = static::getParentParametersForUrl(static::getParent(), $parameters);

        $parameters = [...$parameters, ...$list];

        // Attempt to figure out what url binding should be set for the record.
        $childParameters = Route::current()->parameters();

        if (isset($childParameters['record'])) {
            /** @var Page $controller */
            $controller = Route::current()->getController();
            /** @var resource $resource */
            $resource = $controller::getResource();

            $parameters[Str::singular($resource::getSlug())] = $childParameters['record'];
        }

        return parent::getUrl($name, [...$childParameters, ...$parameters], $isAbsolute, $panel, $tenant);
    }

    /**
     * @return NestedEntry[]
     */
    public static function getParentTree(string $parent, array $urlParams = []): array
    {
        /** @var $parent Resource|NestedResource */
        $singularSlug = Str::camel(Str::singular($parent::getSlug()));

        $list = [];
        if (new $parent() instanceof NestedResource) {
            $list = [...$list, ...static::getParentTree($parent::getParent(), $urlParams)];
        }

        $urlParams = static::getParentParametersForUrl($parent, $urlParams);

        $id = Route::current()?->parameter(
            $singularSlug,
            $urlParams[$singularSlug] ?? null
        );

        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        $list[$parent::getSlug()] = new NestedEntry(
            urlPlaceholder: Str::camel(Str::singular($parent::getSlug())),
            urlPart: $parent::getSlug(),
            resource: $parent,
            label: $parent::getPluralModelLabel(),
            id: $id,
            urlParams: $urlParams
        );

        return $list;
    }

    public static function getParentParametersForUrl(string $parent, array $urlParameters = []): array
    {
        /** @var $parent Resource|NestedResource */
        $list = [];
        $singularSlug = Str::camel(Str::singular($parent::getSlug()));
        if (new $parent() instanceof NestedResource) {
            $list = static::getParentParametersForUrl($parent::getParent(), $urlParameters);
        }
        $list[$singularSlug] = Route::current()?->parameter(
            $singularSlug,
            $urlParameters[$singularSlug] ?? null
        );

        foreach ($list as $key => $value) {
            if ($value instanceof Model) {
                $list[$key] = $value->getKey();
            }
        }

        return $list;
    }

    public static function getNavigationGroup(): ?string
    {
        if (static::getParentId()) {
            return static::getParent()::getRecordTitle(
                static::getParent()::getModel()::find(
                    static::getParentId()
                )
            );
        }

        return static::getParent()::getModelLabel();
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (static::$shouldRegisterNavigationWhenInContext) {
            try {
                static::getUrl('index');

                return true;
            } catch (UrlGenerationException) {
                return false;
            }
        }

        return parent::shouldRegisterNavigation();
    }
}
