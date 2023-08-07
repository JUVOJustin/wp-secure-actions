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
    protected $db_version_key = 'secure_actions_1.0.0';

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
    protected $version = '1.0.0';

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
        `count` int NOT NULL,
        `expiration` BIGINT(20) NOT NULL,
        `created_at` DATETIME NOT NULL,
        `persistent` tinyint(1) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY name (name)
        ";
    }

}