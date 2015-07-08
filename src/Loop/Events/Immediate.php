<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\ImmediateManagerInterface;

class Immediate implements ImmediateInterface
{
    /**
     * @var \Icicle\Loop\Manager\ImmediateManagerInterface
     */
    private $manager;
    
    /**
     * @var callable
     */
    private $callback;

    /**
     * @var mixed[]|null
     */
    private $args;

    /**
     * @param \Icicle\Loop\Manager\ImmediateManagerInterface $manager
     * @param callable $callback Function called when the interval expires.
     * @param array $args Optional array of arguments to pass the callback function.
     */
    public function __construct(ImmediateManagerInterface $manager, callable $callback, array $args = null)
    {
        $this->manager = $manager;
        $this->callback = $callback;
        $this->args = $args;
    }
    
    /**
     * {@inheritdoc}
     */
    public function call()
    {
        if (empty($this->args)) {
            ($this->callback)();
        } else {
            ($this->callback)(...$this->args);
        }
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
    public function isPending(): bool
    {
        return $this->manager->isPending($this);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->manager->execute($this);
    }

    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        $this->manager->cancel($this);
    }
}
