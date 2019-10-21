<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\Handler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Sulu\Bundle\AutomationBundle\Handler\DocumentUnpublishHandler;
use Sulu\Bundle\PageBundle\Document\HomeDocument;
use Sulu\Bundle\PageBundle\Document\PageDocument;
use Sulu\Component\Content\Document\Behavior\WorkflowStageBehavior;
use Sulu\Component\DocumentManager\DocumentManagerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit tests for unpublish handler.
 */
class DocumentUnpublishHandlerTest extends TestCase
{
    /**
     * @var DocumentManagerInterface
     */
    private $documentManager;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var DocumentUnpublishHandler
     */
    private $unpublishHandler;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->documentManager = $this->prophesize(DocumentManagerInterface::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->translator->trans(Argument::type('string'), Argument::type('array'), Argument::type('string'))->willReturn('Unpublish');
        $this->unpublishHandler = new DocumentUnpublishHandler($this->documentManager->reveal(), $this->translator->reveal());
    }

    public function testHandle($id = '123-123-123', $locale = 'de')
    {
        $this->documentManager->find($id, $locale)->willReturn($this->prophesize(WorkflowStageBehavior::class));
        $this->documentManager->unpublish(Argument::type(WorkflowStageBehavior::class), $locale)->shouldBeCalled();
        $this->documentManager->flush()->shouldBeCalled();

        $this->unpublishHandler->handle(['id' => $id, 'locale' => $locale]);
    }

    public function testConfigureOptionsResolver()
    {
        $optionsResolver = $this->prophesize(OptionsResolver::class);

        $optionsResolver->setRequired(['id', 'locale'])->shouldBeCalled()->willReturn($optionsResolver->reveal());
        $optionsResolver->setAllowedTypes('id', 'string')->shouldBeCalled()->willReturn($optionsResolver->reveal());
        $optionsResolver->setAllowedTypes('locale', 'string')->shouldBeCalled()->willReturn($optionsResolver->reveal());

        $this->unpublishHandler->configureOptionsResolver($optionsResolver->reveal());
    }

    public function testSupports()
    {
        $this->assertTrue($this->unpublishHandler->supports(PageDocument::class));
        $this->assertTrue($this->unpublishHandler->supports(HomeDocument::class));
        $this->assertFalse($this->unpublishHandler->supports(\stdClass::class));
    }
}
