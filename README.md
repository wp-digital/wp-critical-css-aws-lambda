## AWS Lambda Critical CSS

### Description

WordPress plugin for generating critical stylesheets for templates via AWS Lambda.

### Install

Clone this repo to `wp-content/plugins/`:

````
cd wp-content/plugins/
git clone git@github.com:innocode-digital/wp-critical-css-aws-lambda.git
````

Activate **AWS Lambda Critical CSS** from Plugins page 
or [WP-CLI](https://make.wordpress.org/cli/handbook/): `wp plugin activate wp-critical-css-aws-lambda`.

Also you could install it as a [Must Use Plugin](https://codex.wordpress.org/Must_Use_Plugins).

Add the following constants to `wp-config.php`:

````
define( 'AWS_LAMBDA_CRITICAL_CSS_KEY', '' );
define( 'AWS_LAMBDA_CRITICAL_CSS_SECRET', '' );
define( 'AWS_LAMBDA_CRITICAL_CSS_REGION', '' ); // e.g. eu-west-1

define( 'AWS_LAMBDA_CRITICAL_CSS_FUNCTION', '' ); // Optional, default value is "wordpress_critical_css-production"
````

Create Lambda function on AWS and upload code. 
Expected default name is **wordpress_critical_css-production** but you may use any other.
You may use the following [AWS Lambda Critical CSS](https://github.com/innocode-digital/aws-lambda-critical-css) 
or create your own.

### Usage

To generate critical CSS from enqueued styles:

````
add_filter( 'aws_lambda_critical_css_styles', function () {
    return [
        // List of enqueued styles. 
        // You need to specify styles which are probably needed for critical CSS.
    ];
} );
````

It's possible to use following hooks for loading inline critical CSS into HTML markup only on first visit:

````
add_filter( 'aws_lambda_critical_css_can_print', function ( $can_print, $key, $hash ) {
    // Fires before rendering critical CSS,
    // pass true or false to render or not
    return true | false;
}, 10, 3 );

add_action( 'aws_lambda_critical_css_printed', function ( $key, $hash ) {
    // Fires after rendering critical CSS
}, 10, 2 );
```` 

Basic example:

````
add_filter( 'aws_lambda_critical_css_can_print', function ( $can_print, $key, $hash ) {
    $cookie_name = "visited_$key";
    
    return ! isset( $_COOKIE[ $cookie_name ] ) || $_COOKIE[ $cookie_name ] != $hash;
}, 10, 3 );

add_action( 'aws_lambda_critical_css_printed', function ( $key, $hash ) {
    $path = '/';

    if ( is_multisite() && !is_subdomain_install() ) {
        $path .= trim( get_blog_details( get_current_blog_id() )->path, '/' );
    }
    
    $cookie_name = "visited_$key";
    
    ?>
    <script>
        document.cookie = '<?= esc_attr( $cookie_name ) ?>=<?= rawurlencode( $hash ) ?>' + '; expires=<?= gmdate( 'r', time() + YEAR_IN_SECONDS ) ?>; path=<?= $path ?>';
    </script>
    <?php
}, 10, 2 );
````

### Caveats

- Relative paths to custom fonts or images in stylesheets should be changed to absolute:

````
add_filter( 'aws_lambda_critical_css_stylesheet', function ( $stylesheet ) {
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