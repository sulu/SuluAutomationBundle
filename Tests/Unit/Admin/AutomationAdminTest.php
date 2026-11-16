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

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Infrastructure\Sulu\Admin\ArticleAdmin;
use Sulu\Bundle\AdminBundle\Admin\View\FormOverlayListViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormGroup;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Bundle\AutomationBundle\Admin\AutomationAdmin;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactory;
use Sulu\Bundle\AutomationBundle\TaskHandler\ArticlePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetPublishTaskHandler;
use Sulu\Bundle\AutomationBundle\Tests\Handler\FirstHandler;
use Sulu\Bundle\AutomationBundle\Tests\Handler\RemoveHandler;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Infrastructure\Sulu\Admin\PageAdmin;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;
use Symfony\Component\Messenger\MessageBusInterface;
use Task\TaskBundle\Handler\TaskHandlerFactory;

class AutomationAdminTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    protected function setUp(): void
    {
        $this->securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $this->securityChecker->hasPermission(Argument::cetera())->willReturn(false);
        $this->securityChecker->hasPermission(AutomationAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
    }

    public function testAddIsOfferedForEveryTypeWithAUsableHandler(): void
    {
        $this->grant(PageAdmin::getPageSecurityContext('example'), PermissionTypes::LIVE);
        $this->grant(SnippetAdmin::SECURITY_CONTEXT, PermissionTypes::LIVE);
        $this->grant(ArticleAdmin::SECURITY_CONTEXT, PermissionTypes::LIVE);

        $this->assertEquals(
            [PageInterface::class => true, SnippetInterface::class => true, ArticleInterface::class => true],
            $this->configureViews($this->createPublishHandlers()),
        );
    }

    public function testAddIsRemovedOnlyForTheTypesWithoutUsableHandler(): void
    {
        $this->grant(SnippetAdmin::SECURITY_CONTEXT, PermissionTypes::LIVE);

        $this->assertEquals(
            [PageInterface::class => false, SnippetInterface::class => true, ArticleInterface::class => false],
            $this->configureViews($this->createPublishHandlers()),
        );
    }

    public function testPageNeedsThePermissionInOneWebspace(): void
    {
        $this->grant(PageAdmin::getPageSecurityContext('other'), PermissionTypes::LIVE);

        $this->assertTrue($this->configureViews($this->createPublishHandlers())[PageInterface::class]);
    }

    public function testEachHandlerNeedsItsOwnPermission(): void
    {
        $this->grant(SnippetAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE);

        $flags = $this->configureViews([new SnippetPublishTaskHandler($this->createMessageBus()), new RemoveHandler()]);

        $this->assertTrue($flags[SnippetInterface::class]);
    }

    public function testHandlerWithoutPermissionIsAlwaysUsable(): void
    {
        $this->assertTrue($this->configureViews([new FirstHandler()])[SnippetInterface::class]);
    }

    public function testNoHandlerAtAll(): void
    {
        $this->assertEquals(
            [PageInterface::class => false, SnippetInterface::class => false, ArticleInterface::class => false],
            $this->configureViews([]),
        );
    }

    private function grant(string $securityContext, string $permission): void
    {
        $this->securityChecker->hasPermission($securityContext, $permission)->willReturn(true);
    }

    /**
     * @return array<object>
     */
    private function createPublishHandlers(): array
    {
        return [
            new PagePublishTaskHandler($this->createMessageBus()),
            new SnippetPublishTaskHandler($this->createMessageBus()),
            new ArticlePublishTaskHandler($this->createMessageBus()),
        ];
    }

    private function createMessageBus(): MessageBusInterface
    {
        return $this->prophesize(MessageBusInterface::class)->reveal();
    }

    /**
     * @param array<object> $handlers
     *
     * @return array<string, bool> the "addable" flag per entity class
     */
    private function configureViews(array $handlers): array
    {
        $webspace = new Webspace();
        $webspace->setKey('example');
        $other = new Webspace();
        $other->setKey('other');
        $webspaceManager = $this->prophesize(WebspaceManagerInterface::class);
        $webspaceManager->getWebspaceCollection()->willReturn(new WebspaceCollection(['example' => $webspace, 'other' => $other]));

        $groupProvider = $this->prophesize(GroupProviderInterface::class);
        $groupProvider->getGroups(Argument::cetera())->willReturn(['blog' => new FormGroup('blog', 'Blog')]);

        $recorder = new class(new ViewBuilderFactory()) extends AutomationViewBuilderFactory {
            /** @var array<string, bool> */
            public array $flags = [];

            public function createTaskListViewBuilder(string $name, string $path, string $entityClass /* , bool $addable = true */): FormOverlayListViewBuilderInterface
            {
                $this->flags[$entityClass] = \func_get_arg(3);

                return parent::createTaskListViewBuilder(...\func_get_args());
            }
        };

        $admin = new AutomationAdmin(
            $recorder,
            $this->securityChecker->reveal(),
            $groupProvider->reveal(),
            new TaskHandlerFactory(\array_combine(\array_map('get_class', $handlers), $handlers)),
            $webspaceManager->reveal(),
        );

        $viewBuilderFactory = new ViewBuilderFactory();
        $viewCollection = new ViewCollection();
        $viewCollection->add($viewBuilderFactory->createFormViewBuilder(PageAdmin::EDIT_FORM_VIEW, '/pages/:id'));
        $viewCollection->add($viewBuilderFactory->createFormViewBuilder(SnippetAdmin::EDIT_TABS_VIEW, '/snippets/:id'));
        $viewCollection->add($viewBuilderFactory->createFormViewBuilder(ArticleAdmin::EDIT_TABS_VIEW . '_blog', '/articles/:id'));
        $admin->configureViews($viewCollection);

        return $recorder->flags;
    }
}
