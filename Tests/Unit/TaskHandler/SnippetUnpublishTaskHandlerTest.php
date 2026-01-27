<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\TaskHandler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetUnpublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\TaskHandlerConfiguration;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Snippet\Application\Message\ApplyWorkflowTransitionSnippetMessage;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SnippetUnpublishTaskHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $messageBus;
    private SnippetUnpublishTaskHandler $handler;

    protected function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->handler = new SnippetUnpublishTaskHandler($this->messageBus->reveal());
    }

    public function testSupportsSnippetClass(): void
    {
        $this->assertTrue($this->handler->supports(SnippetInterface::class));
        $this->assertFalse($this->handler->supports(\stdClass::class));
    }

    public function testGetConfiguration(): void
    {
        $configuration = $this->handler->getConfiguration();

        $this->assertInstanceOf(TaskHandlerConfiguration::class, $configuration);
        $this->assertSame('sulu_content.task_handler.unpublish', $configuration->getTitle());
    }

    public function testConfigureOptionsResolver(): void
    {
        $optionsResolver = new OptionsResolver();
        $result = $this->handler->configureOptionsResolver($optionsResolver);

        $this->assertSame($optionsResolver, $result);

        $resolved = $optionsResolver->resolve([
            'class' => SnippetInterface::class,
            'id' => 'test-uuid-123',
            'locale' => 'en',
        ]);

        $this->assertSame(SnippetInterface::class, $resolved['class']);
        $this->assertSame('test-uuid-123', $resolved['id']);
        $this->assertSame('en', $resolved['locale']);
    }

    public function testHandle(): void
    {
        $workload = [
            'id' => 'snippet-uuid-123',
            'locale' => 'en',
        ];

        $this->messageBus->dispatch(
            Argument::that(function($message) use ($workload) {
                if (!$message instanceof ApplyWorkflowTransitionSnippetMessage) {
                    return false;
                }

                return $message->getIdentifier() === ['uuid' => $workload['id']]
                    && $message->getLocale() === $workload['locale']
                    && WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH === $message->getTransitionName();
            }),
            Argument::that(function($stamps) {
                return 1 === \count($stamps) && $stamps[0] instanceof EnableFlushStamp;
            }),
        )->shouldBeCalledOnce()->willReturn(new Envelope(new \stdClass()));

        $this->handler->handle($workload);
    }
}
