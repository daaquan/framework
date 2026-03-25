<?php

use Phare\Support\DataTransferObject;

enum TestColor: string
{
    case Red = 'red';
    case Blue = 'blue';
}

class AddressDto extends DataTransferObject
{
    public string $street;

    public string $city;
}

class PersonDto extends DataTransferObject
{
    public string $name;

    public int $age;

    public TestColor $color;

    public AddressDto $address;
}

class SimpleDto extends DataTransferObject
{
    public string $foo;

    public string $bar;

    public string $baz;
}

test('constructs from array', function () {
    $dto = new SimpleDto(['foo' => 'a', 'bar' => 'b', 'baz' => 'c']);

    expect($dto->foo)->toBe('a');
    expect($dto->bar)->toBe('b');
    expect($dto->baz)->toBe('c');
});

test('toArray returns all initialized public properties', function () {
    $dto = new SimpleDto(['foo' => 'a', 'bar' => 'b', 'baz' => 'c']);

    expect($dto->toArray())->toBe(['foo' => 'a', 'bar' => 'b', 'baz' => 'c']);
});

test('only returns subset of properties', function () {
    $dto = new SimpleDto(['foo' => 'a', 'bar' => 'b', 'baz' => 'c']);

    expect($dto->only('foo', 'baz'))->toBe(['foo' => 'a', 'baz' => 'c']);
});

test('except excludes specified properties', function () {
    $dto = new SimpleDto(['foo' => 'a', 'bar' => 'b', 'baz' => 'c']);

    expect($dto->except('bar'))->toBe(['foo' => 'a', 'baz' => 'c']);
});

test('casts enum values from backing type', function () {
    $dto = new PersonDto(['name' => 'John', 'age' => 30, 'color' => 'red', 'address' => ['street' => '1st', 'city' => 'NY']]);

    expect($dto->color)->toBe(TestColor::Red);
});

test('casts nested DTO from array', function () {
    $dto = new PersonDto(['name' => 'John', 'age' => 30, 'color' => 'blue', 'address' => ['street' => 'Main St', 'city' => 'LA']]);

    expect($dto->address)->toBeInstanceOf(AddressDto::class);
    expect($dto->address->street)->toBe('Main St');
    expect($dto->address->city)->toBe('LA');
});

test('nested DTO serializes in toArray', function () {
    $dto = new PersonDto(['name' => 'John', 'age' => 30, 'color' => 'blue', 'address' => ['street' => 'A', 'city' => 'B']]);

    $array = $dto->toArray();
    expect($array['address'])->toBe(['street' => 'A', 'city' => 'B']);
});

test('from factory method creates instance', function () {
    $dto = SimpleDto::from(['foo' => 'x', 'bar' => 'y', 'baz' => 'z']);

    expect($dto)->toBeInstanceOf(SimpleDto::class);
    expect($dto->foo)->toBe('x');
});

test('fill updates existing instance', function () {
    $dto = new SimpleDto(['foo' => 'a', 'bar' => 'b', 'baz' => 'c']);
    $dto->fill(['foo' => 'updated']);

    expect($dto->foo)->toBe('updated');
    expect($dto->bar)->toBe('b');
});

test('ignores unknown keys in data', function () {
    $dto = new SimpleDto(['foo' => 'a', 'bar' => 'b', 'baz' => 'c', 'unknown' => 'ignored']);

    expect($dto->toArray())->toBe(['foo' => 'a', 'bar' => 'b', 'baz' => 'c']);
});

test('toArray skips uninitialized properties', function () {
    $dto = new SimpleDto(['foo' => 'a']);

    expect($dto->toArray())->toBe(['foo' => 'a']);
});
