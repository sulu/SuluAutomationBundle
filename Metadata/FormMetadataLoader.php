<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Metadata;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;
use Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Task\TaskBundle\Handler\TaskHandlerFactory;

class FormMetadataLoader implements FormMetadataLoaderInterface
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var TaskHandlerFactory
     */
    private $taskHandlerFactory;

    public function __construct(
        TranslatorInterface $translator,
        TaskHandlerFactory $taskHandlerFactory
    ) {
        $this->translator = $translator;
        $this->taskHandlerFactory = $taskHandlerFactory;
    }

    /**
     * @param mixed[] $metadataOptions
     */
    public function getMetadata(string $key, string $locale, array $metadataOptions): ?MetadataInterface
    {
        if ('task_details' !== $key) {
            return null;
        }

        /** @var FormMetaData $form */
        $form = new FormMetadata();

        // Single Select
        $singleSelectHandler = new FieldMetadata('handlerClass');
        $singleSelectHandler->setType('single_select');
        $singleSelectHandler->setLabel($this->translator->trans('sulu_automation.task.name', [], 'admin', $locale));
        $singleSelectHandler->setRequired(true);

        $valuesOption = new OptionMetadata();
        $valuesOption->setName('values');

        foreach ($this->taskHandlerFactory->getHandlers() as $handler) {
            if ($handler instanceof AutomationTaskHandlerInterface
                && isset($metadataOptions['entityClass']) && $handler->supports($metadataOptions['entityClass'])) {
                $configuration = $handler->getConfiguration();

                $handlerOption = new OptionMetadata();
                $handlerOption->setName(get_class($handler));
                $handlerOption->setTitle($this->translator->trans($configuration->getTitle(), [], 'admin', $locale));

                $valuesOption->addValueOption($handlerOption);
            }
        }
        $singleSelectHandler->addOption($valuesOption);
        $form->addItem($singleSelectHandler);

        // Time Field
        $timeField = new FieldMetadata('time');
        $timeField->setType('time');
        $timeField->setColSpan(6);
        $timeField->setLabel($this->translator->trans('sulu_automation.task.schedule.time', [], 'admin', $locale));
        $form->addItem($timeField);

        // Date Field
        $dateField = new FieldMetadata('date');
        $dateField->setType('date');
        $dateField->setColSpan(6);
        $dateField->setLabel($this->translator->trans('sulu_automation.task.schedule.date', [], 'admin', $locale));

        $form->addItem($dateField);

        return $form;
    }
}
