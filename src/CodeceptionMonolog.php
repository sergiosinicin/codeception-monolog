<?php
namespace Codeception\Extension;

use Codeception\Event\FailEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Platform\Extension as PlatformExtension;
use Codeception\Scenario;
use Codeception\Step;
use Codeception\TestCase\Interfaces\ScenarioDriven;
use Illuminate\Container\Container;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

/**
 * Monolog Extension for Codeception
 *
 * Class CodeceptionMonolog
 * @package Codeception\Extension
 */
class CodeceptionMonolog extends PlatformExtension
{
    /** @var Logger */
    private $logger;

    /** @var  Container */
    private $container;

    public static $events = array(
        Events::TEST_FAIL => 'testFailed',
        Events::TEST_ERROR => 'testError',
        Events::TEST_INCOMPLETE => 'testIncomplete',
    );

    /** @var  string format for log message (sprintf) */
    private $message;

    /** @var string default message used to push to logs */
    private $defaultMessage = "Test %s failed. \nMessage: %s.\nTrace: %s";


    /**
     * {@inheritdoc}
     */
    public function _initialize()
    {
        $this->container = new Container();
        $this->logger = new Logger('tests', $this->resolveHandlers());
        $this->message = !empty($this->config['message'])
            ? $this->config['message']
            : $this->defaultMessage;

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
    protected function getFailMessage(FailEvent $failEvent)
    {
        $test = $failEvent->getTest();
        $failMessage = $failEvent->getFail()->getMessage();

        if ($test instanceof ScenarioDriven) {
            $failingStep = $this->getFailingStep($test->getScenario());

            $failMessage =  'Tried to ' . $test->getFeature()
                . ' but failed when I wanted to ' . $failingStep->getHumanizedAction()
                . ' (' . $failMessage . ')';
        }

        return sprintf(
            $this->message,
            $test->getName(),
            $failMessage,
            $failEvent->getFail()->getTraceAsString()
        );
    }


    /**
     * executed automatically when test.error event is thrown
     *
     * @param TestEvent $testEvent
     * @param FailEvent $failEvent
     */
    public function testError(FailEvent $failEvent)
    {
        $this->logger->error($this->getFailMessage($failEvent));
    }


    /**
     * executed automatically when test.fail event is thrown
     *
     * @param TestEvent $testEvent
     * @param FailEvent $failEvent
     */
    public function testFailed(FailEvent $failEvent)
    {
        $this->logger->error($this->getFailMessage($failEvent));
    }


    /**
     * executed automatically when test.incomplete event is thrown
     *
     * @param TestEvent $testEvent
     * @param FailEvent $failEvent
     */
    public function testIncomplete(FailEvent $failEvent)
    {
        $this->logger->warning($this->getFailMessage($failEvent));
    }

    /**
     * get failing step from a scenario
     *
     * @param $scenario
     * @return Step
     */
    protected function getFailingStep(Scenario $scenario)
    {
        // failed step is last step with executed = true
        $steps = $scenario->getSteps();
        foreach ($steps as $step) {
            /** @var $step Step */
            if ($step->executed == false) {
                return $step;
            }
        }

        return current(array_reverse($steps));
    }

}