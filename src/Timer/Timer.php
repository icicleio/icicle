<?php
namespace Icicle\Timer;

use Icicle\Core\Base;
use Icicle\Loop\Loop;

class Timer implements TimerInterface
{
    const MIN_INTERVAL = 0.004; // 4ms minimum interval.
    
    /**
     * Callback function called when the interval expires.
     * @var     callable
     */
    private $callback;
    
    /**
     * Number of seconds until the timer is called.
     * @var     float
     */
    private $interval;
    
    /**
     * True if the timer is to repeat, false if the timer should only be called once.
     * @var     bool
     */
    private $periodic;
    
    /**
     * Optional array of arguments to be passed to the callback function.
     * @var     array
     */
    private $args;
    
    /**
     * @param   callable $callback Function called when the interval expires.
     * @param   int|float $interval Number of seconds until the callback function is called.
     * @param   bool $periodic True to repeat the timer, false to only run it once.
     * @param   array $args Optional array of arguments to pass the callback function.
     */
    public function __construct(callable $callback, $interval, $periodic = false, array $args = [])
    {
        $this->callback = $callback;
        $this->interval = (float) $interval;
        $this->periodic = (bool) $periodic;
        $this->args = $args;
        
        if (self::MIN_INTERVAL > $this->interval) {
            $this->interval = self::MIN_INTERVAL;
        }
        
        Loop::getInstance()->addTimer($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function start()
    {
        Loop::getInstance()->addTimer($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isActive()
    {
        return Loop::getInstance()->isTimerActive($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        Loop::getInstance()->cancelTimer($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function unreference()
    {
        Loop::getInstance()->unreferenceTimer($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function reference()
    {
        Loop::getInstance()->referenceTimer($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getInterval()
    {
        return $this->interval;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPeriodic()
    {
        return $this->periodic;
    }
    
    /**
     * {@inheritdoc}
     */
    public function call()
    {
        call_user_func_array($this->callback, $this->args);
    }
    
    /**
     * Defines a callback that is run a single time after $interval seconds.
     *
     * @param   callable $callback
     * @param   float $interval Time until the callback is called in seconds.
     * @param   mixed ...$args Optional arguments that will be passed to the callback function.
     *
     * @return  Timer
     *
     * @api
     */
    public static function once(callable $callback, $interval /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 2);
        
        return new static($callback, $interval, false, $args);
    }
    
    /**
     * Defines a callback that is run every $interval seconds.
     *
     * @param   callable $callback
     * @param   float $interval Time between each call to the callback in seconds.
     * @param   mixed ...$args Optional arguments that will be passed to the callback function.
     *
     * @return  Timer
     *
     * @api
     */
    public static function periodic(callable $callback, $interval /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 2);
        
        return new static($callback, $interval, true, $args);
    }
}
