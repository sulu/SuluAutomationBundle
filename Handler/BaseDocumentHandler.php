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

use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\TaskHandlerConfiguration;
use Sulu\Component\Content\Document\Behavior\WorkflowStageBehavior;
use Sulu\Component\DocumentManager\DocumentManagerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Abstract class for document-handler.
 */
abstract class BaseDocumentHandler implements AutomationTaskHandlerInterface
{
    /**
     * @var string
     */
    protected $title;

    /**
     * @var DocumentManagerInterface
     */
    protected $documentManager;

    public function __construct(string $title, DocumentManagerInterface $documentManager)
    {
        $this->title = $title;
        $this->documentManager = $documentManager;
    }

    /**
     * {@inheritdoc}
     */
    public function handle($workload)
    {
        if (is_array($workload)) {
            /** @var WorkflowStageBehavior $document */
            $document = $this->documentManager->find($workload['id'], $workload['locale']);
            $this->handleDocument($document, $workload['locale']);
            $this->documentManager->flush();
        }
    }

    /**
     * Handle given document.
     *
     * @param WorkflowStageBehavior $document
     * @param string $locale
     */
    abstract protected function handleDocument(WorkflowStageBehavior $document, string $locale): void;

    /**
     * {@inheritdoc}
     */
    public function configureOptionsResolver(OptionsResolver $optionsResolver): OptionsResolver
    {
        return $optionsResolver->setRequired(['id', 'locale'])
            ->setAllowedTypes('id', 'string')
            ->setAllowedTypes('locale', 'string');
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $entityClass): bool
    {
        return is_subclass_of($entityClass, WorkflowStageBehavior::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): TaskHandlerConfiguration
    {
        return TaskHandlerConfiguration::create($this->title);
    }
}
