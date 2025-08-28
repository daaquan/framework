<?php

use Phare\Http\Resources\JsonResource;
use Phare\Http\Resources\ResourceCollection;

// Create test resource classes
class UserResource extends JsonResource
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
}

class UserCollection extends ResourceCollection
{
    //
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
    $resource = new UserResource($this->userData);
    $array = $resource->toArray();

    expect($array)->toHaveKey('id');
    expect($array)->toHaveKey('name');
    expect($array)->toHaveKey('email');
    expect($array['id'])->toBe(1);
    expect($array['name'])->toBe('John Doe');
});

it('creates resource using make method', function () {
    $resource = UserResource::make($this->userData);

    expect($resource)->toBeInstanceOf(UserResource::class);
    expect($resource->toArray()['name'])->toBe('John Doe');
});

it('creates collection from array', function () {
    $users = [
        (object)['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
        (object)['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
    ];

    $collection = UserResource::collection($users);

    expect($collection)->toBeInstanceOf(ResourceCollection::class);
    expect($collection->count())->toBe(2);
});

it('handles when conditions', function () {
    $resource = new class($this->userData) extends JsonResource
    {
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'admin' => $this->when(false, 'is admin'),
                'verified' => $this->when(true, 'is verified'),
            ];
        }
    };

    $array = $resource->toArray();
    expect($array)->not->toHaveKey('admin');
    expect($array['verified'])->toBe('is verified');
});

it('handles whenHas conditions', function () {
    $resource = new class($this->userData) extends JsonResource
    {
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'created_at' => $this->whenHas('created_at'),
                'updated_at' => $this->whenHas('updated_at', 'fallback'),
                'deleted_at' => $this->whenHas('deleted_at'),
            ];
        }
    };

    $array = $resource->toArray();
    expect($array['created_at'])->toBe('2023-01-01');
    expect($array['updated_at'])->toBe('fallback');
    expect($array)->not->toHaveKey('deleted_at');
});

it('serializes to JSON', function () {
    $resource = new UserResource($this->userData);
    $json = $resource->toJson();

    $decoded = json_decode($json, true);
    expect($decoded)->toHaveKey('id');
    expect($decoded['name'])->toBe('John Doe');
});

it('implements JsonSerializable', function () {
    $resource = new UserResource($this->userData);
    $serialized = $resource->jsonSerialize();

    expect($serialized)->toBeArray();
    expect($serialized)->toHaveKey('id');
});

it('handles additional data', function () {
    $resource = new UserResource($this->userData);
    $resource->additional(['meta' => 'additional data']);

    $response = $resource->response();
    expect($response)->toBeInstanceOf(\Phare\Http\Resources\JsonResourceResponse::class);
});

it('allows custom wrapping', function () {
    JsonResource::wrap('custom_data');

    $resource = new UserResource($this->userData);
    $serialized = $resource->jsonSerialize();

    // Reset to default
    JsonResource::wrap('data');
});

it('disables wrapping', function () {
    JsonResource::withoutWrapping();

    $resource = new UserResource($this->userData);
    $serialized = $resource->jsonSerialize();

    // Reset to default
    JsonResource::wrap('data');
});

it('handles null values with whenNotNull', function () {
    $resource = new class($this->userData) extends JsonResource
    {
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'name' => $this->whenNotNull($this->name),
                'phone' => $this->whenNotNull(null, 'no phone'),
            ];
        }
    };

    $array = $resource->toArray();
    expect($array['name'])->toBe('John Doe');
    expect($array['phone'])->toBe('no phone');
});

it('merges data conditionally', function () {
    $resource = new class($this->userData) extends JsonResource
    {
        public function toArray(): array
        {
            return array_merge([
                'id' => $this->id,
                'name' => $this->name,
            ], $this->mergeWhen(true, [
                'email' => $this->email,
            ]), $this->mergeWhen(false, [
                'password' => 'secret',
            ]));
        }
    };

    $array = $resource->toArray();
    expect($array)->toHaveKey('email');
    expect($array)->not->toHaveKey('password');
});
