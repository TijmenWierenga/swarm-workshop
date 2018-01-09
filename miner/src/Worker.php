<?php
namespace DevCoin\Miner;

use Predis\ClientInterface;
use React\EventLoop\LoopInterface;

/**
 * @author Tijmen Wierenga <tijmen.wierenga@devmob.com>
 */
class Worker
{
    /**
     * Mining speed in seconds
     */
    const MINING_SPEED_SLOW = 1;
    const MINING_SPEED_NORMAL = 2;
    const MINING_SPEED_FAST = 3;
    const MINING_SPEED_SUPER_FAST = 10;

    const MINING_SPEEDS = [
        self::MINING_SPEED_SLOW,
        self::MINING_SPEED_NORMAL,
        self::MINING_SPEED_FAST,
        self::MINING_SPEED_SUPER_FAST
    ];

    /**
     * @var LoopInterface
     */
    private $loop;
    /**
     * @var ClientInterface
     */
    private $client;
    /**
     * @var int
     */
    private $miningSpeed;

    /**
     * Worker constructor.
     * @param LoopInterface $loop
     * @param ClientInterface $client
     * @param int $miningSpeed
     */
    public function __construct(
        LoopInterface $loop,
        ClientInterface $client,
        int $miningSpeed
    ) {
        if (! in_array($miningSpeed, self::MINING_SPEEDS)) {
            throw new \RuntimeException("Invalid mining speed was applied");
        }

        $this->loop = $loop;
        $this->configureMiner();
        $this->client = $client;
        $this->miningSpeed = $miningSpeed;
    }

    public function run(): void
    {
        $this->loop->run();
    }

    private function mine(): void
    {
        $this->client->incr("blocks");
    }

    private function configureMiner(): void
    {
        $this->loop->addPeriodicTimer($this->miningSpeed, function () {
            $this->mine();
        });
    }
}
