<?php


namespace juvo\WordPressSecureActions;


class Manager
{

    private static $instance = null;
    private $database;

    public static function getInstance(): ?Manager {
        if (self::$instance == null) {
            self::$instance = new Manager();
        }

        return self::$instance;
    }

    private function __construct() {

        if (!defined('ABSPATH')) {
            return;
        }

        // Make sure only loaded once
        if (class_exists('\WP') && !defined('SECURE_ACTIONS_LOADED')) {
            self::init();

            add_action('juvo_secure_actions_cleanup', [$this, "secureActionsCleanup"]);
            add_action('init', array($this, "rewriteAddRewrites"));
            add_action('query_vars', array($this, "rewriteAddVar"));
            add_action('init', array($this, "catchAction"));

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
        wp_clear_scheduled_hook('juvo_secure_actions_cleanup');
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

        $action = $this->getActionDataByKey($key);
        if (is_wp_error($action)) {
            return $action;
        }

        $key = $this->getActionDataByKey($key, "key");

        // Verify key
        if (empty($wp_hasher)) {
            require_once ABSPATH . 'wp-includes/class-phpass.php';
            $wp_hasher = new \PasswordHash(8, true);
        }
        if (!$wp_hasher->CheckPassword($key, $action->getPassword())) {
            return new \WP_Error('invalid_key', __('The confirmation key is invalid for this secure action.', 'juvo_secure_actions'));
        }

        // Check if expiration is reached
        if ($action->isExpired()) {
            $this->deleteAction($action);
            return new \WP_Error('secure_action_limit', __('The action expired.', 'juvo_secure_actions'));
        }

        // Check if count limit is reached
        if ($action->isLimitReached()) {
            $this->deleteAction($action);
            return new \WP_Error('secure_action_limit', __('The actions limit was exceeded.', 'juvo_secure_actions'));
        }

        // Execute callback
        if (!is_callable($action->getCallback())) {
            return new \WP_Error('secure_action_callback', __('The actions callback is not callable.', 'juvo_secure_actions'));
        }

        $result = call_user_func_array($action->getCallback(), $action->getArgs());

        // Only increment counter if variable is no error nor false
        if ($result && !is_wp_error($result)) {
            $this->incrementCount($action);
        }

        return $result;

    }

    /**
     * Increments Count of action. Should only be called after a successfull exection.
     * If the callback has no return value you might have to call this function manually to make sure the count is incremented
     * even without any return value.
     *
     * @param Action $action
     * @return Action|\WP_Error
     */
    public function incrementCount(Action $action) {
        $action->setCount($action->getCount() + 1);
        return $this->database->updateAction($action);
    }

    /**
     * @param int $id
     * @return Action|\WP_Error
     */
    public function getAction(int $id) {
        // Get action
        return $this->database->getAction(intval($id));
    }

    /**
     * Retuns the key or the action form the provided key.
     * This function takes care of splitting the concatenated id+key string.
     *
     * @param string $key
     * @param null|string $info
     * @return Action|string|\WP_Error
     */
    public function getActionDataByKey(string $key, ?string $info = null) {

        list($id, $key) = explode(':', $key, 2);

        switch ($info) {
            case "key":
                return $key;
            case "id":
                return $id;
        }

        return $this->getAction(intval($id));

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

            // Apply secure_action_cleanup filters
            $delete = apply_filters("secure_action_cleanup", $delete, $action, $action->getName());

            if ($delete) {
                $this->deleteAction($action);
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

        if (apply_filters('juvo_secure_actions_delete', $action->isPersistent() ? false : true, $action)) {
            return $this->database->deleteAction($action);
        }

        return false;

    }

    /**
     * @deprecated General none wp_query related parameter is used
     *
     * Add secure downloads rewrite rule
     */
    public function rewriteAddRewrites(): void {
        add_rewrite_rule(
            'sec-action=(.+)[\/]?$', // sec-action, with any following downloads
            'index.php?sec-action=$matches[1]',
            'top'
        );
    }

    /**
     * @deprecated General none wp_query related parameter is used
     *
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

        if (isset($_GET['sec-action'])) {

            $key = sanitize_text_field($_GET['sec-action']);

            $key = base64_decode($key);
            $result = Manager::getInstance()->executeAction($key);
            $action = $this->getActionDataByKey($key);

            // Get current url
            $url = home_url(esc_url($_SERVER["REQUEST_URI"]));

            // Remove arg to avoid endless loop
            $url = remove_query_arg('sec-action', $url);

            $url = apply_filters('juvo_secure_actions_catch_action_redirect', $url, $action, $result);

            wp_safe_redirect($url);
            exit();
        }

    }

    /**
     * @param string $key
     * @param string $url
     * @return string
     */
    public function buildActionUrl(string $key, string $url = ""): string {

        if (empty($url)) {
            $url = get_site_url();
        }

        $url = add_query_arg(array(
            'sec-action' => base64_encode($key)
        ), $url);

        return $url;
    }

}
