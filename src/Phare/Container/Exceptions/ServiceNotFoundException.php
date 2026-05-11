<?php

namespace Phare\Container\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFoundException extends \RuntimeException implements NotFoundExceptionInterface {}
