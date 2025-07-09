<?php

namespace Phare\Eloquent\Concerns;

use Phalcon\Mvc\Model\Behavior\Timestampable;

trait HasTimestamps
{
    protected const CREATED_AT = 'created_at';

    protected const UPDATED_AT = 'updated_at';

    public function initializeTimestampable(): void
    {
        $this->addBehavior(new Timestampable([
            'beforeCreate' => [
                'field' => static::CREATED_AT,
                'format' => 'Y-m-d H:i:s',
            ],
            'beforeUpdate' => [
                'field' => static::UPDATED_AT,
                'format' => 'Y-m-d H:i:s',
            ],
        ]));
    }
}
