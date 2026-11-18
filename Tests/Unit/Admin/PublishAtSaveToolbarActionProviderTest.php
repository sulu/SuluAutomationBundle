<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Sulu\Article\Domain\Model\Article;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\AutomationBundle\Admin\PublishAtSaveToolbarActionProvider;
use Sulu\Bundle\AutomationBundle\TaskHandler\ArticlePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetPublishTaskHandler;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Snippet\Domain\Model\Snippet;
use Sulu\Snippet\Domain\Model\SnippetInterface;

class PublishAtSaveToolbarActionProviderTest extends TestCase
{
    /**
     * @dataProvider provideContent
     *
     * @param class-string $contentRichEntityClass
     */
    public function testOffersTheEntryForContentWithAPublishTask(string $contentRichEntityClass, string $entityInterface, string $handlerClass): void
    {
        $toolbarActions = (new PublishAtSaveToolbarActionProvider())->getToolbarActions($contentRichEntityClass);

        $this->assertCount(1, $toolbarActions);
        $this->assertSame('sulu_admin.save_and_create', $toolbarActions[0]->getType());
        $this->assertSame('tasks', $toolbarActions[0]->getOptions()['resourceKey']);
        $this->assertSame(['entityClass' => $entityInterface], $toolbarActions[0]->getOptions()['parameters']);
        $this->assertSame('entityId', $toolbarActions[0]->getOptions()['idParameter']);
        $this->assertSame($handlerClass, $toolbarActions[0]->getOptions()['data']['handlerClass']);
        $this->assertSame('live', $toolbarActions[0]->getOptions()['permission']);
        $this->assertSame('sulu_automation_publish_at', $toolbarActions[0]->getOptions()['formKey']);
        $this->assertSame(
            '(!_permissions || _permissions.live) && !activeWorkflowTransitionRequest',
            $toolbarActions[0]->getOptions()['visible_condition'],
        );
    }

    /**
     * @return iterable<string, array{class-string, class-string, class-string}>
     */
    public static function provideContent(): iterable
    {
        yield 'page' => [Page::class, PageInterface::class, PagePublishTaskHandler::class];
        yield 'article' => [Article::class, ArticleInterface::class, ArticlePublishTaskHandler::class];
        yield 'snippet' => [Snippet::class, SnippetInterface::class, SnippetPublishTaskHandler::class];
    }

    public function testOffersNothingForOtherContent(): void
    {
        $this->assertSame([], (new PublishAtSaveToolbarActionProvider())->getToolbarActions(\stdClass::class));
    }
}
