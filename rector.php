<?php

declare( strict_types=1 );

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function ( RectorConfig $config ): void {

    $config->sets( [
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        SetList::PRIVATIZATION,
        LevelSetList::UP_TO_PHP_82,
    ] );
    $config->fileExtensions( ['php'] );

    $config->importNames();
    $config->removeUnusedImports();
    $config->importShortClasses( false );

    $config->cacheDirectory( __DIR__.'/cache/rector' );
    $config->phpstanConfig( __DIR__.'/phpstan-rector.neon' );

    $config->paths( [
        __DIR__.'/custom-post-type.php',
        __DIR__.'/src',
    ] );

    $config->skip(
        [
            // or fnmatch
            __DIR__.'/vendor',
            __DIR__.'/.github',
            __DIR__.'/cache',
            __DIR__.'/rector.php',
            __DIR__.'/.php-cs-fixer.php',
            __DIR__.'/sample.php',
        ]
    );
};
