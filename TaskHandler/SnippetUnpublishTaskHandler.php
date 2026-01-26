<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\TaskHandler;

use Sulu\Article\Domain\Model\Article;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Snippet\Application\Message\ApplyWorkflowTransitionSnippetMessage;
use Sulu\Snippet\Domain\Model\Snippet;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Handles automation tasks for article workflow transitions.
 */
class SnippetUnpublishTaskHandler implements AutomationTaskHandlerInterface
{
    public const TITLE = 'sulu_content.task_handler.unpublish';

    private MessageBusInterface $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function configureOptionsResolver(OptionsResolver $optionsResolver): OptionsResolver
    {
        return $optionsResolver
            ->setRequired(['class', 'id', 'locale'])
            ->setAllowedTypes('class', 'string')
            ->setAllowedTypes('id', 'string')
            ->setAllowedTypes('locale', 'string');
    }

    public function supports(string $entityClass): bool
    {
        return Snippet::class === $entityClass;
    }

    public function getConfiguration(): TaskHandlerConfiguration
    {
        return TaskHandlerConfiguration::create(self::TITLE);
    }

    /**
     * @param array{id: string, locale: string} $workload
     */
    public function handle($workload)
    {
        $this->messageBus->dispatch(
            new ApplyWorkflowTransitionSnippetMessage(
                identifier: ['uuid' => $workload['id']],
                locale: $workload['locale'],
                transitionName: WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH,
            ),
            [new EnableFlushStamp()],
        );
    }
}
