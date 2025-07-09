<?php

namespace Phare\Foundation\Http;

/**
 * Interface for Request methods
 *
 * Implementation of this file has been influenced by PHP FIG
 *
 * @link    https://github.com/php-fig/http-message-util/
 *
 * @license https://github.com/php-fig/http-message-util/blob/master/LICENSE
 */
enum RequestMethod: string
{
    case CONNECT = 'CONNECT';

    case DELETE = 'DELETE';

    case GET = 'GET';

    case HEAD = 'HEAD';

    case OPTIONS = 'OPTIONS';

    case PATCH = 'PATCH';

    case POST = 'POST';

    case PURGE = 'PURGE';

    case PUT = 'PUT';

    case TRACE = 'TRACE';
}
