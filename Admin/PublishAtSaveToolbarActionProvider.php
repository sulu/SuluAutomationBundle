<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Admin;

use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\AdminBundle\Admin\View\SaveAndCreateToolbarAction;
use Sulu\Bundle\AutomationBundle\TaskHandler\ArticlePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Content\Infrastructure\Sulu\Admin\SaveToolbarActionProviderInterface;
use Sulu\Page\Domain\Model\PageInterface;

/**
 * Adds the entry "Publish at" to the save dropdown of pages and articles, which saves the content and schedules its
 * publish for a date. Only users who may add tasks and edit and publish the content see it, and not while a request
 * for publishing is open, because the request decides then. A later "Publish at" replaces the planned publish of the
 * content, because the entry sends the marker `replacePlanned` along.
 *
 * Snippets are left out. Their edit tabs carry a group suffix, so they have no Automation tab and a task from this
 * entry could be neither seen nor cancelled.
 */
final class PublishAtSaveToolbarActionProvider implements SaveToolbarActionProviderInterface
{
    private const HANDLERS = [
        PageInterface::class => PagePublishTaskHandler::class,
        ArticleInterface::class => ArticlePublishTaskHandler::class,
    ];

    public function __construct(private readonly SecurityCheckerInterface $securityChecker)
    {
    }

    public function getToolbarActions(string $contentRichEntityClass): array
    {
        if (!$this->securityChecker->hasPermission(AutomationAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            return [];
        }

        foreach (self::HANDLERS as $entityInterface => $handlerClass) {
            if (!\is_a($contentRichEntityClass, $entityInterface, true)) {
                continue;
            }

            return [
                new SaveAndCreateToolbarAction(
                    label: 'sulu_automation.publish_at',
                    title: 'sulu_automation.publish_at',
                    formKey: 'sulu_automation_publish_at',
                    resourceKey: 'tasks',
                    idParameter: 'entityId',
                    data: ['handlerClass' => $handlerClass, 'replacePlanned' => true],
                    parameters: ['entityClass' => $entityInterface],
                    visibleCondition: '(!_permissions || _permissions.edit)',
                    permission: PermissionTypes::LIVE,
                ),
            ];
        }

        return [];
    }
}
