<?php


namespace WordPressSecureActions;


class Manager
{

    public static function init() {

        // Add post type
        Action_CPT::register_post_type();

        // Register cron to cleanup secure actions
        if (! wp_next_scheduled ( 'secure_actions_cleanup' )) {
            wp_schedule_event(strtotime('tomorrow'), 'daily', [new Manager(), 'secure_actions_cleanup']);
        }

    }

    public function secure_actions_cleanup() {

        $args = array(
            'post_type' => 'secure_action',
            'posts_per_page' => -1
        );
        $query = new \WP_Query( $args );

        if (!$query->have_posts() ) {
            return;
        }

        foreach($query->posts as $post) {

            $delete = false;
            $action = self::decode_action($post->post_content);
            if (!$action || !$action instanceof Action) {
                wp_delete_post( $post->ID, true );
                continue;
            }

            // Check if expiration is reached
            if ( $action->isExpired(new \DateTimeImmutable($post->post_date, wp_timezone())) ) {
                $delete = true;
            }

            // Check if count limit is reached
            if ($action->isLimitReached()) {
                $delete = true;
            }

            // Apply secure_action_cleanup filters
            $delete = apply_filters( "secure_action_cleanup", $delete, $action, $post->post_title );

            if ($delete) {
                wp_delete_post( $post->ID, true );
            }

        }

    }

    /**
     * @param string $name
     * @param array|string $callback
     * @param array $args
     * @param int $expiration
     * @param int $limit
     * @param string $key
     * @return \WP_Error
     */
    public static function add_action(string $name, $callback, array $args = [], int $expiration = -1, int $limit = -1, string $key = "") {

        // Generate key if none passed
        if (empty($key)) {
            global $wp_hasher;

            $key = wp_generate_password(20, false);

            if (empty($wp_hasher)) {
                require_once ABSPATH . WPINC . '/class-phpass.php';
                $wp_hasher = new \PasswordHash(8, true);
            }
        }

        $action = new Action($callback, $args, $limit, 0, $expiration);

        // Initial secure action post
        $id = wp_insert_post(
            array(
                'post_title'   => $name,
                'post_type'    => 'secure_action',
                'post_status'  => 'publish',
                'post_content' => self::encode_action($action),
            ),
            true
        );

        if (is_wp_error($id)) {
            return new \WP_Error("secure_action_error", "Creating secure action post failed");
        }

        // Add post password -> id combined with key for easier identification
        $id = wp_update_post(
            array(
                'ID'            => $id,
                'post_password' => $wp_hasher->HashPassword($key),
            ),
            true
        );

        if (is_wp_error($id)) {
            wp_delete_post( $id, true );
            return new \WP_Error("secure_action_error", "Post Password for id $id could not be set.");
        }

        return base64_encode($id . ':' . $key);

    }

    /**
     * @param string $key
     * @return \WP_Error
     * @throws \Exception
     */
    public static function execute_action(string $key) {
        global $wp_hasher;

        // decode key
        $key = base64_decode($key);

        // Split key by :
        list( $id, $key ) = explode( ':', $key, 2 );

        // Get action post object
        $post = get_post($id);
        if (!$post || !$post instanceof \WP_Post) {
            return \WP_Error("secure_action_error", "Action does not exist");
        }

        // Verify key
        if (empty($wp_hasher)) {
            require_once ABSPATH . WPINC . '/class-phpass.php';
            $wp_hasher = new PasswordHash(8, true);
        }
        if (! $wp_hasher->CheckPassword($key, $post->post_password)) {
            return new \WP_Error('invalid_key', __('The confirmation key is invalid for this secure action.'));
        }

        // Get Action object
        $action = self::decode_action($post->post_content);
        if (!$action || !$action instanceof Action) {
            return \WP_Error("secure_action_error", "Post with id $id does not contain valid action information");
        }

        // Check if expiration is reached
        if ( $action->isExpired(new \DateTimeImmutable($post->post_date, wp_timezone())) ) {
            wp_delete_post( $id, true );
            return new \WP_Error('secure_action_limit', __('The action expired.'));
        }

        // Check if count limit is reached
        if ($action->isLimitReached()) {
            wp_delete_post( $id, true );
            return new \WP_Error('secure_action_limit', __('The actions limit was exceeded.'));
        }

        // Execute callback
        if (!is_callable($action->getCallback())) {
            return new \WP_Error('secure_action_callback', __('The actions callback is not callable.'));
        }
        call_user_func_array($action->getCallback(), $action->getArgs());

        // Increment count
        $action->setCount($action->getCount()+1);

        // Update secure action
        $id = wp_update_post(
            array(
                'ID'            => $id,
                'post_content' => self::encode_action($action),
            ),
            true
        );

        if (is_wp_error($id)) {
            return new \WP_Error("secure_action_update_error", "Action with id $id could not be updated.");
        }
    }

    /**
     * Serializes and encodes transport object so it passes the wordpress filters
     *
     * @param Action $action
     * @return string
     */
    private function encode_action(Action $action): string {
        return base64_encode(serialize($action));
    }

    /**
     * @param string $action
     * @return Action
     */
    private function decode_action(string $action): Action {
        return unserialize(base64_decode($action));
    }

}