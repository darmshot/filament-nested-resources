<?php

namespace Darmshot\FilamentNestedResources\Table\Actions;

use Filament\Tables\Actions\Action;
use Illuminate\Support\Str;

class LinkToChildrenAction extends Action
{
    private string $childResource;

    public function forChildResource(string $childResource): self
    {
        $this->childResource = $childResource;

        return $this;
    }

    public function getUrl(): ?string
    {
        $parent = $this->getRecord()->{$this->getRecord()->getKeyName()};

        $params = [Str::camel(Str::singular($this->childResource::getParent()::getSlug())) => $parent];

        return $this->childResource::getUrl(
            'index',
            $this->childResource::getParentParametersForUrl($this->childResource, $params)
        );
    }
}
