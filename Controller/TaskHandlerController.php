<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Controller;

use FOS\RestBundle\Controller\Annotations\RouteResource;
use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Component\Rest\RequestParametersTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Task\Handler\TaskHandlerFactoryInterface;

/**
 * Provides simple-api for task-handler.
 *
 * @RouteResource("task-handler")
 */
class TaskHandlerController
{
    use RequestParametersTrait;

    protected $taskHandlerFactory;

    public function __construct(TaskHandlerFactoryInterface $taskHandlerFactory)
    {
        $this->taskHandlerFactory = $taskHandlerFactory;
    }

    public function getAction(Request $request): Response
    {
        $entityClass = $this->getRequestParameter($request, 'entityClass', true);

        $handlers = [];
        foreach ($this->taskHandlerFactory->getHandlers() as $handler) {
            if ($handler instanceof AutomationTaskHandlerInterface && $handler->supports($entityClass)) {
                $configuration = $handler->getConfiguration();
                $handlers[] = ['id' => get_class($handler), 'title' => $configuration->getTitle()];
            }
        }

        return new JsonResponse(['_embedded' => ['handlers' => $handlers]]);
    }
}
