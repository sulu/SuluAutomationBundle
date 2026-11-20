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
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Article\Domain\Model\Article;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\AdminBundle\Admin\View\SaveAndCreateToolbarAction;
use Sulu\Bundle\AutomationBundle\Admin\AutomationAdmin;
use Sulu\Bundle\AutomationBundle\Admin\PublishAtSaveToolbarActionProvider;
use Sulu\Bundle\AutomationBundle\TaskHandler\ArticlePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageInterface;

class PublishAtSaveToolbarActionProviderTest extends TestCase
{
    use ProphecyTrait;

    private function createProvider(bool $mayAdd = true): PublishAtSaveToolbarActionProvider
    {
        $securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $securityChecker->hasPermission(AutomationAdmin::SECURITY_CONTEXT, 'add')->willReturn($mayAdd);

        return new PublishAtSaveToolbarActionProvider($securityChecker->reveal());
    }

    /**
     * @dataProvider provideContent
     *
     * @param class-string $contentRichEntityClass
     */
    public function testOffersTheEntryForContentWithAPublishTask(string $contentRichEntityClass, string $entityInterface, string $handlerClass): void
    {
        $toolbarActions = $this->createProvider()->getToolbarActions($contentRichEntityClass);

        $this->assertCount(1, $toolbarActions);
        $this->assertInstanceOf(SaveAndCreateToolbarAction::class, $toolbarActions[0]);
        $this->assertSame('sulu_admin.save_and_create', $toolbarActions[0]->getType());
        $this->assertSame('sulu_automation.publish_at', $toolbarActions[0]->getOptions()['label']);
        $this->assertSame('sulu_automation.publish_at', $toolbarActions[0]->getOptions()['title']);
        $this->assertSame('tasks', $toolbarActions[0]->getOptions()['resourceKey']);
        $this->assertSame(['entityClass' => $entityInterface], $toolbarActions[0]->getOptions()['parameters']);
        $this->assertSame('entityId', $toolbarActions[0]->getOptions()['idParameter']);
        $this->assertSame($handlerClass, $toolbarActions[0]->getOptions()['data']['handlerClass']);
        $this->assertSame('live', $toolbarActions[0]->getOptions()['permission']);
        $this->assertSame('sulu_automation_publish_at', $toolbarActions[0]->getOptions()['formKey']);
        $this->assertTrue($toolbarActions[0]->getOptions()['data']['replacePlanned']);
        $this->assertSame('(!_permissions || _permissions.edit)', $toolbarActions[0]->getOptions()['visible_condition']);
    }

    /**
     * @return iterable<string, array{class-string, class-string, class-string}>
     */
    public static function provideContent(): iterable
    {
        yield 'page' => [Page::class, PageInterface::class, PagePublishTaskHandler::class];
        yield 'article' => [Article::class, ArticleInterface::class, ArticlePublishTaskHandler::class];
    }

    public function testOffersNothingForOtherContent(): void
    {
        $this->assertSame([], $this->createProvider()->getToolbarActions(\stdClass::class));
    }

    public function testOffersNothingWithoutThePermissionToAddTasks(): void
    {
        $this->assertSame([], $this->createProvider(false)->getToolbarActions(Page::class));
    }

    public function testOffersNothingForSnippets(): void
    {
        $this->assertSame([], $this->createProvider()->getToolbarActions(\Sulu\Snippet\Domain\Model\Snippet::class));
    }
}
