<?php

namespace Phare\Support\Facades;

/**
 * @method static bool setStatusCode(int $code, string $message = null)
 * @method static bool setHeader(string $name, string $value)
 * @method static bool setRawHeader(string $header)
 * @method static bool setJsonContent(mixed $content, int $jsonOptions = 0, int $depth = 512)
 * @method static bool setContent(string $content)
 * @method static bool appendContent(string $content)
 * @method static string getContent()
 * @method static bool sendHeaders()
 * @method static bool send()
 */
class Response extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'response';
    }
}
