<?php

namespace juvo\WordPressSecureActions\DB;

use BerlinDB\Database\Row;

class Action extends Row
{

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string User friendly name to identify the action
     */
    protected $name;

    /**
     * @var array|string Callback Action to execution whenever action is triggered
     */
    protected $callback;

    /**
     * @var array|string The parameters to be passed to the callback, as an indexed array.
     */
    protected $args;

    /**
     * @var int Limits how often the action can be executed
     */
    protected $limit;

    /**
     * @var int stores how often the action was executed
     */
    protected $count;

    /**
     * @var int expiration intervall in seconds
     */
    protected $expiration;

    /**
     * @var \DateTimeImmutable|string date when the action was created
     */
    protected $created_at;

    /**
     * @var bool determines if action should be deleted when expired or limit reached
     */
    protected $persistent;

    public function __construct($item)
    {
        parent::__construct($item);

        // This is optional, but recommended. Set the type of each column, and prepare.
        $this->id = (int)$this->id;
        $this->password = (string)$this->password;
        $this->name = (string)$this->name;
        $this->callback = maybe_unserialize($this->callback);
        $this->args = maybe_unserialize($this->args);
        $this->limit = (int)$this->limit;
        $this->count = (int)$this->count;
        $this->expiration = (int)$this->expiration;
        $this->created_at = new \DateTimeImmutable($this->created_at);
        $this->persistent = (bool)$this->persistent;
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
        $args = $this->args; // Dereference
        $args[] = $this;
        return array_values($args);
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