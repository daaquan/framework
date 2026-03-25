<?php

namespace Tests\Mock\app\Http\Controllers\Api;

use Tests\Mock\app\Http\Controllers\Controller;

class IndexController extends Controller
{
    public function index()
    {
        return [];
    }

    public function indexAction(int $id): array
    {
        return ['id' => $id];
    }
}
