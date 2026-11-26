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
use Sulu\Bundle\AutomationBundle\TaskHandler\PublishTaskUserGuard;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetPublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\TaskHandlerConfiguration;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Snippet\Application\Message\ApplyWorkflowTransitionSnippetMessage;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class SnippetPublishTaskHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $messageBus;
    private SnippetPublishTaskHandler $handler;

    protected function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->handler = new SnippetPublishTaskHandler($this->messageBus->reveal());
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
        $this->assertEquals('sulu_content.task_handler.publish', $configuration->getTitle());
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

        $this->assertEquals(SnippetInterface::class, $resolved['class']);
        $this->assertEquals('test-uuid-123', $resolved['id']);
        $this->assertEquals('en', $resolved['locale']);
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
                    && WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH === $message->getTransitionName();
            }),
            Argument::that(function($stamps) {
                return 1 === \count($stamps) && $stamps[0] instanceof EnableFlushStamp;
            })
        )->shouldBeCalledOnce()->willReturn(new Envelope(new \stdClass()));

        $this->handler->handle($workload);
    }

    public function testHandleAsksTheGuardForTheUserOfTheTask(): void
    {
        $workload = ['id' => 'uuid-1', 'locale' => 'en', 'userId' => 7];

        $guard = $this->prophesize(PublishTaskUserGuard::class);
        $guard->assertCanPublish('snippets', $workload)->shouldBeCalledOnce();
        $this->messageBus->dispatch(Argument::cetera())->shouldBeCalledOnce()->willReturn(new Envelope(new \stdClass()));

        (new SnippetPublishTaskHandler($this->messageBus->reveal(), $guard->reveal()))->handle($workload);
    }

    public function testHandleDoesNotPublishWhenTheGuardRefuses(): void
    {
        $workload = ['id' => 'uuid-1', 'locale' => 'en', 'userId' => 7];

        $guard = $this->prophesize(PublishTaskUserGuard::class);
        $guard->assertCanPublish('snippets', $workload)->willThrow(new AccessDeniedException('no'));
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);

        (new SnippetPublishTaskHandler($this->messageBus->reveal(), $guard->reveal()))->handle($workload);
    }
}
