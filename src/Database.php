<?php


namespace juvo\WordPressSecureActions;


class Database
{

    const TABLENAME = "secure_actions";
    private $wpdb;
    private $table;

    /**
     * Database constructor.
     */
    public function __construct() {
        $this->wpdb = $GLOBALS["wpdb"];
        $this->table = $this->wpdb->prefix . self::TABLENAME;
    }


    public static function addTable(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLENAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
          `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
          `password` varchar(255) NOT NULL,
          `name` tinytext NOT NULL,
          `callback` longtext NOT NULL,
          `args` longtext,
          `limit` int NOT NULL,
          `count` int NOT NULL,
          `expiration` BIGINT(20) NOT NULL,
          `created_at` DATETIME NOT NULL,
          `persistent` tinyint(1) DEFAULT 0 NOT NULL,
          PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Creates or updates a secure_action entry. If $id is set this entry will be updated
     *
     * @param string $password
     * @param string $name
     * @param array|string $callback
     * @param array $args
     * @param int $limit
     * @param int $count
     * @param int $expiration
     * @param bool $persistent
     * @return Action|\WP_Error
     */
    public function replaceAction(string $password, string $name, $callback, $args, int $limit, int $count, int $expiration, \DateTimeImmutable $created_at, bool $persistent, $id = null) {

        $value = array(
            'password'   => $password,
            'name'       => $name,
            'callback'   => maybe_serialize($callback),
            'args'       => maybe_serialize($args),
            'limit'      => $limit,
            'count'      => $count,
            'expiration' => $expiration,
            'created_at' => $created_at->format('Y-m-d H:i:s'),
            'persistent' => intval($persistent),
        );

        $format = array(
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%d',
            '%d',
            '%s',
            '%d',
        );

        if (is_integer($id)) {
            // Add ID to values
            $value['id'] = $id;
            // Add ID format to formats
            $format[] = '%d';
        }

        $insert = $this->wpdb->replace(
            $this->table,
            $value,
            $format
        );

        if ($insert == false) {
            return new \WP_Error("error_adding_secure_action", $this->wpdb->last_error);
        }

        return new Action($this->wpdb->insert_id, $password, $name, $callback, $args, $limit, $count, $expiration, $created_at, $persistent);

    }

    /**
     * Updates a single secure_action entry.
     *
     * @param Action $action
     * @return Action|\WP_Error
     */
    public function updateAction(Action $action) {

        // Pass through to replace function, but with id
        $insert = $this->replaceAction(
            $action->getPassword(),
            $action->getName(),
            $action->getCallback(),
            $action->getArgs(),
            $action->getLimit(),
            $action->getCount(),
            $action->getExpiration(),
            $action->getCreatedAt(),
            $action->isPersistent(),
            $action->getId()
        );

        if ($insert == false) {
            return new \WP_Error("error_replacing_secure_action", $this->wpdb->last_error);
        }

        return $action;

    }

    /**
     * Returns a single secure_action entry
     *
     * @param int $id
     * @return Action|\WP_Error
     */
    public function getAction(int $id) {

        $query = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id );
        $result = $this->wpdb->get_row($query);

        if ($result !== null) {
            $result = $this->resultRowToAction($result);
        } else {
            $err = $this->wpdb->last_error ?: "Error while selecting action with ID $id. Maybe it was already deleted.";
            return new \WP_Error("error_getting_secure_action", $err);
        }

        return $result;

    }

    /**
     * Get all secure_action entries
     *
     * @return Action[]|\WP_Error
     */
    public function getAllActions() {

        $result = $this->wpdb->dbh->query("SELECT * FROM {$this->table}");
        if (!$result) {
            return new \WP_Error("error_selecting_all_secure_action", $this->wpdb->dbh->error);
        }

        /* fetch object array */
        while ($row = $result->fetch_object()) {
           yield $this->resultRowToAction($row);
        }
    }

    /**
     * Delete a single secure_action entry from database.
     *
     * @param Action $action
     * @return \WP_Error|bool
     */
    public function deleteAction(Action $action) {

        $this->wpdb->delete(
            $this->table,
            array('ID' => $action->getId()),
            array('%d')
        );

        if (!empty($this->wpdb->last_error)) {
            return new \WP_Error("error_deleting_secure_action", $this->wpdb->last_error);
        }

        return true;

    }

    /**
     * Convert wpdb stdClass output of row to Action
     *
     * @param \stdClass $result
     * @return Action
     */
    private function resultRowToAction(\stdClass $result): Action {
        return new Action(
            intval($result->id),
            $result->password,
            $result->name,
            maybe_unserialize($result->callback),
            maybe_unserialize($result->args),
            intval($result->limit),
            intval($result->count),
            intval($result->expiration),
            new \DateTimeImmutable($result->created_at, wp_timezone()),
            boolval($result->persistent)
        );
    }
}
