<?php
namespace Codeception\Extension;

use Carbon\Carbon;
use Codeception\Event\FailEvent;
use Codeception\Event\TestEvent;
use Codeception\Platform\Extension as PlatformExtension;
use Illuminate\Container\Container;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

class CodeceptionMonolog extends PlatformExtension
{
    /** @var Logger */
    private $logger;

    /** @var  Container */
    private $container;

    public static $events = array(
        'test.fail' => 'testFailed',
        'test.error' => 'testError',
        'test.incomplete' => 'testIncomplete',
    );


    /**
     * {@inheritdoc}
     */
    public function _initialize()
    {
        $this->container = new Container();
        $this->logger = new Logger('tests', $this->resolveHandlers());

        parent::_initialize();
    }


    /**
     * resolve single log handler via illuminate DI container
     *
     * @param string $handlerClassName
     * @param array $constructorArgs
     * @return HandlerInterface
     * @throws \Exception
     */
    protected function resolveHandler($handlerClassName, array $constructorArgs = [])
    {
        $handlerClassName = '\\Monolog\\Handler\\' . $handlerClassName;

        return $this->container->build($handlerClassName, $constructorArgs);
    }

    /**
     * resolve log handler instances from config
     *
     * @return HandlerInterface[]
     */
    protected function resolveHandlers()
    {
        $handlers = [];
        if (!empty($this->config['handlers']) && is_array($this->config['handlers'])) {
            foreach ($this->config['handlers'] as $handlerClass => $constructorArgs) {
                // class => NativeMailerHandler
                // config => [ 'from' => 'me', 'to' => 'you', ... ]
                $handlers[] = $this->resolveHandler($handlerClass, $constructorArgs ?: []);
            }
        }

        return $handlers;
    }

    /**
     * @param TestEvent $testEvent
     * @param FailEvent $failEvent
     * @return string
     */
    protected function getFailMessage(TestEvent $testEvent, FailEvent $failEvent)
    {
        $testName = $testEvent->getTest()->getTestFullName($testEvent->getTest());
        return 'Error in Test ' . $testName . ' at ' . (string)Carbon::createFromTimestamp($testEvent->getTime())
        . ' Message: ' . $failEvent->getFail()->getMessage();
    }


    /**
     * executed automatically when test.error event is thrown
     *
     * @param TestEvent $testEvent
     * @param FailEvent $failEvent
     */
    public function testError(TestEvent $testEvent, FailEvent $failEvent)
    {
        $this->logger->error($this->getFailMessage($testEvent, $failEvent));
    }


    /**
     * executed automatically when test.fail event is thrown
     *
     * @param TestEvent $testEvent
     * @param FailEvent $failEvent
     */
    public function testFailed(TestEvent $testEvent, FailEvent $failEvent)
    {
        $this->logger->error($this->getFailMessage($testEvent, $failEvent));
    }


    /**
     * executed automatically when test.incomplete event is thrown
     *
     * @param TestEvent $testEvent
     * @param FailEvent $failEvent
     */
    public function testIncomplete(TestEvent $testEvent, FailEvent $failEvent)
    {
        $this->logger->warning($this->getFailMessage($testEvent, $failEvent));
    }


}