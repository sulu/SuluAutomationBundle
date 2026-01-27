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
use Sulu\Article\Application\Message\ApplyWorkflowTransitionArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\ArticleUnpublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\TaskHandlerConfiguration;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArticleUnpublishTaskHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $messageBus;
    private ArticleUnpublishTaskHandler $handler;

    protected function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->handler = new ArticleUnpublishTaskHandler($this->messageBus->reveal());
    }

    public function testSupportsArticleClass(): void
    {
        $this->assertTrue($this->handler->supports(ArticleInterface::class));
        $this->assertFalse($this->handler->supports(\stdClass::class));
    }

    public function testGetConfiguration(): void
    {
        $configuration = $this->handler->getConfiguration();

        $this->assertInstanceOf(TaskHandlerConfiguration::class, $configuration);
        $this->assertEquals('sulu_content.task_handler.unpublish', $configuration->getTitle());
    }

    public function testConfigureOptionsResolver(): void
    {
        $optionsResolver = new OptionsResolver();
        $result = $this->handler->configureOptionsResolver($optionsResolver);

        $this->assertSame($optionsResolver, $result);

        $resolved = $optionsResolver->resolve([
            'class' => ArticleInterface::class,
            'id' => 'test-uuid-123',
            'locale' => 'en',
        ]);

        $this->assertEquals(ArticleInterface::class, $resolved['class']);
        $this->assertEquals('test-uuid-123', $resolved['id']);
        $this->assertEquals('en', $resolved['locale']);
    }

    public function testHandle(): void
    {
        $workload = [
            'id' => 'article-uuid-123',
            'locale' => 'en',
        ];

        $this->messageBus->dispatch(
            Argument::that(function($message) use ($workload) {
                if (!$message instanceof ApplyWorkflowTransitionArticleMessage) {
                    return false;
                }

                return $message->getIdentifier() === ['uuid' => $workload['id']]
                    && $message->getLocale() === $workload['locale']
                    && WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH === $message->getTransitionName();
            }),
            Argument::that(function($stamps) {
                return 1 === \count($stamps) && $stamps[0] instanceof EnableFlushStamp;
            })
        )->shouldBeCalledOnce()->willReturn(new Envelope(new \stdClass()));

        $this->handler->handle($workload);
    }
}
