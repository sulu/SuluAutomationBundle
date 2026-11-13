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

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\Metadata;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AutomationBundle\Metadata\FormMetadataLoader;
use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\Tests\Handler\FirstHandler;
use Sulu\Bundle\AutomationBundle\Tests\Handler\RemoveHandler;
use Symfony\Contracts\Translation\TranslatorInterface;
use Task\TaskBundle\Handler\TaskHandlerFactory;

class FormMetadataLoaderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TaskPermissionChecker> */
    private ObjectProphecy $permissionChecker;

    private FormMetadataLoader $loader;

    protected function setUp(): void
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::cetera())->willReturnArgument(0);

        $factory = new TaskHandlerFactory([
            FirstHandler::class => new FirstHandler(),
            RemoveHandler::class => new RemoveHandler(),
        ]);

        $this->permissionChecker = $this->prophesize(TaskPermissionChecker::class);
        $this->loader = new FormMetadataLoader($translator->reveal(), $factory, $this->permissionChecker->reveal());
    }

    public function testOffersOnlyHandlersTheUserMayUse(): void
    {
        $this->permissionChecker->isCurrentUserAllowed(Argument::type(FirstHandler::class), 'Entity', '1', 'de')->willReturn(true);
        $this->permissionChecker->isCurrentUserAllowed(Argument::type(RemoveHandler::class), 'Entity', '1', 'de')->willReturn(false);

        $this->assertSame([FirstHandler::class], $this->getOfferedHandlers(['entityClass' => 'Entity', 'entityId' => '1']));
    }

    public function testOffersNoHandlerIfNoneIsAllowed(): void
    {
        $this->permissionChecker->isCurrentUserAllowed(Argument::cetera())->willReturn(false);

        $this->assertSame([], $this->getOfferedHandlers(['entityClass' => 'Entity', 'entityId' => '1']));
    }

    /**
     * @param array<string, string> $options
     *
     * @return list<string>
     */
    private function getOfferedHandlers(array $options): array
    {
        $form = $this->loader->getMetadata('task_details', 'de', $options);
        $this->assertInstanceOf(FormMetadata::class, $form);

        $select = $form->getItems()['handlerClass'];

        return \array_values(\array_map(
            static fn ($option) => $option->getName(),
            $select->getOptions()['values']->getValue() ?? [],
        ));
    }
}
