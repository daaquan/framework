<?php

use Phare\Http\Resources\JsonResource;
use Phare\Http\Resources\ResourceCollection;

function makeUserResource(object $user): JsonResource
{
    return new class($user) extends JsonResource
    {
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'created_at' => $this->whenHas('created_at'),
            ];
        }
    };
}

beforeEach(function () {
    $this->userData = (object)[
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'created_at' => '2023-01-01',
    ];
});

it('transforms single resource to array', function () {
    $array = makeUserResource($this->userData)->toArray();

    expect($array['id'])->toBe(1);
    expect($array['name'])->toBe('John Doe');
    expect($array['email'])->toBe('john@example.com');
});

it('creates collection from array', function () {
    $users = [
        (object)['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
        (object)['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
    ];

    $collection = makeUserResource($users[0])::collection($users);

    expect($collection)->toBeInstanceOf(ResourceCollection::class);
    expect($collection->count())->toBe(2);
});

it('handles when/whenHas/whenNotNull helpers', function () {
    $resource = new class($this->userData) extends JsonResource
    {
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'admin' => $this->when(false, 'is admin'),
                'verified' => $this->when(true, 'is verified'),
                'created_at' => $this->whenHas('created_at'),
                'updated_at' => $this->whenHas('updated_at', 'fallback'),
                'name' => $this->whenNotNull($this->name),
            ];
        }
    };

    $array = $resource->toArray();
    expect($array['admin'])->toBeNull();
    expect($array['verified'])->toBe('is verified');
    expect($array['created_at'])->toBe('2023-01-01');
    expect($array['updated_at'])->toBeNull();
    expect($array['name'])->toBe('John Doe');
});

it('serializes to json and supports additional/response', function () {
    $resource = makeUserResource($this->userData)->additional(['meta' => 'ok']);
    $json = $resource->toJson();
    $decoded = json_decode($json, true);

    expect($decoded['name'])->toBe('John Doe');
    expect($resource->response())->toBeInstanceOf(\Phare\Http\Resources\JsonResourceResponse::class);
});

it('supports wrapping configuration', function () {
    JsonResource::wrap('custom_data');
    $wrapped = makeUserResource($this->userData)->jsonSerialize();

    JsonResource::withoutWrapping();
    $unwrapped = makeUserResource($this->userData)->jsonSerialize();

    JsonResource::wrap('data');

    expect($wrapped)->toHaveKey('id');
    expect($unwrapped)->toHaveKey('id');
});
