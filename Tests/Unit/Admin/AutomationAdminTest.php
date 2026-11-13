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
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Bundle\AutomationBundle\Admin\AutomationAdmin;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactoryInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetPublishTaskHandler;
use Sulu\Bundle\AutomationBundle\Tests\Handler\FirstHandler;
use Sulu\Bundle\AutomationBundle\Tests\Handler\RemoveHandler;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;
use Symfony\Component\Messenger\MessageBusInterface;
use Task\TaskBundle\Handler\TaskHandlerFactory;

class AutomationAdminTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    /** @var ObjectProphecy<AutomationViewBuilderFactoryInterface> */
    private ObjectProphecy $automationViewBuilderFactory;

    protected function setUp(): void
    {
        $this->securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $this->securityChecker->hasPermission(AutomationAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->automationViewBuilderFactory = $this->prophesize(AutomationViewBuilderFactoryInterface::class);
    }

    public function testAddIsOfferedIfAHandlerIsUsable(): void
    {
        $this->securityChecker->hasPermission(SnippetAdmin::SECURITY_CONTEXT, PermissionTypes::LIVE)->willReturn(true);

        $this->assertSame([true], $this->configureSnippetViews([$this->createPublishHandler()]));
    }

    public function testAddIsRemovedIfNoHandlerIsUsable(): void
    {
        $this->securityChecker->hasPermission(SnippetAdmin::SECURITY_CONTEXT, PermissionTypes::LIVE)->willReturn(false);

        $this->assertSame([false], $this->configureSnippetViews([$this->createPublishHandler()]));
    }

    public function testEachHandlerNeedsItsOwnPermission(): void
    {
        $this->securityChecker->hasPermission(SnippetAdmin::SECURITY_CONTEXT, PermissionTypes::LIVE)->willReturn(false);
        $this->securityChecker->hasPermission(SnippetAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)->willReturn(true);

        $this->assertSame(
            [true],
            $this->configureSnippetViews([$this->createPublishHandler(), new RemoveHandler()]),
        );
    }

    public function testHandlerWithoutPermissionIsAlwaysUsable(): void
    {
        $this->assertSame([true], $this->configureSnippetViews([new FirstHandler()]));
    }

    public function testNoHandlerAtAll(): void
    {
        $this->assertSame([false], $this->configureSnippetViews([]));
    }

    /**
     * @param array<object> $handlers
     *
     * @return list<bool> the "addable" flag the snippet tab was created with
     */
    private function configureSnippetViews(array $handlers): array
    {
        $factory = new TaskHandlerFactory(\array_combine(\array_map('get_class', $handlers), $handlers));
        $webspaceManager = $this->prophesize(WebspaceManagerInterface::class);
        $webspaceManager->getWebspaceCollection()->willReturn(new WebspaceCollection());
        $groupProvider = $this->prophesize(GroupProviderInterface::class);
        $groupProvider->getGroups(Argument::cetera())->willReturn([]);

        $flags = [];
        $this->automationViewBuilderFactory
            ->createTaskListViewBuilder(Argument::cetera())
            ->will(function(array $arguments) use (&$flags) {
                if (SnippetInterface::class === $arguments[2]) {
                    $flags[] = $arguments[3];
                }

                $builder = (new ViewBuilderFactory())->createFormOverlayListViewBuilder($arguments[0], $arguments[1]);

                return $builder;
            });

        $admin = new AutomationAdmin(
            $this->automationViewBuilderFactory->reveal(),
            $this->securityChecker->reveal(),
            $groupProvider->reveal(),
            $factory,
            $webspaceManager->reveal(),
        );

        $viewCollection = new ViewCollection();
        $viewCollection->add((new ViewBuilderFactory())->createFormViewBuilder(SnippetAdmin::EDIT_TABS_VIEW, '/snippets/:id'));
        $admin->configureViews($viewCollection);

        return $flags;
    }

    private function createPublishHandler(): SnippetPublishTaskHandler
    {
        return new SnippetPublishTaskHandler($this->prophesize(MessageBusInterface::class)->reveal());
    }
}
