<?php


namespace juvo\WordPressSecureActions;


class Action
{

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string User friendly name to identify the action
     */
    private $name;

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
     * @var \DateTimeImmutable date when the action was created
     */
    private $created_at;

    /**
     * @var bool determines if action should be deleted when expired or limit reached
     */
    private $persistent;

    /**
     * Action constructor.
     * @param int $id
     * @param string $password
     * @param string $name
     * @param array|string $callback
     * @param array $args
     * @param int $limit
     * @param int $count
     * @param int $expiration
     * @param \DateTimeImmutable $created_at
     * @param bool $persistent
     */
    public function __construct(int $id, string $password, string $name, $callback, array $args, int $limit, int $count, int $expiration, \DateTimeImmutable $created_at, bool $persistent) {
        $this->id = $id;
        $this->password = $password;
        $this->name = $name;
        $this->callback = $callback;
        $this->args = $args;
        $this->limit = $limit;
        $this->count = $count;
        $this->expiration = $expiration;
        $this->created_at = $created_at;
        $this->persistent = $persistent;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCreatedAt(): \DateTimeImmutable {
        return $this->created_at;
    }

    /**
     * @return string
     */
    public function getPassword(): string {
        return $this->password;
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
     * @return array|string
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
     * @return int
     */
    public function setCount(int $count): int {
        $this->count = $count;
        return $count;
    }

    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
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

        $expiration = new \DateInterval('PT' . $this->getExpiration() . 'S');
        if (new \DateTimeImmutable("now", wp_timezone()) > $this->getCreatedAt()->add($expiration)) {
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
