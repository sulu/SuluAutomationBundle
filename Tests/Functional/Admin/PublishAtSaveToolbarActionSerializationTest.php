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

namespace Sulu\Bundle\AutomationBundle\Tests\Functional\Admin;

use Sulu\Bundle\AutomationBundle\Admin\PublishAtSaveToolbarActionProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Page\Domain\Model\Page;

/**
 * The core translates label and title of the toolbar action while serializing, so the keys of the bundle
 * must resolve in the admin domain.
 */
class PublishAtSaveToolbarActionSerializationTest extends SuluTestCase
{
    public function testLabelAndTitleAreTranslatedOnSerialization(): void
    {
        $securityChecker = $this->createStub(SecurityCheckerInterface::class);
        $securityChecker->method('hasPermission')->willReturn(true);

        $toolbarActions = (new PublishAtSaveToolbarActionProvider($securityChecker))->getToolbarActions(Page::class);
        $this->assertCount(1, $toolbarActions);

        /** @var array{type: string, options: array<string, mixed>} $serialized */
        $serialized = \json_decode(
            $this->getContainer()->get('jms_serializer')->serialize($toolbarActions[0], 'json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $this->assertSame('sulu_admin.save_and_create', $serialized['type']);
        $this->assertSame('Publish at', $serialized['options']['label']);
        $this->assertSame('Publish at', $serialized['options']['title']);
        $this->assertSame('sulu_automation_publish_at', $serialized['options']['formKey']);
        $this->assertSame('(!_permissions || _permissions.edit)', $serialized['options']['visible_condition']);
    }
}
