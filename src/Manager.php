<?php


namespace juvo\WordPressSecureActions;


class Manager
{

    public static function init() {

        Database::addTable();

        // Register cron to cleanup secure actions
        if (!wp_next_scheduled('secure_actions_cleanup')) {
            wp_schedule_event(strtotime('tomorrow'), 'daily', Manager::secureActionsCleanup());
        }

    }

    /**
     * @param string $name
     * @param array|string $callback
     * @param array $args
     * @param int $expiration
     * @param int $limit
     * @param string $key
     * @param bool $persistent
     * @return \WP_Error|string
     */
    public static function addAction(string $name, $callback, array $args = [], int $expiration = -1, int $limit = -1, bool $persistent = false, string $key = "") {
        global $wp_hasher;

        if (empty($wp_hasher)) {
            require_once ABSPATH . 'wp-includes/class-phpass.php';
            $wp_hasher = new \PasswordHash(8, true);
        }

        // Generate key if none passed
        if (empty($key)) {
            $key = wp_generate_password(28, false);
        }

        $password = $wp_hasher->HashPassword($key);

        $database = new Database();
        $action = $database->replaceAction($password, $name, $callback, $args, $limit, 0, $expiration, new \DateTimeImmutable("now", wp_timezone()), $persistent);

        if (is_wp_error($action)) {
            return $action;
        }

        return $action->getId() . ':' . $key;

    }

    /**
     * @param string $key
     * @return \WP_Error
     * @throws \Exception
     */
    public static function executeAction(string $key) {
        global $wp_hasher;

        // Split key by :
        list($id, $key) = explode(':', $key, 2);

        $action = self::getAction($id);
        if (is_wp_error($action)) {
            return $action;
        }

        // Verify key
        if (empty($wp_hasher)) {
            require_once ABSPATH . 'wp-includes/class-phpass.php';
            $wp_hasher = new \PasswordHash(8, true);
        }
        if (!$wp_hasher->CheckPassword($key, $action->getPassword())) {
            return new \WP_Error('invalid_key', __('The confirmation key is invalid for this secure action.'));
        }

        $database = new Database();

        // Check if expiration is reached
        if ($action->isExpired()) {
            $database->deleteAction($action);
            return new \WP_Error('secure_action_limit', __('The action expired.'));
        }

        // Check if count limit is reached
        if ($action->isLimitReached()) {
            $database->deleteAction($action);
            return new \WP_Error('secure_action_limit', __('The actions limit was exceeded.'));
        }

        // Execute callback
        if (!is_callable($action->getCallback())) {
            return new \WP_Error('secure_action_callback', __('The actions callback is not callable.'));
        }

        // Increment count
        $action->setCount($action->getCount() + 1);
        $database->updateAction($action);

        return call_user_func_array($action->getCallback(), $action->getArgs());
    }

    /**
     * @param int $id
     * @return Action|\WP_Error
     */
    public static function getAction(int $id) {
        // Get action
        $database = new Database();
        return $database->getAction(intval($id));
    }

    public static function secureActionsCleanup() {

        $database = new Database();

        foreach ($database->getAllActions() as $action) {

            $delete = false;

            // Check if expiration is reached
            // todo implement creation date
            if ($action->isExpired()) {
                $delete = true;
            }

            // Check if count limit is reached
            if ($action->isLimitReached()) {
                $delete = true;
            }

            // If is persistent action do not delete
            if ($action->isPersistent()) {
                $delete = false;
            }

            // Apply secure_action_cleanup filters
            $delete = apply_filters("secure_action_cleanup", $delete, $action, $action->getName());

            if ($delete) {
                $database->deleteAction($action);
            }

        }

    }

}
