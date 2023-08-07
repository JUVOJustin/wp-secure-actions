<?php

namespace juvo\WordPressSecureActions\DB;

class Query extends \BerlinDB\Database\Query
{
    /**
     * Name of the database table to query.
     *
     * @since 1.0.0
     * @var   string
     */
    protected $table_name = Table::TABLENAME;

    /**
     * String used to alias the database table in MySQL statement.
     *
     * Keep this short, but descriptive. I.E. "tr" for term relationships.
     *
     * This is used to avoid collisions with JOINs.
     *
     * @since 1.0.0
     * @var   string
     */
    protected $table_alias = 'sa';

    /**
     * Name of class used to setup the database schema.
     *
     * @since 1.0.0
     * @var   string
     */
    protected $table_schema = Schema::class;

    /** Item ******************************************************************/

    /**
     * Name for a single item.
     *
     * Use underscores between words. I.E. "term_relationship"
     *
     * This is used to automatically generate action hooks.
     *
     * @since 1.0.0
     * @var   string
     */
    protected $item_name = 'secure_action';

    /**
     * Plural version for a group of items.
     *
     * Use underscores between words. I.E. "term_relationships"
     *
     * This is used to automatically generate action hooks.
     *
     * @since 1.0.0
     * @var   string
     */
    protected $item_name_plural = 'secure_actions';

    /**
     * Name of class used to turn IDs into first-class objects.
     *
     * This is used when looping through return values to guarantee their shape.
     *
     * @since 1.0.0
     * @var   mixed
     */
    protected $item_shape = Action::class;
}