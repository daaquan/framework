<?php

namespace Phare\Http\Resources;

use Phalcon\Http\Response;

class JsonResourceResponse
{
    protected JsonResource $resource;
    protected ?Response $response = null;

    public function __construct(JsonResource $resource)
    {
        $this->resource = $resource;
    }

    public function toResponse(): Response
    {
        $response = new Response();
        
        $data = $this->wrap(
            $this->resource->resolve(),
            $this->resource->with(),
            $this->resource->additional ?? []
        );

        $response->setJsonContent($data);
        $response->setContentType('application/json');
        $response->setStatusCode(200);

        return $response;
    }

    protected function wrap(array $data, array $with = [], array $additional = []): array
    {
        if ($data instanceof ResourceCollection) {
            $data = $data->toArray();
        }

        if ($this->haveDefaultWrapperAndDataIsUnwrapped($data)) {
            $data = [$this->wrapper() => $data];
        } elseif ($this->haveAdditionalInformationAndDataIsUnwrapped($data, $with, $additional)) {
            $data = [($this->wrapper() ?? 'data') => $data];
        }

        return array_merge_recursive($data, $with, $additional);
    }

    protected function haveDefaultWrapperAndDataIsUnwrapped(array $data): bool
    {
        return $this->wrapper() && !$this->isAssoc($data);
    }

    protected function haveAdditionalInformationAndDataIsUnwrapped(array $data, array $with, array $additional): bool
    {
        return (!$this->isAssoc($data) || $this->wrapper()) && 
               (count($with) || count($additional));
    }

    protected function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function wrapper(): ?string
    {
        return JsonResource::$wrap;
    }

    public function withResponse(Response $response): static
    {
        $this->response = $response;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->response?->getStatusCode() ?? 200;
    }

    public function getHeaders(): array
    {
        return $this->response?->getHeaders()->toArray() ?? [];
    }
}