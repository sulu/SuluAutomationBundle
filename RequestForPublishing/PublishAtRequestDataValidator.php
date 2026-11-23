<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\RequestForPublishing;

use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * Refuses a request that switches "Publish automatically" on without a date, because nothing would be scheduled.
 * It runs before the listener of the core that stores the request, so a refused request leaves nothing behind.
 */
final class PublishAtRequestDataValidator
{
    public function __invoke(TransitionEvent $event): void
    {
        /** @var mixed $data */
        $data = $event->getContext()[ContentWorkflowInterface::WORKFLOW_TRANSITION_REQUEST_DATA_CONTEXT_KEY] ?? [];
        if (!\is_array($data) || true !== ($data[PublishAtAction::TOGGLE_KEY] ?? null)) {
            return;
        }

        if (null === PublishAtAction::readPublishAt($data)) {
            throw new BadRequestHttpException('Choose the date to publish at, or switch off "Publish automatically".');
        }
    }
}
