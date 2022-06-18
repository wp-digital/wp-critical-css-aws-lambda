<?php

namespace Innocode\CriticalCSS;

use DateTime;
use Exception;

class Entry
{
    /**
     * @var int
     */
    protected $id = 0;
    /**
     * @var DateTime
     */
    protected $created;
    /**
     * @var DateTime
     */
    protected $updated;
    /**
     * @var string
     */
    protected $template;
    /**
     * @var string
     */
    protected $key = '';
    /**
     * @var string
     */
    protected $stylesheet = '';
    /**
     * @var string
     */
    protected $version = '';

    /**
     * @param array $data
     */
    public function __construct( array $data )
    {
        if ( isset( $data['ID'] ) ) {
            $this->id = (int) $data['ID'];
        }

        if ( isset( $data['created'] ) ) {
            try {
                $this->created = new DateTime( $data['created'], wp_timezone() );
            } catch ( Exception $exception ) {}
        }

        if ( isset( $data['updated'] ) ) {
            try {
                $this->updated = new DateTime( $data['updated'], wp_timezone() );
            } catch ( Exception $exception ) {}
        }

        if ( isset( $data['template'] ) ) {
            $this->template = $data['template'];
        }

        if ( isset( $data['key'] ) ) {
            $this->key = $data['key'];
        }

        if ( isset( $data['stylesheet'] ) ) {
            $this->stylesheet = trim( $data['stylesheet'] );
        }

        if ( isset( $data['version'] ) ) {
            $this->version = $data['version'];
        }
    }

    /**
     * @return int
     */
    public function get_id() : int
    {
        return $this->id;
    }

    /**
     * @return DateTime|null
     */
    public function get_created() : ?DateTime
    {
        return $this->created;
    }

    /**
     * @return DateTime|null
     */
    public function get_updated() : ?DateTime
    {
        return $this->updated;
    }

    /**
     * @return string
     */
    public function get_template() : string
    {
        return $this->template;
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
    public function get_stylesheet() : string
    {
        return $this->stylesheet;
    }

    /**
     * @return string
     */
    public function get_version() : string
    {
        return $this->version;
    }

    /**
     * @return bool
     */
    public function has_version() : bool
    {
        return '' !== $this->get_version();
    }
}
