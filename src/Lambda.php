<?php

namespace Innocode\CriticalCSSAWSLambda;

use Aws\Lambda\LambdaClient;
use Aws\Result;

/**
 * Class Lambda
 * @package Innocode\CriticalCSSAWSLambda
 */
class Lambda
{
    /**
     * @var string
     */
    protected $key;
    /**
     * @var string
     */
    protected $secret;
    /**
     * @var string
     */
    protected $region;
    /**
     * @var string
     */
    protected $function = 'critical-css-production-processor';
    /**
     * @var LambdaClient
     */
    protected $client;

    /**
     * Lambda constructor.
     * @param string $key
     * @param string $secret
     * @param string $region
     */
    public function __construct( string $key, string $secret, string $region )
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->region = $region;
    }

    /**
     * @return string
     */
    public function get_key() : string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function get_secret() : string
    {
        return $this->secret;
    }

    /**
     * @return string
     */
    public function get_region() : string
    {
        return $this->region;
    }

    /**
     * @param string $function
     */
    public function set_function( string $function )
    {
        $this->function = $function;
    }

    /**
     * @return string
     */
    public function get_function() : string
    {
        return $this->function;
    }

    /**
     * @return LambdaClient
     */
    public function get_client() : LambdaClient
    {
        return $this->client;
    }

    public function init()
    {
        $this->client = new LambdaClient( [
            'credentials' => [
                'key'    => $this->get_key(),
                'secret' => $this->get_secret(),
            ],
            'region'      => $this->get_region(),
            'version'     => 'latest',
        ] );
    }

    /**
     * @param array $args
     * @return Result
     */
    public function __invoke( array $args ) : Result
    {
        return $this->get_client()->invoke( [
            'FunctionName'   => $this->get_function(),
            'Payload'        => json_encode( $args ),
            'InvocationType' => 'Event',
        ] );
    }
}
