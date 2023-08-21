<?php


namespace juvo\WordPressSecureActions;


use juvo\WordPressSecureActions\DB\Action;
use juvo\WordPressSecureActions\DB\Query;
use juvo\WordPressSecureActions\DB\Table;
use WP_Error;

class Manager
{

    private static $instance = null;
    /**
     * @var Query
     */
    private $query;

    public static function getInstance(): ?Manager
    {

        if (self::$instance == null) {

            // Try to dynamically load action-scheduler
            $position = strpos(__DIR__, '/vendor/');
            if ($position !== false) {
                $result = substr(__DIR__, 0, $position + 8); // +8 to include '/vendor/' in the final result
                $result .= "woocommerce/action-scheduler/action-scheduler.php"; // Appending the new path
                if (file_exists($result)) {
                    require $result;
                }
            }
            
            self::$instance = new Manager();
        }

        return self::$instance;
    }

    private function __construct()
    {

        if (!defined('ABSPATH')) {
            return;
        }

        add_action('init', function() {
            self::init();
        });

        add_action('juvo_secure_actions_cleanup', [$this, "secureActionsCleanup"]);
        add_action('init', array($this, "rewriteAddRewrites"));
        add_filter('query_vars', array($this, "rewriteAddVar"));
        add_action('init', array($this, "catchAction"));

        $this->query = new Query();

        define('SECURE_ACTIONS_LOADED', true);

    }

    public static function init(): void
    {

        new Table();

        // Register cron to cleanup secure actions
        if (!wp_next_scheduled('juvo_secure_actions_cleanup')) {
            wp_schedule_event(strtotime('tomorrow'), 'daily', 'juvo_secure_actions_cleanup');
        }
    }

    public static function deactivate()
    {
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
     * @return WP_Error|string
     * @throws \Exception
     */
    public function addAction(string $name, $callback, array $args = [], int $expiration = -1, int $limit = -1, bool $persistent = false, string $key = "")
    {

        // Generate key if none passed
        if (empty($key)) {
            $key = wp_generate_password(28, false);
        }

        $hash = wp_hash_password( $key );

        $action = [
            'password'   => $hash,
            'name'       => $name,
            'callback'   => maybe_serialize($callback),
            'args'       => maybe_serialize($args),
            'limit'      => $limit,
            'expiration' => $expiration,
            'persistent' => $persistent
        ];

        // Returns fresh action´s id
        $action = $this->query->add_item($action);

        if (!$action) {
            return new WP_Error("error_adding_secure_action", "Secure action could not be created");
        }

        // Get action from db
        $action = $this->query->get_item($action); //@phpstan-ignore-line

        return $action->getId() . ':' . $key; //@phpstan-ignore-line

    }

    /**
     * @param string $key
     * @return WP_Error
     * @throws \Exception
     */
    public function executeAction(string $key)
    {
        $action = $this->getActionDataByKey($key);
        if (!$action || is_wp_error($action)) {
            return $action;
        }

        $key = $this->getActionDataByKey($key, "key");

        if (!wp_check_password($key,$action->getPassword())) {
            return new WP_Error('invalid_key', __('The confirmation key is invalid for this secure action.', 'juvo_secure_actions'));
        }

        // Check if expiration is reached
        if ($action->isExpired()) {
            $this->deleteAction($action);
            return new WP_Error('secure_action_limit', __('The action expired.', 'juvo_secure_actions'));
        }

        // Check if count limit is reached
        if ($action->isLimitReached()) {
            $this->deleteAction($action);
            return new WP_Error('secure_action_limit', __('The actions limit was exceeded.', 'juvo_secure_actions'));
        }

        // Execute callback
        if (!is_callable($action->getCallback())) {
            return new WP_Error('secure_action_callback', __('The actions callback is not callable.', 'juvo_secure_actions'));
        }

        $result = call_user_func_array($action->getCallback(), $action->getArgs());

        // Only increment counter if variable is no error nor false
        if ($result && !is_wp_error($result)) {
            $this->incrementCount($action);
        }

        return $result;

    }

    /**
     * Use with caution: If the password is replaced the old password is invalidated.
     *
     * Replaces the password for an action. Useful if you want to get a working key again at a later point.
     *
     * @param Action $action
     * @param string $key
     * @return string|WP_Error
     */
    public function replacePassword(Action $action, string $key = "") {

        // Generate key if none passed
        if (empty($key)) {
            $key = wp_generate_password(28, false);
        }

        $hash = wp_hash_password( $key );

        // Returns fresh action´s id
        $updated = $this->query->update_item($action->getId(), [
            'password' => $hash
        ]);

        if (!$updated) {
            return new WP_Error("error_updating_secure_action", "Secure action´s hash could not be updated");
        }

        // Get action from db
        $action = $this->query->get_item($action->getId()); //@phpstan-ignore-line

        return $action->getId() . ':' . $key; //@phpstan-ignore-line
    }

    /**
     * Increments Count of action. Should only be called after a successfull execution.
     * If the callback has no return value you might have to call this function manually to make sure the count is incremented
     * even without any return value.
     *
     * @param Action $action
     * @return Action|WP_Error
     */
    public function incrementCount(Action $action)
    {
        $action->setCount($action->getCount() + 1);

        $updated = $this->query->update_item(
            $action->getId(),
            [
                'count' => $action->getCount()
            ]
        );
        if (!$updated) {
            return new WP_Error("error_updating_secure_action", "Secure Action could not be updated");
        }

        return $action;
    }

    /**
     * Get an action by a column name
     *
     * @param string $column
     * @param $name
     * @return Action|WP_Error
     */
    public function getActionBy(string $column, $name)
    {
        $action = $this->query->get_item_by($column, $name);
        if (!$action instanceof Action) {
            return new WP_Error("error_getting_secure_action", "Secure Action could not be found");
        }
        return $action;
    }

    /**
     * Returns the key or the action form the provided key.
     * This function takes care of splitting the concatenated id+key string.
     *
     * @param string $key
     * @param null|string $info
     * @return Action|string|WP_Error
     * @throws \Exception
     */
    public function getActionDataByKey(string $key, ?string $info = null)
    {

        list($id, $key) = explode(':', $key, 2);

        switch ($info) {
            case "key":
                return $key;
            case "id":
                return $id;
        }

        $action = $this->query->get_item(intval($id));
        if (!$action instanceof Action) {
            return new WP_Error("error_getting_secure_action", "Secure Action could not be found");
        }

        return $action;

    }

    public function secureActionsCleanup()
    {

        $query = new Query([
            'number' => -1
        ]);
        foreach ($query->items as $action) {

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
     * @param mixed|Action $action
     * @return bool|Action|WP_Error
     * @throws \Exception
     */
    public function deleteAction($action)
    {

        if (is_int($action)) {
            $action = $this->query->get_item($action);
        }

        if (!$action instanceof Action) {
            if (is_wp_error($action)) {
                return $action;
            }
            return new WP_Error('secure_action_delete', __('No valid action id or Action instance provided.'));
        }

        if (apply_filters('juvo_secure_actions_delete', $action->isPersistent() ? false : true, $action)) {
            return $this->query->delete_item($action->getId());
        }

        return false;

    }

    /**
     * @deprecated General none wp_query related parameter is used
     *
     * Add secure downloads rewrite rule
     */
    public function rewriteAddRewrites(): void
    {
        add_rewrite_rule(
            'sec-action/(.+)[/]?$', // sec-action, with any following downloads
            'index.php?sec-action=$matches[1]',
            'top'
        );
    }

    /**
     * @param array $vars
     * @return array
     * @deprecated General none wp_query related parameter is used
     *
     * Add query var
     *
     */
    public function rewriteAddVar(array $vars): array
    {
        $vars[] = 'sec-action';
        return $vars;
    }

    /**
     * Executes Secure Action by key passed with sec-d var
     *
     * @throws \Exception
     */
    public function catchAction(): void
    {

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
    public function buildActionUrl(string $key, string $url = ""): string
    {

        if (empty($url)) {
            $url = get_site_url();
        }

        $url = add_query_arg(array(
            'sec-action' => base64_encode($key)
        ), $url);

        return $url;
    }

}
