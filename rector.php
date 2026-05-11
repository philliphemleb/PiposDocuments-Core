<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\SimplifyBoolIdenticalTrueRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        // PHPStan bootstrap files — they intentionally use procedural code
        // and Rector's refactorings would break the loaders.
        __DIR__ . '/tests/console-application.php',
        __DIR__ . '/tests/object-manager.php',
        // Project convention (CLAUDE.md): explicit boolean comparisons
        // like `isset($x) === true`. Rector's codeQuality set wants to
        // simplify these away — we want them kept.
        SimplifyBoolIdenticalTrueRector::class,
        // Doctrine entities have ORM attributes on class properties — promoting
        // them to constructor parameters strips the mapping and breaks hydration.
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . '/src/*/Entity/*',
        ],
    ])
    ->withCache(cacheDirectory: __DIR__ . '/var/.rector-cache')
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
    );
