# AWS Lambda Critical CSS

### Description

Generates critical stylesheets for templates via AWS Lambda.
See also [AWS Lambda Critical CSS](https://github.com/innocode-digital/aws-lambda-critical-css).

### Install

- Preferable way is to use [Composer](https://getcomposer.org/):

    ````
    composer require innocode-digital/wp-critical-css-aws-lambda
    ````

  By default, it will be installed as [Must Use Plugin](https://codex.wordpress.org/Must_Use_Plugins).
  It's possible to control with `extra.installer-paths` in `composer.json`.

- Alternate way is to clone this repo to `wp-content/mu-plugins/` or `wp-content/plugins/`:

    ````
    cd wp-content/plugins/
    git clone git@github.com:innocode-digital/wp-critical-css-aws-lambda.git
    cd wp-critical-css-aws-lambda/
    composer install
    ````

If plugin was installed as regular plugin then activate **AWS Lambda Critical CSS** from Plugins page
or [WP-CLI](https://make.wordpress.org/cli/handbook/): `wp plugin activate wp-critical-css-aws-lambda`.

### Usage

Add the following constants to `wp-config.php`:

````
define( 'AWS_LAMBDA_CRITICAL_CSS_KEY', '' );
define( 'AWS_LAMBDA_CRITICAL_CSS_SECRET', '' );
define( 'AWS_LAMBDA_CRITICAL_CSS_REGION', '' ); // e.g. eu-west-1

define( 'AWS_LAMBDA_CRITICAL_CSS_FUNCTION', '' ); // Optional, default value is "critical-css-production-processor"
````

Create serverless function on your favorite service. Expected default name is **critical-css-production-processor**,
but you may use any other. There is a prepared function [AWS Lambda Critical CSS](https://github.com/innocode-digital/aws-lambda-critical-css).

### Usage

To generate critical CSS from enqueued styles:

````
add_filter( 'innocode_critical_css_styles', function () {
    return [
        // List of enqueued styles. 
        // Specify styles which you think are needed for critical CSS.
    ];
} );
````

### Caveats

- Relative paths to custom fonts or images in stylesheets should be changed to absolute:

````
add_filter( 'innocode_critical_css_stylesheet', function ( $stylesheet ) {
    $stylesheet = str_replace(
        '../fonts/',
        get_template_directory_uri() . '/path/to/fonts/',
        $stylesheet
    );
    $stylesheet = str_replace(
        '../images/',
        get_template_directory_uri() . '/path/to/images/',
        $stylesheet
    );

    return $stylesheet;
} );
````

- This plugin is only for generating and rendering critical CSS,
  to defer CSS files you may use [Deferred loading](https://github.com/innocode-digital/wp-deferred-loading).

- If page caching is used then you may want to add variable value from cookie in cache key. E.g. for Batcache it's
possible to do with next code before `advanced-cache.php` file is included:

````
foreach ( array_keys( $_COOKIE ) as $name ) {
    if ( strpos( $name, 'innocode_critical_css_' ) === 0 ) {
        $GLOBALS['batcache']['unique']['innocode_critical_css'] = substr( $name, strlen( 'innocode_critical_css_' ) );
    }
}
````
