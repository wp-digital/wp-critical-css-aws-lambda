<?php

use Aws\Lambda\LambdaClient;

class WP_Critical_CSS_AWS_Lambda{

    protected $_operations = [];

    /**
     * @var LambdaClient
     */
    protected $_lambda_client = null;

    protected $_file_s3_key = false;

    protected function _add_operation( $operation, $params )
    {
        $this->_operations[] = array_merge( [ 'action' => $operation ], $params );
    }

    public function load(){


        $this->_lambda_client = new LambdaClient([
            'credentials' => array(
                'key'    => AWS_LAMBDA_CSS_KEY,
                'secret' => AWS_LAMBDA_CSS_SECRET,
            ),
            'region' => AWS_LAMBDA_CSS_REGION,
            'version' => '2017-09-01',
        ]);

    }

    /**
     * @return array with css url's
     */

    public static function get_css_files(){
        global $wp_styles;
        $registered_styles = $wp_styles->registered;
        $css = [];
        $handles = apply_filters('critical_css');
        if(is_array($handles)){
            foreach ($handles as $handle){
                if(isset($registered_styles[$handle])){
                    $css = [
                        $registered_styles[$handle]->src
                    ];
                }
            }
        }
        return $css;
    }

    /**
     * @return string template name
     */
    public static function get_template_name(){
        global $template;

        $theme_directory = get_template_directory();
        $template_name = substr($template, strlen($theme_directory) - strlen($template) + 1);
        return $template_name;
    }

    /**
     * @return string link of current page
     */
    private static function get_page_link(){
        $link = '';
        if(is_front_page()){
            $link = home_url('/');
        }

        elseif(get_class(get_queried_object()) == "WP_Term"){
            $term = get_queried_object();
            $link = get_term_link($term->term_id,$term->taxonomy);
        }
        elseif(is_home()){
            $link = get_the_permalink(get_option('page_for_posts'));
        }
        else{
            $link = get_page_link();
        }
        return $link;
    }
    protected function _run_lambda(){
        $args = $this->_get_lambda_args();
        $function = $this->_get_lambda_function();
        return $this->_lambda_client->invoke( [
            'FunctionName' => $function,
            'Payload' => json_encode( $args ),
        ] );
    }
    /**
     * @return array with arguments for LambdaClient
     */
    protected static function _get_lambda_args(){
        $defaults = [
            'bucket' => AWS_LAMBDA_CSS_BUCKET,
            'template_name' => self::get_template_name(),
            'css_files' => self::get_css_files(),
            'page_url' => self::get_page_link(),
        ];
        return $defaults;
    }
    protected function _get_lambda_function()
    {
        return defined( 'AWS_LAMBDA_CSS_FUNCTION' ) ? AWS_LAMBDA_CSS_FUNCTION : 'wordpress_critical_css';
    }


}