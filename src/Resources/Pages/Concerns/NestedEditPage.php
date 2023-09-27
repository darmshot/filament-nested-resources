<?php

namespace Darmshot\FilamentNestedResources\Resources\Pages\Concerns;

use Darmshot\FilamentNestedResources\NestedResource;
use Filament\Forms\Form;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * @extends \Filament\Resources\Pages\EditRecord
 * @extends \Filament\Resources\Pages\ViewRecord
 * @extends \Filament\Resources\Pages\ListRecords
 */
trait NestedEditPage
{
    public array $urlParameters;

    /**
     * @return class-string<\Darmshot\FilamentNestedResources\NestedResource>
     */
    abstract public static function getResource(): string;

    public function boot(): void
    {
        if (empty($this->urlParameters)) {
            $this->urlParameters = $this->getUrlParametersForState();
        }
    }
    public function mount(int | string $record): void
    {
        // todo resolved
        parent::mount($record);

        if (empty($this->urlParameters)) {
            $this->urlParameters = $this->getUrlParametersForState();
        }
    }

    protected function getUrlParametersForState(): array
    {
        $parameters = Route::current()->parameters;

        foreach ($parameters as $key => $value) {
            if ($value instanceof Model) {
                $parameters[$key] = $value->getKey();
            }
        }

        return $parameters;
    }

    public function getBreadcrumbs(): array
    {
        /** @var NestedResource $resource */
        $resource = static::getResource();

        // Build the nested breadcrumbs.
        $nestedCrumbs = [];
        foreach ($resource::getParentTree(static::getResource()::getParent(), $this->urlParameters) as $i => $nested) {
            // Here we check if we can view and/or edit a record, if not we replace the link with a #.
            // List.
            if ($nested->resource::canViewAny()) {
                $nestedCrumbs[$nested->getListUrl()] = $nested->resource::getBreadcrumb();
            } else {
                $nestedCrumbs[] = $nested->resource::getBreadcrumb();
            }

            // Edit.
            if (($record = $nested->getRecord()) && $nested->resource::canEdit($record)) {
                $nestedCrumbs[$nested->getEditUrl()] = $nested->getBreadcrumbTitle();
            } else {
                $nestedCrumbs[] = $nested->getBreadcrumbTitle();
            }
        }

        // Add the current list entry.
        if ($resource::canViewAny()) {
            $currentListUrl = $resource::getUrl(
                'index',
                $resource::getParentParametersForUrl($resource::getParent(), $this->urlParameters)
            );
            $nestedCrumbs[$currentListUrl] = $resource::getBreadcrumb();
        } else {
            $nestedCrumbs[] = $resource::getBreadcrumb();
        }

        // If it is a view page we need to add the current entry.
        if ($this instanceof ViewRecord) {
            if ($resource::canEdit($this->record)) {
                $nestedCrumbs[$resource::getUrl('edit', $this->urlParameters)] = $this->getRecordTitle();
            } else {
                $nestedCrumbs[] = $this->getTitle();
            }
        }

        // Finalize with the current url.
        $breadcrumb = $this->getBreadcrumb();
        if (filled($breadcrumb)) {
            $nestedCrumbs[] = $breadcrumb;
        }

        return $nestedCrumbs;
    }


    protected function configureDeleteAction(\Filament\Actions\DeleteAction $action): void
    {
        // todo resolved
        $resource = static::getResource();

        $action
            ->authorize($resource::canDelete($this->getRecord()))
            ->successRedirectUrl($resource::getUrl('index', $this->urlParameters));
    }


    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();

        if ($resource::hasPage('view') && $resource::canView($this->record)) {
            return $resource::getUrl('view', [...$this->urlParameters, 'record' => $this->record]);
        }

        if ($resource::hasPage('edit') && $resource::canEdit($this->record)) {
            return $resource::getUrl('edit', [...$this->urlParameters, 'record' => $this->record]);
        }

        return $resource::getUrl('index', $this->urlParameters);
    }

    protected function getParentId(): string|int
    {
        /** @var NestedResource $resource */
        $resource = $this::getResource();

        $parent = Str::camel(Str::afterLast($resource::getParent()::getModel(), '\\'));

        if ($this->urlParameters[$parent] instanceof Model) {
            return $this->urlParameters[$parent]->getKey();
        }

        if (is_array($this->urlParameters[$parent]) && isset($this->urlParameters[$parent]['id'])) {
            return $this->urlParameters[$parent]['id'];
        }

        return $this->urlParameters[$parent];
    }

    public function getParent(): Model
    {
        $resource = $this::getResource();

        return $resource::getParent()::getModel()::find($this->getParentId());
    }

    public function form(Form $form): Form
    {
        // todo resolved
        return static::getResource()::form(
            $form
                ->operation('edit')
                ->model($this->getRecord())
                ->statePath($this->getFormStatePath())
                ->columns($this->hasInlineLabels() ? 1 : 2)
                ->inlineLabel($this->hasInlineLabels()),
            $this->getParent()
        );
    }
}
