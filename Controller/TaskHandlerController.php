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
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides simple-api for task-handler.
 *
 * @RouteResource("task-handler")
 */
class TaskHandlerController extends Controller
{
    use RequestParametersTrait;

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function getAction(Request $request)
    {
        $handlerFactory = $this->get('task.handler.factory');
        $entityClass = $this->getRequestParameter($request, 'entity-class', true);

        $handlers = [];
        foreach ($handlerFactory->getHandlers() as $handler) {
            if ($handler instanceof AutomationTaskHandlerInterface && $handler->supports($entityClass)) {
                $configuration = $handler->getConfiguration();
                $handlers[] = ['id' => get_class($handler), 'title' => $configuration->getTitle()];
            }
        }

        return new JsonResponse(['_embedded' => ['handlers' => $handlers]]);
    }
}
