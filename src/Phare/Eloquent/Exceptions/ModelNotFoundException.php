<?php

namespace Phare\Eloquent\Exceptions;

/**
 * Thrown when a query that must return a model returns nothing.
 *
 * This used to be Phalcon\Mvc\Model\Exception, which arrived through the
 * Phalcon inheritance Phare's Model no longer has.
 */
class ModelNotFoundException extends \RuntimeException {}
