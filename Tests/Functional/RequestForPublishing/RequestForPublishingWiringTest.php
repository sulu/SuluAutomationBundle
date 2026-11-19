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
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtRequestDataValidator;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtResourceResolver;
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

    /**
     * The date is required through the schema of the form while the switch is on. The visible condition cannot say that.
     */
    public function testTheFormRequiresTheDateWhileTheSwitchIsOn(): void
    {
        /** @var FormMetadata $form */
        $form = $this->getContainer()->get('sulu_admin.form_metadata_provider')
            ->getMetadata('workflow_transition_request_details', 'en');

        $schema = \json_encode($form->getSchema()->toJsonSchema(), \JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('publishAt', $schema);
        $this->assertStringContainsString('"required":["publishAt"]', $schema);
    }

    public function testTheRequestDataIsValidatedBeforeTheCoreStoresIt(): void
    {
        $listeners = $this->getContainer()->get('event_dispatcher')
            ->getListeners('workflow.content_workflow.transition.request_for_review');

        $validators = \array_filter($listeners, static fn ($listener) => \is_object($listener[0] ?? null) && $listener[0] instanceof PublishAtRequestDataValidator);

        $this->assertCount(1, $validators);
    }

    /**
     * The repositories of the resolver are the ones of the core, and the selects of the content are valid for each.
     */
    public function testTheResourceResolverFindsNoContentThatDoesNotExist(): void
    {
        $resolver = $this->getContainer()->get('sulu_automation.request_for_publishing.resource_resolver');
        $this->assertInstanceOf(PublishAtResourceResolver::class, $resolver);

        foreach (['pages', 'articles', 'snippets'] as $resourceKey) {
            $this->assertSame(
                ['title' => null, 'webspaceKey' => null, 'securityContext' => null],
                $resolver->resolve($resourceKey, '00000000-0000-4000-8000-000000000000', 'en'),
            );
        }
    }
}
