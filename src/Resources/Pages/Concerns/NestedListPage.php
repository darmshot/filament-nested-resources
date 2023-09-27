<?php

namespace Darmshot\FilamentNestedResources\Resources\Pages\Concerns;

use Darmshot\FilamentNestedResources\NestedResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * @extends \Filament\Resources\Pages\EditRecord
 * @extends \Filament\Resources\Pages\ViewRecord
 * @extends \Filament\Resources\Pages\ListRecords
 */
trait NestedListPage
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

    public function mount(): void
    {
        // todo resolved
        parent::mount();

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

    protected function getTableQuery(): Builder
    {
        $urlParams = array_values($this->urlParameters);
        $parameter = array_pop($urlParams);

        $query = static::getResource()::getEloquentQuery($parameter);

        $tabs = $this->getTabs();

        if (
            filled($this->activeTab) &&
            array_key_exists($this->activeTab, $tabs)
        ) {
            $tabs[$this->activeTab]->modifyQuery($query);
        }

        return $query;
    }

    protected function configureEditAction(Tables\Actions\EditAction $action): void
    {
        //todo resolved
        $resource = static::getResource();

        $action
            ->authorize(fn (Model $record): bool => $resource::canEdit($record))
            ->form(fn (Form $form): Form => $this->form($form->columns(2)));

        if ($resource::hasPage('edit')) {
            $action->url(fn (Model $record): string => $resource::getUrl('edit', [...$this->urlParameters, 'record' => $record]));
        }
    }

    protected function configureCreateAction(CreateAction | Tables\Actions\CreateAction $action): void
    {
        //todo resolved
        $resource = static::getResource();

        $action
            ->authorize($resource::canCreate())
            ->model($this->getModel())
            ->modelLabel($this->getModelLabel() ?? static::getResource()::getModelLabel())
            ->form(fn (Form $form): Form => $this->form($form->columns(2)));

        if ($action instanceof CreateAction) {
            $action->relationship(($tenant = Filament::getTenant()) ? fn (): Relation => static::getResource()::getTenantRelationship($tenant) : null);
        }

        if ($resource::hasPage('create')) {
            $action->url(fn (): string => $resource::getUrl('create', parameters: $this->urlParameters));
        }
    }

    protected function configureDeleteAction(Tables\Actions\DeleteAction $action): void
    {
        //todo resolved
        $resource = static::getResource();

        $action
            ->authorize(fn (Model $record): bool => static::getResource()::canDelete($record))->record($this->getRecord())
            ->recordTitle($this->getRecordTitle())
            ->successRedirectUrl($resource::getUrl('index', $this->urlParameters));

        $action
            ->authorize(fn (Model $record): bool => static::getResource()::canDelete($record));
    }

    protected function configureViewAction(Tables\Actions\ViewAction $action): void
    {
        //todo resolved
        $resource = static::getResource();

        $action
            ->authorize(fn (Model $record): bool => $resource::canView($record))
            ->infolist(fn (Infolist $infolist): Infolist => $this->infolist($infolist->columns(2)))
            ->form(fn (Form $form): Form => $this->form($form->columns(2)));

        if ($resource::hasPage('view')) {
            $action->url(fn (Model $record): string => $resource::getUrl('view', [...$this->urlParameters, 'record' => $record]));
        }
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

    protected function getParentId(): string | int
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
        return static::getResource()::form($form, $this->getParent());
    }

    /*    protected function getTableRecordUrlUsing(): ?Closure
        {
            return function (Model $record): ?string {
                foreach (['view', 'edit'] as $action) {

                    $action = $this->getAction($action);

                    if (!$action) {
                        continue;
                    }

                    $action->record($record);

                    if ($action->isHidden()) {
                        continue;
                    }

                    $url = $action->getUrl();

                    if (!$url) {
                        continue;
                    }

                    return $url;
                }

                $resource = static::getResource();

                foreach (['view', 'edit'] as $action) {
                    if (!$resource::hasPage($action)) {
                        continue;
                    }

                    if (!$resource::{'can' . ucfirst($action)}($record)) {
                        continue;
                    }

                    return $resource::getUrl($action, [...$this->urlParameters, 'record' => $record]);
                }

                return null;
            };
        }*/
}
