<?php

namespace Phare\Providers;

use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Phare\Foundation\AbstractApplication as Application;

class VoltViewProvider implements ServiceProviderInterface
{
    public function register(Application|DiInterface $app): void
    {
        $app->singleton('volt', function (View $view) use ($app) {
            $volt = new Volt($view, $app);
            $volt->setOptions([
                'path' => $app->storagePath('framework/views/'),
                'separator' => '_',
                'prefix' => 'view',
            ]);
            $compiler = $volt->getCompiler();
            $compiler
                ->addFilter('date_format', 'view_date_format')
                ->addFilter('date_format_full', 'view_date_format_full')
                ->addFilter('date_format_tz', 'view_date_format_tz')
                ->addFilter('user_id', 'user_id')
                ->addFilter('public_id', 'public_id')
                ->addFilter('number_format', 'number_format')
                ->addFilter('to_int', 'to_int')
                ->addFilter('http_build_query', 'http_build_query')
                ->addFilter('json_decode', 'json_decode')
                ->addFilter('var_export', 'var_export')
                ->addFilter('s3_object_url2console_url', 's3_object_url2console_url')
                ->addFilter('s3_object_filename', 's3_object_filename');

            // button style link
            $compiler->addFunction(
                'btn_link',
                function ($resolvedArgs, $exprArgs) {
                    return '\Phare\View\Tag::btnLink(' . $resolvedArgs . ')';
                }
            );

            // icon button style link
            $compiler->addFunction(
                'icon_btn_link',
                function ($resolvedArgs, $exprArgs) {
                    return '\Phare\View\Tag::btnIconLink(' . $resolvedArgs . ')';
                }
            );

            // button style submit button
            $compiler->addFunction(
                'icon_submit_button',
                function ($resolvedArgs, $exprArgs) {
                    return '\Phare\View\Tag::iconSubmitButton(' . $resolvedArgs . ')';
                }
            );

            // number_format
            $compiler->addFunction('number_format', 'number_format');

            return $volt;
        });

        $app->singleton('view', function () use ($app) {
            $viewDir = $app->basePath('resources/views/');

            return (new View())
                ->enable()
                ->registerEngines(['.phtml' => 'volt'])
                ->setLayoutsDir($viewDir . 'layouts/')
                ->setPartialsDir($viewDir . 'partials/')
                ->setRenderLevel(View::LEVEL_LAYOUT)
                ->setViewsDir($viewDir);
        });
    }
}
