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
 * Refuses "Publish automatically" without a future date, because nothing would be scheduled and nobody would notice.
 * Runs before the core stores the request, so a refused request leaves nothing behind.
 */
final class PublishAtRequestDataValidator
{
    public function __construct(private readonly ?\DateTimeImmutable $now = null)
    {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var mixed $data */
        $data = $event->getContext()[ContentWorkflowInterface::WORKFLOW_TRANSITION_REQUEST_DATA_CONTEXT_KEY] ?? [];
        if (!\is_array($data) || true !== ($data[PublishAtAction::TOGGLE_KEY] ?? null)) {
            return;
        }

        $publishAt = PublishAtAction::readPublishAt($data);
        if (null === $publishAt) {
            throw new BadRequestHttpException('Choose the date to publish at, or switch off "Publish automatically".');
        }

        if ($publishAt <= ($this->now ?? new \DateTimeImmutable())) {
            throw new BadRequestHttpException('Choose a date in the future, or switch off "Publish automatically".');
        }
    }
}
