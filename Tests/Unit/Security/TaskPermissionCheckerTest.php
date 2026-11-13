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

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Article\Infrastructure\Sulu\Admin\ArticleAdmin;
use Sulu\Bundle\AutomationBundle\Exception\TaskPermissionDeniedException;
use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\Tests\Handler\FirstHandler;
use Sulu\Bundle\AutomationBundle\Tests\Handler\RemoveHandler;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Component\Security\Authentication\UserRepositoryInterface;
use Sulu\Component\Security\Authorization\AccessControl\AccessControlManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;
use Symfony\Component\Messenger\MessageBusInterface;
use Task\Handler\TaskHandlerFactoryInterface;

class TaskPermissionCheckerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    /** @var ObjectProphecy<AccessControlManagerInterface> */
    private ObjectProphecy $accessControlManager;

    /** @var ObjectProphecy<UserRepositoryInterface> */
    private ObjectProphecy $userRepository;

    /** @var ObjectProphecy<PageRepositoryInterface> */
    private ObjectProphecy $pageRepository;

    /** @var ObjectProphecy<TaskHandlerFactoryInterface> */
    private ObjectProphecy $taskHandlerFactory;

    private TaskPermissionChecker $checker;

    protected function setUp(): void
    {
        $this->securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $this->accessControlManager = $this->prophesize(AccessControlManagerInterface::class);
        $this->userRepository = $this->prophesize(UserRepositoryInterface::class);
        $this->pageRepository = $this->prophesize(PageRepositoryInterface::class);
        $this->taskHandlerFactory = $this->prophesize(TaskHandlerFactoryInterface::class);
        $this->taskHandlerFactory->create('publish')->willReturn(
            new PagePublishTaskHandler($this->prophesize(MessageBusInterface::class)->reveal())
        );
        $this->taskHandlerFactory->create('remove')->willReturn(new RemoveHandler());
        $this->taskHandlerFactory->create('plain')->willReturn(new FirstHandler());

        $this->checker = new TaskPermissionChecker(
            $this->securityChecker->reveal(),
            $this->accessControlManager->reveal(),
            $this->userRepository->reveal(),
            $this->pageRepository->reveal(),
            $this->taskHandlerFactory->reveal(),
        );
    }

    public function testCheckCurrentUserOfPageUsesWebspaceContextOfPage(): void
    {
        $page = $this->prophesize(PageInterface::class);
        $page->getSecurityContext()->willReturn('sulu.webspaces.example');
        $this->pageRepository->getOneBy(['uuid' => 'page-uuid'])->willReturn($page->reveal());

        $this->securityChecker->checkPermission(
            Argument::that(static fn (SecurityCondition $condition): bool => 'sulu.webspaces.example' === $condition->getSecurityContext()
                && 'de' === $condition->getLocale()
                && Page::class === $condition->getObjectType()
                && 'page-uuid' === $condition->getObjectId()),
            PermissionTypes::LIVE,
        )->shouldBeCalled();

        $this->checker->checkCurrentUser('publish', PageInterface::class, 'page-uuid', 'de');
    }

    public function testCheckCurrentUserOfSnippet(): void
    {
        $this->securityChecker->checkPermission(
            Argument::that(static fn (SecurityCondition $condition): bool => SnippetAdmin::SECURITY_CONTEXT === $condition->getSecurityContext()),
            PermissionTypes::LIVE,
        )->shouldBeCalled();

        $this->checker->checkCurrentUser('publish', SnippetInterface::class, 'snippet-uuid', 'en');
    }

    public function testCheckOfUnknownClassIsSkipped(): void
    {
        $this->securityChecker->checkPermission(Argument::cetera())->shouldNotBeCalled();
        $this->accessControlManager->getUserPermissions(Argument::cetera())->shouldNotBeCalled();
        $this->userRepository->findUserById(5)->willReturn(new User());

        $this->checker->checkCurrentUser('publish', \stdClass::class, '1', 'en');
        $this->checker->checkUser(5, 'publish', \stdClass::class, '1', 'en');

        $this->addToAssertionCount(1);
    }

    public function testCheckUserUsesPermissionOfHandler(): void
    {
        $user = new User();
        $this->userRepository->findUserById(5)->willReturn($user);
        $this->accessControlManager->getUserPermissions(Argument::cetera())
            ->willReturn([PermissionTypes::LIVE => true, PermissionTypes::DELETE => false]);

        $this->expectException(TaskPermissionDeniedException::class);

        $this->checker->checkUser(5, 'remove', SnippetInterface::class, 'snippet-uuid', 'en');
    }

    public function testCheckCurrentUserUsesPermissionOfHandler(): void
    {
        $this->securityChecker->checkPermission(Argument::type(SecurityCondition::class), PermissionTypes::DELETE)
            ->shouldBeCalled();

        $this->checker->checkCurrentUser('remove', SnippetInterface::class, 'snippet-uuid', 'en');
    }

    public function testHandlerWithoutPermissionIsNotChecked(): void
    {
        $this->securityChecker->checkPermission(Argument::cetera())->shouldNotBeCalled();
        $this->userRepository->findUserById(5)->willReturn(new User());
        $this->accessControlManager->getUserPermissions(Argument::cetera())->shouldNotBeCalled();

        $this->checker->checkCurrentUser('plain', SnippetInterface::class, 'snippet-uuid', 'en');
        $this->checker->checkUser(5, 'plain', SnippetInterface::class, 'snippet-uuid', 'en');

        $this->addToAssertionCount(1);
    }

    public function testIsCurrentUserAllowed(): void
    {
        $this->securityChecker->hasPermission(Argument::type(SecurityCondition::class), PermissionTypes::DELETE)
            ->willReturn(false);
        $this->securityChecker->hasPermission(Argument::type(SecurityCondition::class), PermissionTypes::LIVE)
            ->willReturn(true);

        $publish = new PagePublishTaskHandler($this->prophesize(MessageBusInterface::class)->reveal());
        $page = $this->prophesize(PageInterface::class);
        $page->getSecurityContext()->willReturn('sulu.webspaces.example');
        $this->pageRepository->getOneBy(['uuid' => 'page-uuid'])->willReturn($page->reveal());

        $this->assertTrue($this->checker->isCurrentUserAllowed($publish, PageInterface::class, 'page-uuid', 'de'));
        $this->assertFalse($this->checker->isCurrentUserAllowed(new RemoveHandler(), SnippetInterface::class, 's', 'de'));
        $this->assertTrue($this->checker->isCurrentUserAllowed(new FirstHandler(), SnippetInterface::class, 's', 'de'));
    }

    public function testCheckUserGranted(): void
    {
        $user = new User();
        $this->userRepository->findUserById(5)->willReturn($user);
        $this->accessControlManager->getUserPermissions(
            Argument::that(static fn (SecurityCondition $condition): bool => ArticleAdmin::SECURITY_CONTEXT === $condition->getSecurityContext()),
            $user,
        )->willReturn([PermissionTypes::LIVE => true]);

        $this->checker->checkUser(5, 'publish', ArticleInterface::class, 'article-uuid', 'en');

        $this->addToAssertionCount(1);
    }

    public function testCheckUserWithoutLivePermission(): void
    {
        $user = new User();
        $this->userRepository->findUserById(5)->willReturn($user);
        $this->accessControlManager->getUserPermissions(Argument::cetera())
            ->willReturn([PermissionTypes::EDIT => true, PermissionTypes::LIVE => false]);

        $this->expectException(TaskPermissionDeniedException::class);

        $this->checker->checkUser(5, 'publish', SnippetInterface::class, 'snippet-uuid', 'en');
    }

    public function testCheckUserWithoutAnyPermission(): void
    {
        $user = new User();
        $this->userRepository->findUserById(5)->willReturn($user);
        $this->accessControlManager->getUserPermissions(Argument::cetera())->willReturn([]);

        $this->expectException(TaskPermissionDeniedException::class);

        $this->checker->checkUser(5, 'publish', SnippetInterface::class, 'snippet-uuid', 'en');
    }

    public function testCheckUserDisabled(): void
    {
        $user = new User();
        $user->setEnabled(false);
        $this->userRepository->findUserById(5)->willReturn($user);
        $this->accessControlManager->getUserPermissions(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(TaskPermissionDeniedException::class);

        $this->checker->checkUser(5, 'publish', SnippetInterface::class, 'snippet-uuid', 'en');
    }

    public function testCheckUserLocked(): void
    {
        $user = new User();
        $user->setEnabled(true);
        $user->setLocked(true);
        $this->userRepository->findUserById(5)->willReturn($user);

        $this->expectException(TaskPermissionDeniedException::class);

        $this->checker->checkUser(5, 'publish', SnippetInterface::class, 'snippet-uuid', 'en');
    }

    public function testCheckUserDeleted(): void
    {
        $this->userRepository->findUserById(5)->willReturn(null);

        $this->expectException(TaskPermissionDeniedException::class);

        $this->checker->checkUser(5, 'publish', SnippetInterface::class, 'snippet-uuid', 'en');
    }
}
