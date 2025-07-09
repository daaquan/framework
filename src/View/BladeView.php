<?php

namespace Phare\View;

use Phalcon\Mvc\View;

class BladeView extends View
{
    public function with($key, $value = null)
    {
        $this->setVar($key, $value);

        return $this;
    }

    public function render(string $controllerName, string $actionName, array $params = [])
    {
        $path = app()['dispatcher']->getParam('bladeView') ?: $controllerName . '/' . $actionName;
        throw new \RuntimeException("View [$path.blade.php] not found");
    }
}
