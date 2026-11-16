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

namespace Functional\Security;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\AutomationBundle\Exception\TaskPermissionDeniedException;
use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetPublishTaskHandler;
use Sulu\Bundle\SecurityBundle\Entity\Permission;
use Sulu\Bundle\SecurityBundle\Entity\Role;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Bundle\SecurityBundle\Entity\UserRole;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Snippet\Domain\Model\SnippetInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;

/**
 * Runs the check the way task:run does: no token and no request, so nothing but the stored user decides.
 */
class TaskPermissionCheckerTest extends SuluTestCase
{
    private TaskPermissionChecker $checker;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->purgeDatabase();
        $this->checker = $this->getContainer()->get('sulu_automation.task_permission_checker');
        $this->entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
        $this->getContainer()->get('security.token_storage')->setToken(null);
    }

    public function testUserWithLiveMayRun(): void
    {
        $user = $this->createUser('publisher', $this->mask(PermissionTypes::VIEW, PermissionTypes::LIVE));

        $this->checker->checkUser($user->getId(), SnippetPublishTaskHandler::class, SnippetInterface::class, 'snippet-uuid', 'en');

        $this->addToAssertionCount(1);
    }

    public function testUserWithoutLiveMayNotRun(): void
    {
        $user = $this->createUser('editor', $this->mask(PermissionTypes::VIEW, PermissionTypes::EDIT));

        $this->expectException(TaskPermissionDeniedException::class);

        $this->checker->checkUser($user->getId(), SnippetPublishTaskHandler::class, SnippetInterface::class, 'snippet-uuid', 'en');
    }

    private function mask(string ...$permissionTypes): int
    {
        $masks = $this->getContainer()->getParameter('sulu_security.permissions');

        return \array_sum(\array_map(static fn (string $type): int => $masks[$type], $permissionTypes));
    }

    private function createUser(string $username, int $permissions): User
    {
        $role = (new Role())->setName($username)->setSystem('Sulu');
        $role->addPermission((new Permission())->setContext(SnippetAdmin::SECURITY_CONTEXT)->setPermissions($permissions));

        $user = (new User())
            ->setUsername($username)
            ->setPassword('secret')
            ->setSalt('')
            ->setLocale('en')
            ->setEnabled(true);

        $userRole = (new UserRole())->setRole($role)->setUser($user)->setLocale(\json_encode(['en']));
        $role->addUserRole($userRole);
        $user->addUserRole($userRole);

        $this->entityManager->persist($role);
        $this->entityManager->persist($userRole);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
