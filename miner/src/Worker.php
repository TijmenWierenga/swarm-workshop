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
    const MINING_SPEED = 1;

    /**
     * @var LoopInterface
     */
    private $loop;
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * Worker constructor.
     * @param LoopInterface $loop
     * @param ClientInterface $client
     */
    public function __construct(LoopInterface $loop, ClientInterface $client)
    {
        $this->loop = $loop;
        $this->configureMiner();
        $this->client = $client;
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
        $this->loop->addPeriodicTimer(self::MINING_SPEED, function () {
            $this->mine();
        });
    }
}
