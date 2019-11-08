<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Handler;

use Sulu\Component\Content\Document\Behavior\WorkflowStageBehavior;
use Sulu\Component\DocumentManager\DocumentManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides handler for unpublishing documents.
 */
class DocumentUnpublishHandler extends BaseDocumentHandler
{
    public function __construct(DocumentManagerInterface $documentManager, TranslatorInterface $translator)
    {
        parent::__construct($translator->trans('sulu_content.task_handler.unpublish', [], 'admin'), $documentManager);
    }

    /**
     * {@inheritdoc}
     */
    protected function handleDocument(WorkflowStageBehavior $document, $locale): void
    {
        $this->documentManager->unpublish($document, $locale);
    }
}
