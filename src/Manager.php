<?php


namespace juvo\WordPressSecureActions;


class Manager
{

    private static $instance = null;
    private $database;

    public static function getInstance(): ?Manager {
        if (self::$instance == null)
        {
            self::$instance = new Manager();
        }

        return self::$instance;
    }

    private function __construct() {

        if ( !defined('ABSPATH') ) {
            return;
        }

        // Make sure only loaded once
        if ( class_exists('\WP') && !defined('SECURE_ACTIONS_LOADED') ) {
            self::init();

            add_action('juvo_secure_actions_cleanup', [$this, "secureActionsCleanup"]);
            add_action('init', array($this, "rewriteAddRewrites") );
            add_action('query_vars', array($this, "rewriteAddVar") );
            add_action('parse_query', array($this, "catchAction") );

            $this->database = new Database();

            define('SECURE_ACTIONS_LOADED', true);
        }

    }

    public static function init(): void {

        // Add database
        Database::addTable();

        // Register cron to cleanup secure actions
        if (!wp_next_scheduled('juvo_secure_actions_cleanup')) {
            wp_schedule_event(strtotime('tomorrow'), 'daily', 'juvo_secure_actions_cleanup');
        }

    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'juvo_secure_actions_cleanup' );
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
    public function addAction(string $name, $callback, array $args = [], int $expiration = -1, int $limit = -1, bool $persistent = false, string $key = "") {
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


        $action = $this->database->replaceAction($password, $name, $callback, $args, $limit, 0, $expiration, new \DateTimeImmutable("now", wp_timezone()), $persistent);

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
    public function executeAction(string $key) {
        global $wp_hasher;

        // Split key by :
        list($id, $key) = explode(':', $key, 2);

        $action = $this->getAction(intval($id));
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

        // Check if expiration is reached
        if ($action->isExpired()) {
            $this->database->deleteAction($action);
            return new \WP_Error('secure_action_limit', __('The action expired.'));
        }

        // Check if count limit is reached
        if ($action->isLimitReached()) {
            $this->database->deleteAction($action);
            return new \WP_Error('secure_action_limit', __('The actions limit was exceeded.'));
        }

        // Execute callback
        if (!is_callable($action->getCallback())) {
            return new \WP_Error('secure_action_callback', __('The actions callback is not callable.'));
        }

        $result = call_user_func_array($action->getCallback(), $action->getArgs());

        // Only increment counter if variable is no error nor false
        if ($result && !is_wp_error($result)) {
            // Increment count
            $action->setCount($action->getCount() + 1);
            $this->database->updateAction($action);
        }

        return $result;

    }

    /**
     * @param int $id
     * @return Action|\WP_Error
     */
    public function getAction(int $id) {
        // Get action
        return $this->database->getAction(intval($id));
    }

    public function secureActionsCleanup() {

        foreach ($this->database->getAllActions() as $action) {

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
                $this->database->deleteAction($action);
            }

        }

    }

    /**
     * @param int|Action $action
     * @return bool|Action|\WP_Error
     */
    public function deleteAction($action) {

        if (is_int($action)) {
            $action = $this->getAction($action);
        }

        if (!$action instanceof Action) {
            if (is_wp_error($action)) {
                return $action;
            }
            return new \WP_Error('secure_action_delete', __('No valid action id or Action instance provided.'));
        }

        return $this->database->deleteAction($action);

    }

    /**
     * Add secure downloads rewrite rule
     */
    public function rewriteAddRewrites(): void {
        add_rewrite_rule(
            'sec-action/(.+)[/]?$', // sec-action, with any following downloads
            'index.php?sec-action=$matches[1]',
            'top'
        );
    }

    /**
     * Add query var
     *
     * @param array $vars
     * @return array
     */
    public function rewriteAddVar(array $vars): array {
        $vars[] = 'sec-action';
        return $vars;
    }

    /**
     * Executes Secure Action by key passed with sec-d var
     *
     * @throws \Exception
     */
    public function catchAction(): void {

        if ($key = get_query_var('sec-action')) {
            (Manager::getInstance())->executeAction(base64_decode($key));
            wp_safe_redirect(get_site_url());
            exit();
        }

    }

    /**
     * @param string $key
     * @return string
     */
    public function buildActionUrl(string $key): string {
        return get_site_url() . "/sec-action/" . base64_encode($key);
    }

}
