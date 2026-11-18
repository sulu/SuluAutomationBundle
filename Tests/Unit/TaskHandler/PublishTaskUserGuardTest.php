<?php

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
use Sulu\Bundle\AutomationBundle\TaskHandler\PublishTaskUserGuard;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authentication\UserRepositoryInterface;
use Sulu\Content\Application\Security\WorkflowTransitionAdminAuthorizerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PublishTaskUserGuardTest extends TestCase
{
    use ProphecyTrait;

    public function testAsksTheWorkflowWithTheUserOfTheTask(): void
    {
        $user = $this->prophesize(UserInterface::class)->reveal();
        $userRepository = $this->prophesize(UserRepositoryInterface::class);
        $userRepository->findUserById(7)->willReturn($user);

        $authorizer = $this->prophesize(WorkflowTransitionAdminAuthorizerInterface::class);
        $authorizer->assertCanPublish('pages', 'page-uuid', 'en', $user)->shouldBeCalledOnce();

        (new PublishTaskUserGuard($authorizer->reveal(), $userRepository->reveal()))
            ->assertCanPublish('pages', ['id' => 'page-uuid', 'locale' => 'en', 'userId' => '7']);
    }

    public function testLetsTheRefusalOfTheWorkflowThrough(): void
    {
        $user = $this->prophesize(UserInterface::class)->reveal();
        $userRepository = $this->prophesize(UserRepositoryInterface::class);
        $userRepository->findUserById(7)->willReturn($user);

        $authorizer = $this->prophesize(WorkflowTransitionAdminAuthorizerInterface::class);
        $authorizer->assertCanPublish('pages', 'page-uuid', 'en', $user)->willThrow(new AccessDeniedException('no'));

        $this->expectException(AccessDeniedException::class);

        (new PublishTaskUserGuard($authorizer->reveal(), $userRepository->reveal()))
            ->assertCanPublish('pages', ['id' => 'page-uuid', 'locale' => 'en', 'userId' => 7]);
    }

    public function testRefusesAUserThatDoesNotExistAnymore(): void
    {
        $userRepository = $this->prophesize(UserRepositoryInterface::class);
        $userRepository->findUserById(7)->willReturn(null);

        $authorizer = $this->prophesize(WorkflowTransitionAdminAuthorizerInterface::class);
        $authorizer->assertCanPublish(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(AccessDeniedException::class);

        (new PublishTaskUserGuard($authorizer->reveal(), $userRepository->reveal()))
            ->assertCanPublish('pages', ['id' => 'page-uuid', 'locale' => 'en', 'userId' => 7]);
    }

    public function testRunsATaskWithoutAUserAsBefore(): void
    {
        $authorizer = $this->prophesize(WorkflowTransitionAdminAuthorizerInterface::class);
        $authorizer->assertCanPublish(Argument::cetera())->shouldNotBeCalled();

        (new PublishTaskUserGuard($authorizer->reveal(), $this->prophesize(UserRepositoryInterface::class)->reveal()))
            ->assertCanPublish('pages', ['id' => 'page-uuid', 'locale' => 'en']);
    }
}
