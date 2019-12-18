# WP Custom Post Type Class

A single class to help you build more advanced custom post types quickly.

## Installation

```sh

composer require jazzman/custom-post-type

require_once ABSPATH.'vendor/autoload.php';

```

## Creating a new Custom Post type

To create the post type simply create a new object

```php

use JazzMan\Post\CustomPostType;

$books = new CustomPostType('book');
```

The optional second parameter is the arguments for the post_type.
see [WordPress codex](http://codex.wordpress.org/Function_Reference/register_post_type#Parameters) for available options.

The Class uses the WordPress defaults where possible.

To override the default options simply pass an array of options as the second parameter. Not all options have to be passed just the ones you want to add/override like so:

```php
$books = new CustomPostType('book', array(
	'supports' => array('title', 'editor', 'thumbnail', 'comments')
));
```

See the [WordPress codex](http://codex.wordpress.org/Function_Reference/register_post_type#Parameters) for all available options.

## Existing Post Types

To work with exisiting post types, simply pass the post type name into the class constructor

```php
$blog = new CustomPostType('post');
```

## Adding Taxonomies

You can add taxonomies easily using the `register_taxonomy()` method like so:

```php
$books->registerTaxonomy('genres');
```

this method accepts two arguments, names and options. The taxonomy name is required and can be string (the taxonomy name), or an array of names following same format as post types:

```php
$books->registerTaxonomy('genres',array(
	'show_ui'       => true,
	'query_var'     => true,
	'rewrite'       => array( 'slug' => 'the_genre' )
));
```

Again options can be passed optionally as an array. see the [WordPress codex](http://codex.wordpress.org/Function_Reference/register_taxonomy#Parameters) for all possible options.

### Existing Taxonomies

You can add exisiting taxonomies to the post type by passing the taxonomy name through the `register_taxonomy` method. You will only need to specify the options for the custom taxonomy **once**, when its first registered.

## Admin Edit Screen

### Filters

When you register a taxonomy it is *automagically* added to the admin edit screen as a filter and a column.

You can define what filters you want to appear by using the `filters()` method:

```php
$books->setFilters(array('genre'));
```

By passing an array of taxonomy names you can choose the filters that appear and the order they appear in. If you pass an empty array, no drop down filters will appear on the admin edit screen.

### Columns

The Class has a number of methods to help you modify the admin columns.
Taxonomies registered with this class are automagically added to the admin edit screen as columns.

You can add your own custom columns to include what ever value you want, for example with our books post type we will add custom fields for a price and rating.


You can define what columns you want to appear on the admin edit screen with the `setColumns()` method by passing an array like so:

```php
$books->setColumns(array(
	'cb' => '<input type="checkbox" />',
	'title' => __('Title'),
	'genre' => __('Genres'),
	'price' => __('Price'),
	'rating' => __('Rating'),
	'date' => __('Date')
));
```

The key defines the name of the column, the value is the label that appears for that column. The following column names are *automagically* populated by the class:

- any taxonomy registered through the object
- `cb` the checkbox for bulk editing
- `title` the post title with the edit link
- `author` the post author
- `post_id` the posts id
- `icon`  the posts thumbnail


#### Populating Columns

You will need to create a function to populate a column that isn't *automagically* populated.

You do so with the `setPopulateColumns()` method like so:

```php
$books->setPopulateColumns('column_name', function($column, $post) {

	// your code goes here…

});
```

so we can populate our price column like so:

```php
$books->setPopulateColumns('price', function($column, $post) {

	echo "£" . get_post_meta($post->ID,'price',true);

});
```

The method will pass two variables into the function:

* `$column` - The column name (not the label)
* `$post` - The current post object

These are passed to help you populate the column appropriately.

#### Sorting Columns

If it makes sense that column should be sortable by ascending/descending you can define custom sortable columns like so:

```php
$books->setSortable(array(
	'column_name' => array('meta_key', true)
));
```

The `true/false` is used to define whether the meta value is a string or integer,
reason being is that if numbers are ordered as a string, numbers such as:

	1, 3, 5, 11, 14, 21, 33

Would be ordered as:

	1, 11, 14, 21, 3, 33, 5

By adding the option true value the values will be sorted as integers, if false or undefined, the class will sort columns as string.

so for our books example you will use:

```php
$books->setSortable([
    'price'  => ['price', true],
    'rating' => ['rating', true],
]);
```

### Menu Icons

#### Dashicons

With WordPress 3.8 comes [dashicons](https://developer.wordpress.org/resource/dashicons/) an icon font you can use with your custom post types. To use simply pass the icon name through the `setMenuIcon()` method like so:

```php
$books->setMenuIcon('dashicons-book-alt');
```

For a full list of icons and the class names to use visit [https://developer.wordpress.org/resource/dashicons/](https://developer.wordpress.org/resource/dashicons/)