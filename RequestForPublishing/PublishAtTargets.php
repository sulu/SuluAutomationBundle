<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\RequestForPublishing;

use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\ArticlePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetPublishTaskHandler;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Snippet\Domain\Model\SnippetInterface;

/**
 * Maps the resource key of a workflow transition request to the entity and the handler of its scheduled publish.
 *
 * @internal
 */
final class PublishAtTargets
{
    private const TARGETS = [
        'pages' => [PageInterface::class, PagePublishTaskHandler::class],
        'articles' => [ArticleInterface::class, ArticlePublishTaskHandler::class],
        'snippets' => [SnippetInterface::class, SnippetPublishTaskHandler::class],
    ];

    public function supports(string $resourceKey): bool
    {
        return isset(self::TARGETS[$resourceKey]);
    }

    /**
     * @return class-string
     */
    public function getEntityClass(string $resourceKey): string
    {
        return $this->get($resourceKey)[0];
    }

    /**
     * @return class-string<PagePublishTaskHandler|ArticlePublishTaskHandler|SnippetPublishTaskHandler>
     */
    public function getHandlerClass(string $resourceKey): string
    {
        return $this->get($resourceKey)[1];
    }

    /**
     * @return array{class-string, class-string<PagePublishTaskHandler|ArticlePublishTaskHandler|SnippetPublishTaskHandler>}
     */
    private function get(string $resourceKey): array
    {
        return self::TARGETS[$resourceKey] ?? throw new \InvalidArgumentException(\sprintf('No publish task for the resource "%s".', $resourceKey));
    }
}
