<?php

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\ColumnDefinition;

it('can create basic column definitions', function () {
    $blueprint = new Blueprint('users');
    
    $id = $blueprint->id();
    expect($id)->toBeInstanceOf(ColumnDefinition::class);
    expect($id->getType())->toBe('bigIncrements');
    expect($id->getName())->toBe('id');
});

it('can create string columns with custom length', function () {
    $blueprint = new Blueprint('users');
    
    $name = $blueprint->string('name', 100);
    expect($name->getType())->toBe('string');
    expect($name->getName())->toBe('name');
    expect($name->getAttributes()['length'])->toBe(100);
});

it('can create columns with modifiers', function () {
    $blueprint = new Blueprint('users');
    
    $email = $blueprint->string('email')->unique()->nullable();
    expect($email->getAttributes()['unique'])->toBe(true);
    expect($email->getAttributes()['nullable'])->toBe(true);
});

it('can create foreign key columns', function () {
    $blueprint = new Blueprint('posts');
    
    $userId = $blueprint->foreignId('user_id');
    expect($userId->getType())->toBe('bigInteger');
    expect($userId->getName())->toBe('user_id');
});

it('can create timestamp columns', function () {
    $blueprint = new Blueprint('users');
    
    $blueprint->timestamps();
    $columns = $blueprint->getColumns();
    
    expect($columns)->toHaveCount(2);
    expect($columns[0]->getName())->toBe('created_at');
    expect($columns[1]->getName())->toBe('updated_at');
});

it('can create soft delete column', function () {
    $blueprint = new Blueprint('users');
    
    $blueprint->softDeletes();
    $columns = $blueprint->getColumns();
    
    expect($columns)->toHaveCount(1);
    expect($columns[0]->getName())->toBe('deleted_at');
    expect($columns[0]->getAttributes()['nullable'])->toBe(true);
});

it('can create various numeric column types', function () {
    $blueprint = new Blueprint('stats');
    
    $count = $blueprint->integer('count');
    $amount = $blueprint->decimal('amount', 10, 2);
    $percentage = $blueprint->float('percentage');
    
    expect($count->getType())->toBe('integer');
    expect($amount->getType())->toBe('decimal');
    expect($amount->getAttributes()['precision'])->toBe(10);
    expect($amount->getAttributes()['scale'])->toBe(2);
    expect($percentage->getType())->toBe('float');
});

it('can create text column types', function () {
    $blueprint = new Blueprint('articles');
    
    $summary = $blueprint->text('summary');
    $content = $blueprint->longText('content');
    
    expect($summary->getType())->toBe('text');
    expect($content->getType())->toBe('longText');
});

it('can create date and boolean columns', function () {
    $blueprint = new Blueprint('events');
    
    $date = $blueprint->date('event_date');
    $active = $blueprint->boolean('is_active');
    $datetime = $blueprint->dateTime('scheduled_at');
    
    expect($date->getType())->toBe('date');
    expect($active->getType())->toBe('boolean');
    expect($datetime->getType())->toBe('dateTime');
});

it('can create enum columns', function () {
    $blueprint = new Blueprint('orders');
    
    $status = $blueprint->enum('status', ['pending', 'processing', 'completed', 'cancelled']);
    
    expect($status->getType())->toBe('enum');
    expect($status->getAttributes()['values'])->toBe(['pending', 'processing', 'completed', 'cancelled']);
});

it('can create json and binary columns', function () {
    $blueprint = new Blueprint('data');
    
    $metadata = $blueprint->json('metadata');
    $file = $blueprint->binary('file_content');
    
    expect($metadata->getType())->toBe('json');
    expect($file->getType())->toBe('binary');
});

it('tracks whether blueprint is for updating table', function () {
    $createBlueprint = new Blueprint('users');
    $updateBlueprint = new Blueprint('users', true);
    
    expect($createBlueprint->isUpdating())->toBe(false);
    expect($updateBlueprint->isUpdating())->toBe(true);
});

it('can create indexes', function () {
    $blueprint = new Blueprint('users');
    
    $blueprint->index('email');
    $blueprint->unique(['email', 'username']);
    $blueprint->primary('id');
    
    $commands = $blueprint->getCommands();
    
    expect($commands)->toHaveCount(3);
    expect($commands[0]['type'])->toBe('index');
    expect($commands[1]['type'])->toBe('unique');
    expect($commands[2]['type'])->toBe('primary');
});

it('can drop columns and indexes', function () {
    $blueprint = new Blueprint('users', true);
    
    $blueprint->dropColumn('old_field');
    $blueprint->dropUnique('users_email_unique');
    $blueprint->dropIndex('users_name_index');
    
    $commands = $blueprint->getCommands();
    
    expect($commands)->toHaveCount(3);
    expect($commands[0]['type'])->toBe('dropColumn');
    expect($commands[1]['type'])->toBe('dropUnique');
    expect($commands[2]['type'])->toBe('dropIndex');
});

it('can rename columns', function () {
    $blueprint = new Blueprint('users', true);
    
    $blueprint->renameColumn('old_name', 'new_name');
    
    $commands = $blueprint->getCommands();
    
    expect($commands)->toHaveCount(1);
    expect($commands[0]['type'])->toBe('renameColumn');
    expect($commands[0]['from'])->toBe('old_name');
    expect($commands[0]['to'])->toBe('new_name');
});