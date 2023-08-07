<?php

namespace juvo\WordPressSecureActions\DB;

class Schema extends \BerlinDB\Database\Schema
{

    public $columns = [

        'id' => [
            'name'     => 'id',
            'type'     => 'bigint',
            'length'   => '20',
            'unsigned' => true,
            'extra'    => 'auto_increment',
            'primary'  => true,
            'sortable' => true,
        ],

        'password' => [
            'name'       => 'password',
            'type'       => 'varchar',
            'length'     => '255',
            'allow_null' => false,
            'unsigned'   => true,
            'searchable' => true,
            'sortable'   => false,
        ],

        'name' => [
            'name'       => 'name',
            'type'       => 'varchar',
            'length'     => '255',
            'allow_null' => false,
            'searchable' => true,
            'sortable'   => false,
        ],

        'callback' => [
            'name'       => 'callback',
            'type'       => 'longtext',
            'allow_null' => false,
            'searchable' => false,
            'sortable'   => false,
        ],

        'args' => [
            'name'       => 'args',
            'type'       => 'longtext',
            'allow_null' => true,
            'searchable' => false,
            'sortable'   => false,
        ],

        'limit' => [
            'name'       => 'limit',
            'type'       => 'int',
            'allow_null' => false,
            'unsigned'   => true,
            'searchable' => false,
            'sortable'   => false,
        ],

        'count' => [
            'name'       => 'count',
            'type'       => 'int',
            'allow_null' => false,
            'unsigned'   => true,
            'searchable' => false,
            'sortable'   => false,
        ],

        'expiration' => [
            'name'       => 'expiration',
            'type'       => 'bigint',
            'length'     => '20',
            'unsigned'   => true,
            'searchable' => false,
            'sortable'   => false,
            'allow_null' => false,
        ],

        'created_at' => [
            'name'       => 'created_at',
            'type'       => 'datetime',
            'date_query' => true,
            'searchable' => true,
            'sortable'   => true,
        ],

        'persistent' => [
            'name'       => 'persistent',
            'type'       => 'tinyint',
            'length'     => '1',
            'default'    => 0,
            'unsigned'   => true,
            'allow_null' => false,
        ],

    ];

}