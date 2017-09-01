<?php

use Aws\Lambda\LambdaClient;

class WP_Critical_CSS_AWS_Lambda{

    protected $_operations = [];

    /**
     * @var LambdaClient
     */
    protected $_lambda_client = null;

    protected $_file_s3_key = false;


    public function load(){


        if(defined('AWS_LAMBDA_CSS_KEY') && defined('AWS_LAMBDA_CSS_SECRET') && defined('AWS_LAMBDA_CSS_REGION')){
            $this->_lambda_client = new LambdaClient([
                'credentials' => array(
                    'key'    => AWS_LAMBDA_CSS_KEY,
                    'secret' => AWS_LAMBDA_CSS_SECRET,
                ),
                'region' => AWS_LAMBDA_CSS_REGION,
                'version' => '2017-09-01',
            ]);
        }


    }

    /**
     * @return array with css url's
     */

    public static function get_css_files(){
        global $wp_styles;
        $registered_styles = $wp_styles->registered;
        $css = [];
        $handles = apply_filters('critical_css',$css);
            foreach ($handles as $handle){
                if(isset($registered_styles[$handle])){
                    $css = [
                        $registered_styles[$handle]->src
                    ];
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
        $template_name = str_replace($theme_directory,'',$template);
        return $template_name;
    }

    /**
     * @return string link of current page
     */
    private static function get_page_link(){
        $link = home_url($_SERVER['REQUEST_URI']);

        return $link;
    }
    protected function _run_lambda(){
        if(defined('AWS_LAMBDA_CSS_BUCKET')){
            $args = $this->_get_lambda_args();
        }

        $function = $this->_get_lambda_function();
        if(is_object($this->_lambda_client) && isset($args)){
            return $this->_lambda_client->invoke( [
                'FunctionName' => $function,
                'Payload' => json_encode( $args ),
            ] );
        }
        else{
            return false;
        }

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