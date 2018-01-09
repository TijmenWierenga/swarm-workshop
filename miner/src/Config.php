<?php

namespace DevCoin\Miner;

class Config
{
    /**
     * @var array
     */
    private $store;

    /**
     * Config constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->store = $config;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        if (! array_key_exists($key, $this->store)) {
            throw new \RuntimeException("Unknown config key requested");
        }

        return $this->store[$key];
    }
}
