<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Events\Manager\SignalManagerInterface;

class Signal implements SignalInterface
{
    /**
     * @var \Icicle\Loop\Events\Manager\SignalManagerInterface
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
     * @param   \Icicle\Loop\Events\Manager\SignalManagerInterface $manager
     * @param   int $signo
     * @param   callable $callback
     */
    public function __construct(SignalManagerInterface $manager, $signo, callable $callback)
    {
        $this->manager = $manager;
        $this->callback = $callback;
        $this->signo = (int) $signo;
    }

    /**
     * @inheritdoc
     */
    public function call()
    {
        $callback = $this->callback;
        $callback($this->signo);
    }

    /**
     * @inheritdoc
     */
    public function __invoke()
    {
        $this->call();
    }

    /**
     * @inheritdoc
     */
    public function enable()
    {
        $this->manager->enable($this);
    }

    /**
     * @inheritdoc
     */
    public function disable()
    {
        $this->manager->disable($this);
    }

    /**
     * @inheritdoc
     */
    public function isEnabled()
    {
        return $this->manager->isEnabled($this);
    }

    /**
     * @inheritdoc
     */
    public function getSignal()
    {
        return $this->signo;
    }
}
