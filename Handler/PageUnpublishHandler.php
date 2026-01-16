<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Handler;

use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\TaskHandlerConfiguration;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Task\Executor\RetryTaskHandlerInterface;

/**
 * Provides handler for unpublishing pages.
 */
class PageUnpublishHandler implements AutomationTaskHandlerInterface, RetryTaskHandlerInterface
{
    private MessageBusInterface $messageBus;

    private TranslatorInterface $translator;

    public function __construct(MessageBusInterface $messageBus, TranslatorInterface $translator)
    {
        $this->messageBus = $messageBus;
        $this->translator = $translator;
    }

    public function configureOptionsResolver(OptionsResolver $optionsResolver): OptionsResolver
    {
        return $optionsResolver->setRequired(['id', 'locale'])
            ->setAllowedTypes('id', 'string')
            ->setAllowedTypes('locale', 'string');
    }

    public function supports(string $entityClass): bool
    {
        return \is_a($entityClass, PageInterface::class, true);
    }

    public function getConfiguration(): TaskHandlerConfiguration
    {
        return TaskHandlerConfiguration::create($this->translator->trans('sulu_automation.unpublish', [], 'admin'));
    }

    /**
     * @param array{id: string, locale: string} $workload
     */
    public function handle($workload): void
    {
        $message = new ApplyWorkflowTransitionPageMessage(
            ['uuid' => $workload['id']],
            $workload['locale'],
            'unpublish'
        );

        $this->messageBus->dispatch(new Envelope($message, [new EnableFlushStamp()]));
    }

    public function getMaximumAttempts(): int
    {
        return 3;
    }
}
