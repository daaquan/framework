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
        if ($this->fireModelEvent('restoring', true) === false) {
            return false;
        }

        $this->{static::DELETED_AT} = null;

        $restored = $this->save();
        if ($restored) {
            $this->fireModelEvent('restored');
        }

        return $restored;
    }

    public function forceDelete(): bool
    {
        if ($this->fireModelEvent('forceDeleting', true) === false) {
            return false;
        }

        $this->forceDeleting = true;

        $deleted = parent::delete();
        if ($deleted) {
            $this->fireModelEvent('forceDeleted');
        }

        return $deleted;
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
