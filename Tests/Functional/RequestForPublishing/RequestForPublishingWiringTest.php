<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Functional\RequestForPublishing;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtAction;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\WorkflowTransitionRequest\Action\WorkflowTransitionRequestActionRegistryInterface;

/**
 * The bundle does not autoconfigure, so what the core finds of it is only what its own config registers.
 */
class RequestForPublishingWiringTest extends SuluTestCase
{
    public function testTheCoreFindsTheActionByItsKey(): void
    {
        $registry = $this->getContainer()->get(WorkflowTransitionRequestActionRegistryInterface::class);

        $this->assertInstanceOf(PublishAtAction::class, $registry->get('publish_at'));
    }

    public function testTheFormOfTheRequestCarriesTheDateNextToTheMessage(): void
    {
        /** @var FormMetadata $form */
        $form = $this->getContainer()->get('sulu_admin.form_metadata_provider')
            ->getMetadata('workflow_transition_request_details', 'en');

        $items = $form->getItems();

        // the order follows the order in which the form directories are loaded, so only the content counts
        $this->assertEqualsCanonicalizing(['message', 'publishAt', 'publishAutomatically'], \array_keys($items));
        $this->assertSame('datetime', $items['publishAt']->getType());
        $this->assertSame('publishAutomatically == true', $items['publishAt']->getVisibleCondition());
        $this->assertSame('checkbox', $items['publishAutomatically']->getType());
        $this->assertSame(4, $items['publishAutomatically']->getColSpan());
        $this->assertSame(8, $items['publishAt']->getColSpan());
    }
}
