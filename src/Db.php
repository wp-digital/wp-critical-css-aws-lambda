<?php

namespace Innocode\CriticalCSS;

use Innocode\Version\Version;

class Db
{
    const VERSION = '1.0.0';

    /**
     * @var string
     */
    protected $table;
    /**
     * @var Version
     */
    protected $version;

    public function __construct()
    {
        $this->version = new Version();
    }

    /**
     * @param string $table
     *
     * @return void
     */
    public function set_table( string $table ) : void
    {
        $this->table = $table;
    }

    /**
     * @return string
     */
    public function get_table() : string
    {
        return $this->table;
    }

    /**
     * @return Version
     */
    public function get_version() : Version
    {
        return $this->version;
    }

    /**
     * @return void
     */
    public function init() : void
    {
        $table = $this->get_table();

        $version = $this->get_version();
        $version->set_option( "{$table}_db_version" );

        if ( null === $version() ) {
            $this->create_table();
        }
    }

    /**
     * @return void
     */
    protected function create_table() : void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $query = "CREATE TABLE $wpdb->prefix{$this->get_table()} (
            ID bigint(20) unsigned NOT NULL auto_increment,
            created datetime NOT NULL default '0000-00-00 00:00:00',
            updated datetime NOT NULL default '0000-00-00 00:00:00',
            template varchar(50) NOT NULL default '',
            object varchar(50) NOT NULL default '',
            `key` char(32) NOT NULL default '',
            stylesheet longtext,
            version char(32) NOT NULL default '',
            PRIMARY KEY (ID),
            UNIQUE KEY template_object_key (template,object,`key`),
            KEY template_object (template,object)
        ) $charset_collate;\n";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $query );

        $this->get_version()->update( static::VERSION );
    }

    /**
     * @param string $stylesheet
     * @param string $version
     * @param string $template
     * @param string $object
     * @param string $key
     *
     * @return int
     */
    public function create_entry(
        string $stylesheet,
        string $version,
        string $template,
        string $object,
        string $key
    ) : int
    {
        global $wpdb;

        do_action( 'innocode_critical_css_create_entry', $stylesheet, $version, $template, $object, $key );

        $now = current_time( 'mysql' );
        $created = (bool) $wpdb->insert(
            $wpdb->prefix . $this->get_table(),
            [
                'created'    => $now,
                'updated'    => $now,
                'template'   => $template,
                'object'     => $object,
                'key'        => $key,
                'stylesheet' => $stylesheet,
                'version'    => $version,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( $created ) {
            wp_cache_delete( "$template:$object:$key", 'innocode_critical_css' );

            do_action( 'innocode_critical_css_created_entry', $stylesheet, $version, $template, $object, $key );
        }

        return $wpdb->insert_id;
    }

    /**
     * @param string $template
     * @param string $object
     * @param string $key
     *
     * @return Entry|null
     */
    public function get_entry( string $template, string $object, string $key ) : ?Entry
    {
        global $wpdb;

        $cache_key = "$template:$object:$key";

        if ( false !== ( $data = wp_cache_get( $cache_key, 'innocode_critical_css' ) ) ) {
            return new Entry( $data );
        }

        $query = $wpdb->prepare(
            "SELECT * FROM $wpdb->prefix{$this->get_table()} WHERE template = %s AND object = %s AND `key` = %s",
            $template,
            $object,
            $key
        );
        $data = $wpdb->get_row( $query, ARRAY_A );

        if ( null === $data ) {
            return null;
        }

        wp_cache_set( $cache_key, $data, 'innocode_critical_css' );

        return new Entry( $data );
    }

    /**
     * @param string $stylesheet
     * @param string $version
     * @param string $template
     * @param string $object
     * @param string $key
     *
     * @return bool
     */
    public function update_entry(
        string $stylesheet,
        string $version,
        string $template,
        string $object,
        string $key
    ) : bool
    {
        global $wpdb;

        do_action( 'innocode_critical_css_update_entry', $stylesheet, $version, $template, $object, $key );

        $updated = (bool) $wpdb->update(
            $wpdb->prefix . $this->get_table(),
            [
                'updated'    => current_time( 'mysql' ),
                'stylesheet' => $stylesheet,
                'version'    => $version,
            ],
            [ 'template' => $template, 'object' => $object, 'key' => $key ],
            [' %s', '%s', '%s' ],
            [ '%s', '%s' ]
        );

        if ( $updated ) {
            wp_cache_delete( "$template:$object:$key", 'innocode_critical_css' );

            do_action( 'innocode_critical_css_updated_entry', $stylesheet, $version, $template, $object, $key );
        }

        return $updated;
    }

    /**
     * @param string $template
     * @param string $object
     *
     * @return bool
     */
    public function delete_entries( string $template, string $object ) : bool
    {
        global $wpdb;

        do_action( 'innocode_critical_css_delete_entries', $template, $object );

        $deleted = (bool) $wpdb->delete(
            $wpdb->prefix . $this->get_table(),
            [ 'template' => $template, 'object' => $object ],
            [ '%s', '%s' ]
        );

        if ( $deleted ) {
            do_action( 'innocode_critical_css_deleted_entries', $template, $object );
        }

        return $deleted;
    }

    /**
     * @param string $stylesheet
     * @param string $version
     * @param string $template
     * @param string $object
     * @param string $key
     *
     * @return bool|int
     */
    public function save_entry( string $stylesheet, string $version, string $template, string $object, string $key )
    {
        return null !== $this->get_entry( $template, $object, $key )
            ? $this->update_entry( $stylesheet, $version, $template, $object, $key )
            : $this->create_entry( $stylesheet, $version, $template, $object, $key );
    }

    /**
     * @param string $template
     * @param string $object
     * @param string $key
     *
     * @return bool|int
     */
    public function clear_entry( string $template, string $object, string $key )
    {
        return $this->save_entry( '', '', $template, $object, $key );
    }

    /**
     * @return void
     */
    public function drop_table() : void
    {
        global $wpdb;

        $wpdb->query( "DROP TABLE IF EXISTS $wpdb->prefix{$this->get_table()}" );

        $this->get_version()->delete();
    }
}
