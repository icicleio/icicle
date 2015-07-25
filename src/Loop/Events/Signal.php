<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\SignalManagerInterface;

class Signal implements SignalInterface
{
    /**
     * @var \Icicle\Loop\Manager\SignalManagerInterface
     */
    private $manager;

    /**
     * @var callable
     */
    private $callback;

    /**
     * @var int
     */
    private $signo;

    /**
     * @param \Icicle\Loop\Manager\SignalManagerInterface $manager
     * @param int $signo
     * @param callable $callback
     */
    public function __construct(SignalManagerInterface $manager, int $signo, callable $callback)
    {
        $this->manager = $manager;
        $this->callback = $callback;
        $this->signo = $signo;
    }

    /**
     * {@inheritdoc}
     */
    public function call()
    {
        ($this->callback)($this->signo);
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke()
    {
        $this->call();
    }

    /**
     * {@inheritdoc}
     */
    public function enable()
    {
        $this->manager->enable($this);
    }

    /**
     * {@inheritdoc}
     */
    public function disable()
    {
        $this->manager->disable($this);
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->manager->isEnabled($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getSignal(): int
    {
        return $this->signo;
    }
}
