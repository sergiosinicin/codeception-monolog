<?php

namespace Codeception\Extension;

use Codeception\Event\FailEvent;
use Codeception\Events;
use Codeception\Platform\Extension as PlatformExtension;
use Codeception\Scenario;
use Codeception\Step;
use Codeception\Test\Interfaces\ScenarioDriven;
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
    public static $events = array(
        Events::TEST_FAIL => 'testFailed',
        Events::TEST_ERROR => 'testError',
        Events::TEST_INCOMPLETE => 'testIncomplete',
    );
    /** @var Logger */
    private $logger;
    /** @var  Container */
    private $container;
    /** @var  string format for log message (sprintf) */
    private $message;

    /** @var string default message used to push to logs */
    private $defaultMessage = "Test %s failed. \nMessage: %s.\nTrace: %s";
    /** @var  string if set url - attach report */
    private $reportUrl;
    /** @var  array fields in attachment */
    private $context;

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

        $this->reportUrl = !empty($this->config['report_url'])
            ? $this->config['report_url']
            : '';

        $this->context = array();

        parent::_initialize();
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

        return $this->container->make($handlerClassName, $constructorArgs);
    }

    /**
     * executed automatically when test.error event is thrown
     *
     * @param FailEvent $failEvent
     */
    public function testError(FailEvent $failEvent)
    {
        $this->logger->error($this->getFailMessage($failEvent), $this->getContext());
    }

    /**
     * @param FailEvent $failEvent
     * @return string
     */
    protected function getFailMessage(FailEvent $failEvent)
    {
        $test = $failEvent->getTest();
        $failMessage = $failEvent->getFail()->getMessage();

        if ($test instanceof ScenarioDriven) {
            $failingStep = $this->getFailingStep($test->getScenario());

            $failMessage = 'Tried to ' . $test->getFeature()
                . ' but failed when I wanted to ' . $failingStep->getHumanizedActionWithoutArguments()
                . ' (' . $failMessage . ')';
        }

        if ($this->reportUrl) {
            $reports = $test->getMetadata()->getReports();
            $html = '';
            if (!empty($reports['html']) && file_exists($reports['html'])) {
                $html = $reports['html'];
            } 
            $png = '';
            if (!empty($reports['png']) && file_exists($reports['png'])) {
                $png = $reports['png'];
            }
            
            $this->context['Report   HTML   Screenshot'] = 
                '<' . $this->reportUrl . '/report.html|  Link>'.
                '<' . $this->reportUrl . '/' . basename($html) . '|   Link>'.
                '<' . $this->reportUrl . '/' . basename($png) . '|      Link>'
                ;
        }

        return sprintf(
            $this->message,
            $test->getMetadata()->getName(),
            $failMessage,
            $failEvent->getFail()->getTraceAsString()
        );
    }

    /**
     * get failing step from a scenario
     *
     * @param Scenario $scenario
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

    /**
     * @return array
     */
    private function getContext()
    {
        return $this->context;
    }

    /**
     * executed automatically when test.fail event is thrown
     *
     * @param FailEvent $failEvent
     */
    public function testFailed(FailEvent $failEvent)
    {
        $this->logger->error($this->getFailMessage($failEvent), $this->getContext());
    }

    /**
     * executed automatically when test.incomplete event is thrown
     *
     * @param FailEvent $failEvent
     */
    public function testIncomplete(FailEvent $failEvent)
    {
        $this->logger->warning($this->getFailMessage($failEvent), $this->getContext());
    }
}
