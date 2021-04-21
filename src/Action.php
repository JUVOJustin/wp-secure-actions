<?php


namespace WordPressSecureActions;


class Action
{

    private $post;

    /**
     * @var array|string Callback Action to execution whenever action is triggered
     */
    private $callback;

    /**
     * @var array The parameters to be passed to the callback, as an indexed array.
     */
    private $args;

    /**
     * @var int Limits how often the action can be executed
     */
    private $limit;

    /**
     * @var int stores how often the action was executed
     */
    private $count;

    /**
     * @var int expiration intervall in seconds
     */
    private $expiration;

    /**
     * @var bool determines if action should be deleted when expired or limit reached
     */
    private $persistent;

    /**
     * Action constructor.
     * @param \WP_Post $post
     */
    public function __construct(\WP_Post $post) {

        $this->post = $post;

        $meta = get_post_meta($post->ID);

        $this->callback = maybe_unserialize($meta["callback"][0]);
        $this->args = maybe_unserialize($meta["args"][0]);
        $this->limit = $meta["limit"][0];
        $this->count = $meta["count"][0];
        $this->expiration = $meta["expiration"][0];
        $this->persistent = boolval($meta["persistent"][0]);
    }

    /**
     * @return bool
     */
    public function isPersistent(): bool {
        return $this->persistent;
    }

    /**
     * @return array
     */
    public function getArgs(): array {
        return $this->args;
    }

    /**
     * @return array
     */
    public function getCallback() {
        return $this->callback;
    }

    /**
     * @return bool
     */
    public function isLimitReached(): bool {

        if ($this->limit === -1) {
            return false;
        }

        if ($this->getCount() >= $this->getLimit()) {
            return true;
        }
        return false;
    }

    /**
     * @return int
     */
    public function getCount(): int {
        return $this->count;
    }

    /**
     * @param int $count
     * @return bool|int
     */
    public function setCount(int $count) {
        $this->count = $count;
        return update_post_meta($this->post->ID, "count", $this->count);
    }

    /**
     * @return int
     */
    public function getLimit(): int {
        return $this->limit;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isExpired(): bool {

        if ($this->expiration === -1) {
            return false;
        }

        $date = new \DateTimeImmutable($this->post->post_date, wp_timezone());
        $expiration = new \DateInterval('PT' . $this->getExpiration() . 'S');
        if (new \DateTimeImmutable("now", wp_timezone()) > $date->add($expiration)) {
            return true;
        }
        return false;
    }

    /**
     * @return int
     */
    public function getExpiration(): int {
        return $this->expiration;
    }


}