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

use Sulu\Article\Domain\Model\ArticleDimensionContent;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Article\Infrastructure\Sulu\Admin\ArticleAdmin;
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Page\Infrastructure\Sulu\Admin\PageAdmin;
use Sulu\Snippet\Domain\Model\SnippetDimensionContent;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;
use Sulu\Snippet\Infrastructure\Sulu\Admin\SnippetAdmin;

/**
 * Reads what the activity log needs to show and to protect an entry: the title, the webspace and the security context
 * of the content, as the events of the workflow transition requests in the core do.
 *
 * A repository of a bundle that is not installed is null, and its content resolves to nothing.
 *
 * @internal
 *
 * @final
 */
class PublishAtResourceResolver
{
    public function __construct(
        private readonly ?PageRepositoryInterface $pageRepository = null,
        private readonly ?ArticleRepositoryInterface $articleRepository = null,
        private readonly ?SnippetRepositoryInterface $snippetRepository = null,
    ) {
    }

    /**
     * @return array{title: string|null, webspaceKey: string|null, securityContext: string|null}
     */
    public function resolve(string $resourceKey, string $resourceId, string $locale): array
    {
        $none = ['title' => null, 'webspaceKey' => null, 'securityContext' => null];
        $filters = ['uuid' => $resourceId];
        $dimensionAttributes = [
            'locale' => [$locale],
            'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
        ];
        $selects = ['selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true], 'dimensionAttributes' => $dimensionAttributes];

        if ('pages' === $resourceKey && null !== $this->pageRepository) {
            $page = $this->pageRepository->findOneBy($filters, [PageRepositoryInterface::SELECT_PAGE_CONTENT => $selects]);
            if (!$page instanceof PageInterface) {
                return $none;
            }

            $collection = new DimensionContentCollection($page->getDimensionContents(), [], PageDimensionContent::class);

            return [
                'title' => $collection->getDimensionContent(['locale' => $locale])?->getTitle(),
                'webspaceKey' => $page->getWebspaceKey(),
                'securityContext' => PageAdmin::getPageSecurityContext($page->getWebspaceKey()),
            ];
        }

        if ('articles' === $resourceKey && null !== $this->articleRepository) {
            $article = $this->articleRepository->findOneBy($filters, [ArticleRepositoryInterface::SELECT_ARTICLE_CONTENT => $selects]);
            if (null === $article) {
                return $none;
            }

            $collection = new DimensionContentCollection($article->getDimensionContents(), [], ArticleDimensionContent::class);

            return [
                'title' => $collection->getDimensionContent(['locale' => $locale])?->getTitle(),
                'webspaceKey' => null,
                'securityContext' => ArticleAdmin::SECURITY_CONTEXT,
            ];
        }

        if ('snippets' === $resourceKey && null !== $this->snippetRepository) {
            $snippet = $this->snippetRepository->findOneBy($filters, [SnippetRepositoryInterface::SELECT_SNIPPET_CONTENT => [
                'selects' => $selects['selects'],
                'dimensionAttributes' => ['locale' => $locale] + $dimensionAttributes,
            ]]);
            if (null === $snippet) {
                return $none;
            }

            $collection = new DimensionContentCollection($snippet->getDimensionContents(), [], SnippetDimensionContent::class);

            return [
                'title' => $collection->getDimensionContent(['locale' => $locale])?->getTitle(),
                'webspaceKey' => null,
                'securityContext' => SnippetAdmin::SECURITY_CONTEXT,
            ];
        }

        return $none;
    }
}
