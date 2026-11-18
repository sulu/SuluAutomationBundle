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
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AutomationBundle\TaskHandler\ArticlePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetPublishTaskHandler;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Content\Infrastructure\Sulu\Admin\SaveToolbarActionProviderInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Snippet\Domain\Model\SnippetInterface;

/**
 * Adds the entry "Publish at" to the save dropdown of pages, articles and snippets, which saves the content and
 * schedules its publish for a date. Only users with the permission `live` see it, and not while a request for
 * publishing is open, because the request decides then. The core drops the entry for users without `live`, because
 * pages deliver no permissions to the visible condition.
 */
final class PublishAtSaveToolbarActionProvider implements SaveToolbarActionProviderInterface
{
    private const HANDLERS = [
        PageInterface::class => PagePublishTaskHandler::class,
        ArticleInterface::class => ArticlePublishTaskHandler::class,
        SnippetInterface::class => SnippetPublishTaskHandler::class,
    ];

    public function getToolbarActions(string $contentRichEntityClass): array
    {
        foreach (self::HANDLERS as $entityInterface => $handlerClass) {
            if (!\is_a($contentRichEntityClass, $entityInterface, true)) {
                continue;
            }

            return [
                new ToolbarAction(
                    'sulu_admin.save_and_create',
                    [
                        'label' => 'sulu_automation.publish_at',
                        'title' => 'sulu_automation.publish_at',
                        'formKey' => 'sulu_automation_publish_at',
                        'resourceKey' => 'tasks',
                        'data' => ['handlerClass' => $handlerClass],
                        'parameters' => ['entityClass' => $entityInterface],
                        'idParameter' => 'entityId',
                        'permission' => PermissionTypes::LIVE,
                        'visible_condition' => '(!_permissions || _permissions.live) && !activeWorkflowTransitionRequest',
                    ]
                ),
            ];
        }

        return [];
    }
}
