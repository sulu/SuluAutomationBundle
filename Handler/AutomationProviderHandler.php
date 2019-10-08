<?php


namespace Sulu\Bundle\AutomationBundle\Handler;


use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Task\Handler\TaskHandlerFactoryInterface;

class AutomationProviderHandler
{
    /**
     * @var TaskHandlerFactoryInterface
     */
    private $taskHandlerFactory;

    public function __construct(TaskHandlerFactoryInterface $taskHandlerFactory)
    {
        $this->taskHandlerFactory = $taskHandlerFactory;
    }

    public function getHandlerValues()
    {
        $handlers = [];

        foreach ($this->taskHandlerFactory->getHandlers() as $handler) {
            if ($handler instanceof AutomationTaskHandlerInterface) {
                $configuration = $handler->getConfiguration();
                $handlers[] = ['name' => get_class($handler), 'title' => $configuration->getTitle()];
            }
        }

        return $handlers;
    }
}
