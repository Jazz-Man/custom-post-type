<?php
/**
 * Plugin Name:         custom-post-type
 * Plugin URI:          https://github.com/Jazz-Man/custom-post-type
 * Description:         A single class to help you build more advanced custom post types quickly.
 * Author:              Vasyl Sokolyk
 * Author URI:          https://www.linkedin.com/in/sokolyk-vasyl
 * Requires at least:   5.2
 * Requires PHP:        7.4
 * License:             MIT
 * Update URI:          https://github.com/Jazz-Man/custom-post-type.
 */

use JazzMan\Post\ArchivePostType;
use JazzMan\Post\PostTypeMessages;
use JazzMan\Post\ReusableBlocks;

if (function_exists('app_autoload_classes')) {
    app_autoload_classes([
        ArchivePostType::class,
        ReusableBlocks::class,
        PostTypeMessages::class,
    ]);
}
