<?php

namespace juvo\WordPressSecureActions\DB;

class Table extends \BerlinDB\Database\Table
{

    const TABLENAME = "secure_actions";

    /**
     * Table name, without the global table prefix.
     *
     * @since 1.0.0
     * @var   string
     */
    public $name = self::TABLENAME;

    /**
     * Database version key (saved in _options or _sitemeta)
     *
     * @since 1.0.0
     * @var   string
     */
    protected $db_version_key = 'secure_actions_db_version';

    /**
     * Optional description.
     *
     * @since 1.0.0
     * @var   string
     */
    public $description = 'Secure Actions';

    /**
     * Database version.
     *
     * @since 1.0.0
     * @var   mixed
     */
    protected $version = '1.0.1';

    /**
     * Array of upgrade versions and methods.
     *
     * @access protected
     * @since 2.0.0
     * @var array
     */
    protected $upgrades = array(
        '1.0.1' => 'upgrade_callback_1_0_1',
    );

    /**
     * Setup this database table.
     *
     * @since 1.0.0
     */
    protected function set_schema()
    {
        $this->schema = "
        `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
        `password` varchar(255) NOT NULL,
        `name` varchar(255) NOT NULL,
        `callback` longtext NOT NULL,
        `args` longtext,
        `limit` int NOT NULL,
        `exec_count` int NOT NULL,
        `expiration` BIGINT(20) NOT NULL,
        `created_at` DATETIME NOT NULL,
        `persistent` tinyint(1) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY name (name)
        ";
    }

    /**
     * Renames count column
     *
     * @since 2.0.0
     *
     * @return bool True if upgrade was successful, false otherwise.
     */
    protected function upgrade_callback_1_0_1(): bool
    {

        // Look for column
        $result = $this->column_exists( 'exec_count' );
        if ($result === false) {
            $this->get_db()->query( "ALTER TABLE {$this->table_name} CHANGE count exec_count int NOT NULL;" );
            // Return success/fail
            return $this->is_success( true );
        }
        return false;
    }

}
