<?php


namespace WordPressSecureActions;


class Action
{

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
     * Action constructor.
     * @param array|string $callback
     * @param int $limit
     * @param int $count
     * @param int $expiration
     */
    public function __construct($callback, array $args, int $limit, int $count, int $expiration) {
        $this->callback = $callback;
        $this->args = $args;
        $this->limit = $limit;
        $this->count = $count;
        $this->expiration = $expiration;
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
    public function getCallback(): array {
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
     */
    public function setCount(int $count): void {
        $this->count = $count;
    }

    /**
     * @return int
     */
    public function getLimit(): int {
        return $this->limit;
    }

    /**
     * @param \DateTimeImmutable $date
     * @return bool
     * @throws \Exception
     */
    public function isExpired(\DateTimeImmutable $date): bool {

        if ($this->expiration === -1) {
            return false;
        }

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