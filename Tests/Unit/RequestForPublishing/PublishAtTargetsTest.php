<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\RequestForPublishing;

use PHPUnit\Framework\TestCase;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtTargets;
use Sulu\Bundle\AutomationBundle\TaskHandler\ArticlePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetPublishTaskHandler;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Snippet\Domain\Model\SnippetInterface;

class PublishAtTargetsTest extends TestCase
{
    public function testMapsTheResourceKeysToEntityAndHandler(): void
    {
        $targets = new PublishAtTargets();

        $this->assertSame(PageInterface::class, $targets->getEntityClass('pages'));
        $this->assertSame(PagePublishTaskHandler::class, $targets->getHandlerClass('pages'));
        $this->assertSame(ArticleInterface::class, $targets->getEntityClass('articles'));
        $this->assertSame(ArticlePublishTaskHandler::class, $targets->getHandlerClass('articles'));
        $this->assertSame(SnippetInterface::class, $targets->getEntityClass('snippets'));
        $this->assertSame(SnippetPublishTaskHandler::class, $targets->getHandlerClass('snippets'));
    }

    public function testSupportsOnlyContentThatHasAPublishTask(): void
    {
        $targets = new PublishAtTargets();

        $this->assertTrue($targets->supports('pages'));
        $this->assertFalse($targets->supports('products'));
    }

    public function testRefusesAnUnknownResourceKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PublishAtTargets())->getHandlerClass('products');
    }
}
