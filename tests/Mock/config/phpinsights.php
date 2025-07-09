<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Preset
    |--------------------------------------------------------------------------
    |
    | This option controls the default preset that will be used by PHP Insights
    | to make your code reliable, simple, and clean. However, you can always
    | adjust the `Metrics` and `Insights` below in this configuration file.
    |
    | Supported: "default", "laravel", "symfony", "magento2", "drupal"
    |
    */

    'preset' => 'laravel',

    /*
    |--------------------------------------------------------------------------
    | IDE
    |--------------------------------------------------------------------------
    |
    | This options allow to add hyperlinks in your terminal to quickly open
    | files in your favorite IDE while browsing your PhpInsights report.
    |
    | Supported: "textmate", "macvim", "emacs", "sublime", "phpstorm",
    | "atom", "vscode".
    |
    | If you have another IDE that is not in this list but which provide an
    | url-handler, you could fill this config with a pattern like this:
    |
    | myide://open?url=file://%f&line=%l
    |
    */

    // MacOS only
    // @see https://phpinsights.com/ide.html#prerequisite-troubleshooting
    'ide' => 'phpstorm',

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may adjust all the various `Insights` that will be used by PHP
    | Insights. You can either add, remove or configure `Insights`. Keep in
    | mind that all added `Insights` must belong to a specific `Metric`.
    |
    */

    'exclude' => [
    ],

    'add' => [
        \NunoMaduro\PhpInsights\Domain\Metrics\Architecture\Classes::class => [
            \NunoMaduro\PhpInsights\Domain\Insights\ForbiddenFinalClasses::class,
            \PhpCsFixer\Fixer\CastNotation\ModernizeTypesCastingFixer::class, // intval() -> (int)
        ],
    ],

    'remove' => [
        \SlevomatCodingStandard\Sniffs\Namespaces\AlphabeticallySortedUsesSniff::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\DeclareStrictTypesSniff::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\DisallowMixedTypeHintSniff::class,
        \NunoMaduro\PhpInsights\Domain\Insights\ForbiddenDefineFunctions::class,
        \NunoMaduro\PhpInsights\Domain\Insights\ForbiddenNormalClasses::class,
        \NunoMaduro\PhpInsights\Domain\Insights\ForbiddenTraits::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\ParameterTypeHintSniff::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff::class,
        \SlevomatCodingStandard\Sniffs\Commenting\UselessFunctionDocCommentSniff::class,
        \SlevomatCodingStandard\Sniffs\Classes\ModernClassNameReferenceSniff::class,
        \PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer::class,
    ],

    'config' => [
        \PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer::class => [
            'operators' => [
                '=>' => 'align',
                '===' => 'align_single_space_minimal',
            ],
        ],
        \SlevomatCodingStandard\Sniffs\Files\LineLengthSniff::class => [
            'lineLimit' => 120,
            'absoluteLineLimit' => 120,
            'ignoreComments' => false,
        ],
        \SlevomatCodingStandard\Sniffs\Functions\FunctionLengthSniff::class => [
            'maxLinesLength' => 30,
            'exclude' => [],
        ],
        \SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSniff::class => [
            'exclude' => [],
        ],
        \PhpCsFixer\Fixer\FunctionNotation\VoidReturnFixer::class => [],
        \NunoMaduro\PhpInsights\Domain\Insights\CyclomaticComplexityIsHigh::class => [
            'maxComplexity' => 14,
            'exclude' => [],
        ],
        \SlevomatCodingStandard\Sniffs\Functions\UnusedParameterSniff::class => [
            'exclude' => [],
        ],
        \PHP_CodeSniffer\Standards\Generic\Sniffs\Commenting\TodoSniff::class => [
            'exclude' => [
            ],
        ],
        \NunoMaduro\PhpInsights\Domain\Insights\ForbiddenPrivateMethods::class => [
            'title' => 'The usage of private methods is not idiomatic in Laravel.',
        ],
        \PhpCsFixer\Fixer\CastNotation\CastSpacesFixer::class => ['space' => 'none'],
        \PHP_CodeSniffer\Standards\Generic\Sniffs\Formatting\SpaceAfterNotSniff::class => ['spacing' => 0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Requirements
    |--------------------------------------------------------------------------
    |
    | Here you may define a level you want to reach per `Insights` category.
    | When a score is lower than the minimum level defined, then an error
    | code will be returned. This is optional and individually defined.
    |
    */

    'requirements' => [
        'min-quality' => 0,
        'min-complexity' => 0,
        'min-architecture' => 0,
        'min-style' => 0,
        'disable-security-check' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Threads
    |--------------------------------------------------------------------------
    |
    | Here you may adjust how many threads (core) PHPInsights can use to perform
    | the analyse. This is optional, don't provide it and the tool will guess
    | the max core number available. This accept null value or integer > 0.
    |
    */

    'threads' => 4,

    'diff_context' => 0,
];
