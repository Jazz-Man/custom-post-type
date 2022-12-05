<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $config): void {
    // here we can define, what sets of rules will be applied
    // tip: use "SetList" class to autocomplete sets

    $config->import(SetList::CODE_QUALITY);
    $config->import(SetList::PHP_74);
    $config->import(SetList::TYPE_DECLARATION);
    $config->import(SetList::TYPE_DECLARATION_STRICT);
    $config->import(SetList::EARLY_RETURN);
    $config->import(SetList::NAMING);
    $config->import(SetList::CODING_STYLE);
    $config->import(SetList::DEAD_CODE);
    $config->import(LevelSetList::UP_TO_PHP_74);
    $config->fileExtensions(['php']);
    $config->phpVersion(PhpVersion::PHP_74);
    $config->importNames();
    $config->importShortClasses(false);
    $config->parallel();
    $config->cacheDirectory(__DIR__.'/cache/rector');
    $config->paths([
        __DIR__,
    ]);

    $config->skip(
        [
            // or fnmatch
            __DIR__.'/vendor',
            __DIR__.'/.github',
            __DIR__.'/cache',
            __DIR__.'/rector.php',
            __DIR__.'/.php-cs-fixer.php',
        ]
    );
};
