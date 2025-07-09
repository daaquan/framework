<?php

namespace Phare\Eloquent\Concerns;

use Phalcon\Mvc\Model\Behavior\SoftDelete;

trait SoftDeletes
{
    protected const DELETED_AT = 'deleted_at';

    protected bool $forceDeleting = false;

    public function initializeSoftDeletes(): void {}

    public function restore(): bool
    {
        $this->{static::DELETED_AT} = null;

        return $this->save();
    }

    public function forceDelete(): bool
    {
        $this->forceDeleting = true;

        return parent::delete();
    }

    protected function beforeDelete()
    {
        if (!$this->forceDeleting) {
            $this->addBehavior(new SoftDelete([
                'field' => static::DELETED_AT,
                'value' => date('Y-m-d H:i:s'),
            ]));
        }

        // Ensure the delete operation continues normally after adding the behavior
        return true;
    }
}
