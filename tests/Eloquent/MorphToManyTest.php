<?php

if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
    test('morph to many tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for morph to many tests.');
    });

    return;
}

use Phare\Database\Schema\Blueprint;
use Phare\Database\Schema\SchemaBuilder;
use Tests\Mock\Models\Post;
use Tests\Mock\Models\Tag;
use Tests\Mock\Models\Video;

beforeEach(function () {
    $connection = $this->app->make('db');
    $schema = new SchemaBuilder($connection);

    foreach (['taggables', 'videos', 'posts', 'tags'] as $table) {
        if ($schema->hasTable($table)) {
            $connection->execute('DROP TABLE ' . $table);
        }
    }

    $schema->create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });

    $schema->create('videos', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });

    $schema->create('tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    $schema->create('taggables', function (Blueprint $table) {
        $table->foreignId('tag_id');
        $table->foreignId('taggable_id');
        $table->string('taggable_type');
    });
});

function makePost(string $title = 'First post'): Post
{
    $post = new Post();
    $post->fill(['title' => $title]);
    $post->create();

    return $post;
}

function makeVideo(string $title = 'First video'): Video
{
    $video = new Video();
    $video->fill(['title' => $title]);
    $video->create();

    return $video;
}

function makeTag(string $name): Tag
{
    $tag = new Tag();
    $tag->fill(['name' => $name]);
    $tag->create();

    return $tag;
}

it('loads a polymorphic many to many relationship', function () {
    $post = makePost();
    $tag = makeTag('news');

    $post->tags()->attach($tag->id);

    $fresh = Post::where('id', $post->id)->first();

    expect($fresh->tags)->toHaveCount(1)
        ->and($fresh->tags->first()->name)->toBe('news')
        ->and($fresh->tags->first()->pivot->taggable_type)->toBe(Post::class);
});

it('attaches and detaches with morph type scoping', function () {
    $post = makePost();
    $video = makeVideo();
    $tag = makeTag('featured');

    $post->tags()->attach($tag->id);
    $video->tags()->attach($tag->id);

    $deleted = $post->tags()->detach([$tag->id]);
    $rows = $this->app->make('db')->fetchAll('SELECT * FROM taggables ORDER BY taggable_type');

    expect($deleted)->toBe(1)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]['taggable_type'])->toBe(Video::class);
});

it('supports the inverse morphed by many relation', function () {
    $post = makePost();
    $video = makeVideo();
    $tag = makeTag('popular');

    $post->tags()->attach($tag->id);
    $video->tags()->attach($tag->id);

    $fresh = Tag::where('id', $tag->id)->first();

    expect($fresh->posts)->toHaveCount(1)
        ->and($fresh->posts->first()->id)->toBe($post->id)
        ->and($fresh->videos)->toHaveCount(1)
        ->and($fresh->videos->first()->id)->toBe($video->id);
});

it('eager loads morph many to many relations', function () {
    $first = makePost('First');
    $second = makePost('Second');
    $alpha = makeTag('alpha');
    $beta = makeTag('beta');

    $first->tags()->attach($alpha->id);
    $second->tags()->attach($beta->id);

    $posts = Post::with('tags')->orderBy('id')->get();

    expect($posts)->toHaveCount(2)
        ->and($posts[0]->relationLoaded('tags'))->toBeTrue()
        ->and($posts[0]->tags->first()->name)->toBe('alpha')
        ->and($posts[1]->tags->first()->name)->toBe('beta');
});
