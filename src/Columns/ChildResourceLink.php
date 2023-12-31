<?php

namespace Darmshot\FilamentNestedResources\Columns;

use Darmshot\FilamentNestedResources\NestedResource;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;

class ChildResourceLink extends TextColumn
{
    /**
     * @link NestedResource
     */
    private string $resourceClass;

    public static function make(string $name): static
    {
        $item = parent::make($name);
        $item->forResource($name);
        $item->label($item->getChildLabelPlural());

        return $item;
    }

    public function getChildLabelPlural(): string
    {
        return Str::title($this->resourceClass::getPluralModelLabel());
    }

    public function getChildLabelSingular(): string
    {
        return Str::title($this->resourceClass::getModelLabel());
    }

    public function forResource(string $resourceClass): static
    {
        $this->resourceClass = $resourceClass;

        return $this;
    }

    public function getState(): string
    {
        $count = $this->getCount();

        return $count . ' ' . ($count === 1 ? $this->getChildLabelSingular() : $this->getChildLabelPlural());
    }

    public function getUrl(): ?string
    {
        $baseParams = [];
        if (property_exists($this->table->getLivewire(), 'urlParameters')) {
            $baseParams = $this->table->getLivewire()->urlParameters;
        }

        $param = Str::camel(Str::singular($this->resourceClass::getParent()::getSlug()));

        return $this->resourceClass::getUrl(
            'index',
            [...$baseParams, $param => $this->record->getKey()]
        );
    }

    private function getCount(): int
    {
        return $this->resourceClass::getEloquentQuery($this->record->getKey())->count();
    }
}
