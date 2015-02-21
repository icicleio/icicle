<?php
namespace Icicle\Process;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Process\Exception\FailedException;
use Icicle\Process\Exception\LogicException;
use Icicle\Process\Exception\RuntimeException;
use Icicle\Process\Exception\TerminatedException;
use Icicle\Promise\Deferred;
use Icicle\Socket\ReadableStream;
use Icicle\Socket\WritableStream;

class Process implements ProcessInterface
{
    const ERROR = -1;
    
    const STDIN = 0;
    const STDOUT = 1;
    const STDERR = 2;
    const EXIT_CODE = 3;
    
    /**
     * @var bool|null
     */
    private static $sigchildEnabled;
    
    /**
     * @var Closure|null
     */
    private static $signalHandler;
    
    /**
     * @var Process[]
     */
    private static $processes = [];
    
    /**
     * @var resource|null
     */
    private $process;
    
    /**
     * @var string
     */
    private $command;
    
    /**
     * @var string|null
     */
    private $cwd;
    
    /**
     * @var array
     */
    private $env = [];
    
    /**
     * @var array
     */
    private $options;
    
    /**
     * @var Stream|null
     */
    private $stdin;
    
    /**
     * @var Stream|null
     */
    private $stdout;
    
    /**
     * @var Stream|null
     */
    private $stderr;
    
    /**
     * @var int
     */
    private $pid = 0;
    
    /**
     * @var int|null
     */
    private $exitCode;
    
    /**
     * @var Deferred
     */
    private $deferred;
    
    /**
     * @param   string $command Command to run.
     * @param   string|null $cwd Working directory or use null to use the working directory of the current PHP process.
     * @param   array|null $env Environment variables or use null to inherit from the current PHP process.
     * @param   array|null $options Options for proc_open().
     */
    public function __construct($command, $cwd = null, array $env = null, array $options = null)
    {
        if (null == self::$signalHandler && !self::isSigchildEnabled()) {
            self::$signalHandler = function ($signo, $pid, $status) {
                if (isset(self::$processes[$pid])) {
                    self::$processes[$pid]->close(pcntl_wexitstatus($status));
                }
            };
            
            Loop::addSignalHandler(SIGCHLD, self::$signalHandler);
        }
        
        $this->setCommand($command);
        $this->setWorkingDirectory($cwd);
        
        if (null !== $env) {
            $this->setEnv($env);
        }
        
        if (null !== $options) {
            $this->setOptions($options);
        }
        
        $this->deferred = new Deferred(function () {
            $this->stop();
        });
    }
    
    /**
     * Stops the process if it is still running.
     */
    public function __destruct()
    {
        $this->terminate(1); // Will only terminate if the process is still running.
    }
    
    /**
     * Resets process values.
     */
    public function __clone()
    {
        $this->process = null;
        $this->deferred = null;
        $this->pid = 0;
        $this->stdin = null;
        $this->stdout = null;
        $this->stderr = null;
        $this->exitCode = null;
        
        $this->deferred = new Deferred();
    }
    
    /**
     * @return  PromiseInterface
     */
    public function run()
    {
        if ($this->deferred->getPromise()->isPending()) {
            $fd = [
                ['pipe', 'r'], // stdin
                ['pipe', 'w'], // stdout
                ['pipe', 'w'], // stderr
            ];
            
            if (self::isSigchildEnabled()) {
                $fd[] = ['pipe', 'w']; // exit code pipe
                $command = sprintf('(%s) 3>/dev/null; code=$?; echo $code >&3; exit $code', $this->command);
            } else {
                $command = $this->command;
            }
            
            $this->process = proc_open($command, $fd, $pipes, $this->cwd, $this->env, $this->options);
            
            if (!is_resource($this->process)) {
                $this->deferred->reject(new RuntimeException('Could not start process.'));
                return $this->deferred->getPromise();
            }
            
            if (!self::isSigchildEnabled()) {
                $status = proc_get_status($this->process);
                $this->pid = $status['pid'];
                self::$processes[$this->pid] = $this;
            }
            
            $this->stdin = new WritableStream($pipes[0]);
            $this->stdout = new ReadableStream($pipes[1]);
            $this->stderr = new ReadableStream($pipes[2]);
            
            if (isset($pipes[3])) {
                $stream = new ReadableStream($pipes[3]);
                $stream->read()->done(
                    function ($exitCode) {
                        $this->close((int) $exitCode);
                    },
                    function (Exception $exception) {
                        $this->close(self::ERROR);
                    }
                );
            }
        }
        
        return $this->deferred->getPromise();
    }
    
    /**
     * Sends a signal to stop the process, first sending SIGTERM then sending SIGKILL after $timeout seconds if the process
     * does not stop. Cannot be used if PHP was compiled with --enable-sigchild.
     *
     * @param   int|float $timeout Number of seconds after sending SIGTERM to wait before sending SIGKILL.
     */
    public function stop($timeout = 10)
    {
        if (self::isSigchildEnabled()) {
            throw new LogicException('PHP was compiled with --enable-sigchild, cannot signal process.');
        }
        
        $this->terminate($timeout);
    }
    
    /**
     * @param   int|float $timeout Number of seconds after sending SIGTERM to wait before sending SIGKILL.
     */
    protected function terminate($timeout)
    {
        if (is_resource($this->process) && !self::isSigchildEnabled()) {
            proc_terminate($this->process, SIGTERM); // Send SIGTERM
            
            $timer = Loop::timer($timeout, function () {
                if (is_resource($this->process)) {
                    proc_terminate($this->process, SIGKILL); // Send SIGKILL
                    $this->close(self::ERROR);
                }
            });
            
            $this->deferred->getPromise()->after(function () use ($timer) {
                $timer->cancel();
            });
        }
    }
    
    /**
     * Sends the given signal to the process. Cannot be used if PHP was compiled with --enable-sigchild
     *
     * @param   int $signo Signal number to send to process. SIGTERM by default.
     */
    public function signal($signo)
    {
        if (self::isSigchildEnabled()) {
            throw new LogicException('PHP was compiled with --enable-sigchild, cannot signal process.');
        }
        
        if (is_resource($this->process)) {
            proc_terminate($this->process, (int) $signo);
        }
    }
    
    /**
     * @param   int $exitCode
     */
    protected function close($exitCode)
    {
        proc_close($this->process);
        $this->process = null;
        
        unset(self::$processes[$this->pid]);
        
        $this->exitCode = $exitCode;
        
        if (0 === $this->exitCode) {
            $this->deferred->resolve($this);
        } else {
            $this->deferred->reject(new FailedException($this));
        }
        
        $this->stdin->close(); // Close only writable pipe. Readable pipes will close themselves when empty or on destruct.
    }
    
    /**
     * Returns the PID of the child process. Value is only meaningful if the process has been started and PHP was not
     * compiled with --enable-sigchild.
     *
     * @return  int
     */
    public function getPid()
    {
        return $this->pid;
    }
    
    /**
     * Returns the command to execute.
     *
     * @return  string The command to execute.
     */
    public function getCommand()
    {
        return $this->command;
    }
    
    /**
     * Sets the command to execute.
     *
     * @param   string $command The command to execute.
     *
     * @return  self
     */
    public function setCommand($command)
    {
        $this->command = (string) $command;
        
        return $this;
    }
    
    /**
     * Gets the current working directory.
     *
     * @return  string|null The current working directory or null if inherited from the current PHP process.
     */
    public function getWorkingDirectory()
    {
        if (null === $this->cwd) {
            return getcwd() ?: null;
        }
        
        return $this->cwd;
    }
    
    /**
     * Sets the current working directory.
     *
     * @param   string|null $cwd The new working directory or null to inherit from the current PHP process.
     *
     * @return  self
     */
    public function setWorkingDirectory($cwd)
    {
        $this->cwd = null === $cwd ? null : (string) $cwd;
        
        return $this;
    }
    
    /**
     * Gets the environment variables array.
     *
     * @return  array Array of environment variables.
     */
    public function getEnv()
    {
        return $this->env;
    }
    
    /**
     * Sets the environment variables.
     *
     * @param   array $env Array of environment variables.
     *
     * @return  self
     */
    public function setEnv(array $env)
    {
        $this->env = [];
        foreach ($env as $key => $value) {
            if (!is_array($value)) { // env cannot accept array values.
                $this->env[(string) $key] = (string) $value;
            }
        }
        
        return $this;
    }
    
    /**
     * Gets the options to pass to proc_open().
     *
     * @return  array Array of options.
     */
    public function getOptions()
    {
        return $this->options;
    }
    
    /**
     * Sets the options to pass to proc_open().
     *
     * @param   array $env Array of options.
     *
     * @return  self
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
        
        return $this;
    }
    
    /**
     * Gets the exit code of the process. The value returned is only meaningful if the process has run and exited.
     *
     * @return  int|null The exit code or null if the process is running or has not been started.
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }
    
    /**
     * Determines if the process is still running.
     *
     * @return  bool
     */
    public function isRunning()
    {
        return is_resource($this->process);
    }
    
    /**
     * Gets the process input stream (STDIN).
     *
     * @return  WritableStream
     *
     * @throws  LogicException Thrown if the process has not been started.
     */
    public function getInputStream()
    {
        if (null === $this->stdin) {
            throw new LogicException('The process has not been started.');
        }
        
        return $this->stdin;
    }
    
    /**
     * Gets the process output stream (STDOUT).
     *
     * @return  ReadableStream
     *
     * @throws  LogicException Thrown if the process has not been started.
     */
    public function getOutputStream()
    {
        if (null === $this->stdout) {
            throw new LogicException('The process has not been started.');
        }
        
        return $this->stdout;
    }
    
    /**
     * Gets the process error stream (STDERR).
     *
     * @return  ReadableStream
     *
     * @throws  LogicException Thrown if the process has not been started.
     */
    public function getErrorStream()
    {
        if (null === $this->stderr) {
            throw new LogicException('The process has not been started.');
        }
        
        return $this->stderr;
    }
    
    /**
     * Determines if PHP was compiled with the '--enable-sigchild' option.
     *
     * @return  bool
     *
     * @see     Symfony\Component\Process\Process::isSigchildEnabled()
     */
    public static function isSigchildEnabled()
    {
        if (null === self::$sigchildEnabled) {
            ob_start();
            phpinfo(INFO_GENERAL);
            self::$sigchildEnabled = false !== strpos(ob_get_clean(), '--enable-sigchild');
        }
        
        return self::$sigchildEnabled;
    }
}
