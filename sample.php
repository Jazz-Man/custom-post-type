<?php

use JazzMan\Post\CustomPostType;

// create a book custom post type
$books = new CustomPostType('book');

// create a genre taxonomy
$books->registerTaxonomy('genre');

// define the columns to appear on the admin edit screen
$books->setColumns([
    'cb' => '<input type="checkbox" />',
    'title' => __('Title'),
    'genre' => __('Genres'),
    'price' => __('Price'),
    'rating' => __('Rating'),
    'date' => __('Date'),
]);

// populate the price column
$books->setPopulateColumns('price', static function ($column, $post): void {
    echo '£'.get_post_meta($post->ID, 'price', true);
});

// populate the ratings column
$books->setPopulateColumns('rating', static function ($column, $post): void {
    echo get_post_meta($post->ID, 'rating', true).'/5';
});

// make rating and price columns sortable
$books->setSortable([
    'price' => ['price', true],
    'rating' => ['rating', true],
]);

// use "pages" icon for post type
$books->setMenuIcon('dashicons-book-alt');
