<?php

namespace Flasher\Prime\Notification;

use Flasher\Prime\Envelope;
use Flasher\Prime\Stamp\DelayStamp;
use Flasher\Prime\Stamp\HandlerStamp;
use Flasher\Prime\Stamp\HopsStamp;
use Flasher\Prime\Stamp\PriorityStamp;
use Flasher\Prime\Stamp\StampInterface;
use Flasher\Prime\Stamp\TitleStamp;
use Flasher\Prime\Storage\StorageManagerInterface;

class NotificationBuilder implements NotificationBuilderInterface
{
    /**
     * @var Envelope
     */
    protected $envelope;

    /**
     * @var StorageManagerInterface
     */
    protected $storageManager;

    /** @var array<string, object | callable>  */
    protected static $macros = array();

    /**
     * @param string                  $handler
     */
    public function __construct(StorageManagerInterface $storageManager, NotificationInterface $notification, $handler)
    {
        $this->storageManager = $storageManager;
        $this->envelope = Envelope::wrap($notification);
        $this->handler($handler);
    }

    /**
     * @param string $message
     *
     * @return Envelope
     */
    public function addSuccess($message, array $options = array())
    {
        return $this->addFlash(NotificationInterface::TYPE_SUCCESS, $message, $options);
    }

    /**
     * @param string $message
     *
     * @return Envelope
     */
    public function addError($message, array $options = array())
    {
        return $this->addFlash(NotificationInterface::TYPE_ERROR, $message, $options);
    }

    /**
     * @param string $message
     *
     * @return Envelope
     */
    public function addWarning($message, array $options = array())
    {
        return $this->addFlash(NotificationInterface::TYPE_WARNING, $message, $options);
    }

    /**
     * @param string $message
     *
     * @return Envelope
     */
    public function addInfo($message, array $options = array())
    {
        return $this->addFlash(NotificationInterface::TYPE_INFO, $message, $options);
    }

    public function addFlash($type, $message = null, array $options = array())
    {
        if ($type instanceof NotificationInterface) {
            $this->envelope = Envelope::wrap($type);
            $type = $this->envelope->getType();
        }

        $this->type($type, $message, $options);

        return $this->flash();
    }

    public function flash(array $stamps = array())
    {
        if (!empty($stamps)) {
            $this->with($stamps);
        }

        $this->storageManager->add($this->getEnvelope());

        return $this->getEnvelope();
    }

    public function type($type, $message = null, array $options = array())
    {
        $this->envelope->setType($type);

        if (null !== $message) {
            $this->message($message);
        }

        if (array() !== $options) {
            $this->options($options, false);
        }

        return $this;
    }

    public function message($message)
    {
        $this->envelope->setMessage(addslashes($message));

        return $this;
    }

    public function options(array $options, $merge = true)
    {
        if (true === $merge) {
            $options = array_merge($this->envelope->getOptions(), $options);
        }

        $this->envelope->setOptions($options);

        return $this;
    }

    public function option($name, $value)
    {
        $this->envelope->setOption($name, $value);

        return $this;
    }

    public function success($message = null, array $options = array())
    {
        return $this->type(NotificationInterface::TYPE_SUCCESS, $message, $options);
    }

    public function error($message = null, array $options = array())
    {
        return $this->type(NotificationInterface::TYPE_ERROR, $message, $options);
    }

    public function info($message = null, array $options = array())
    {
        return $this->type(NotificationInterface::TYPE_INFO, $message, $options);
    }

    public function warning($message = null, array $options = array())
    {
        return $this->type(NotificationInterface::TYPE_WARNING, $message, $options);
    }

    public function title($title)
    {
        $this->envelope->withStamp(new TitleStamp($title));

        return $this;
    }

    public function priority($priority)
    {
        $this->envelope->withStamp(new PriorityStamp($priority));

        return $this;
    }

    public function hops($amount)
    {
        $this->envelope->withStamp(new HopsStamp($amount));

        return $this;
    }

    public function keep()
    {
        $hopsStamp = $this->envelope->get('Flasher\Prime\Stamp\HopsStamp');
        $amount = $hopsStamp instanceof HopsStamp ? $hopsStamp->getAmount() : 1;

        $this->envelope->withStamp(new HopsStamp($amount + 1));

        return $this;
    }

    public function delay($delay)
    {
        $this->envelope->withStamp(new DelayStamp($delay));

        return $this;
    }

    public function now()
    {
        return $this->delay(0);
    }

    public function with(array $stamps = array())
    {
        $this->envelope->with($stamps);

        return $this;
    }

    public function withStamp(StampInterface $stamp)
    {
        $this->envelope->withStamp($stamp);

        return $this;
    }

    public function getEnvelope()
    {
        return $this->envelope;
    }

    public function handler($handler)
    {
        $this->envelope->withStamp(new HandlerStamp($handler));

        return $this;
    }

    /**
     * @param string $name
     * @param object|callable $macro
     */
    public static function macro($name, $macro)
    {
        static::$macros[$name] = $macro;
    }

    /**
     * @param object $mixin
     * @param bool $replace
     */
    public static function mixin($mixin, $replace = true)
    {
        $reflection = new \ReflectionClass($mixin);
        $methods = $reflection->getMethods(
            \ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            if ($replace || ! static::hasMacro($method->name)) {
                $method->setAccessible(true);
                static::macro($method->name, $method->invoke($mixin));
            }
        }
    }

    /**
     * @param string $name
     */
    public static function hasMacro($name)
    {
        return isset(static::$macros[$name]);
    }

    /**
     * @param string $method
     * @param array $parameters
     */
    public static function __callStatic($method, $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new \BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', get_called_class(), $method
            ));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof \Closure) {
            return call_user_func_array(\Closure::bind($macro, null, get_called_class()), $parameters);
        }

        return call_user_func_array($macro, $parameters);
    }

    /**
     * @param string $method
     * @param array $parameters
     */
    public function __call($method, $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new \BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', get_called_class(), $method
            ));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof \Closure) {
            return call_user_func_array($macro->bindTo($this, get_called_class()), $parameters);
        }

        return call_user_func_array($macro, $parameters);
    }
}
