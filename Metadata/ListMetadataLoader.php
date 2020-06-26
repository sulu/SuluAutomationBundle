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

use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\ListMetadata;
use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\ListMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\XmlListMetadataLoader;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;

class ListMetadataLoader implements ListMetadataLoaderInterface
{
    /**
     * @var XmlListMetadataLoader
     */
    private $xmlListMetadataLoader;

    public function __construct(XmlListMetadataLoader $xmlListMetadataLoader)
    {
        $this->xmlListMetadataLoader = $xmlListMetadataLoader;
    }

    public function getMetadata(string $key, string $locale, array $metadataOptions = []): ?MetadataInterface
    {
        if ('tasks' !== $key) {
            return null;
        }

        if (!method_exists(FieldMetadata::class, 'setTransformerTypeParameters')) {
            return null;
        }

        $list = $this->xmlListMetadataLoader->getMetadata($key, $locale, $metadataOptions);

        if (!$list instanceof ListMetadata) {
            return $list;
        }

        foreach ($list->getFields() as $field) {
            if ('status' !== $field->getName()) {
                continue;
            }

            $field->setType('icon');
            $field->setTransformerTypeParameters([
                'mapping' => [
                    'planned' => 'su-clock',
                    'running' => 'su-process',
                    'completed' => [
                        'icon' => 'su-check-circle',
                        'color' => '#6ac86b',
                    ],
                    'failed' => [
                        'icon' => 'su-ban',
                        'color' => '#cf3939',
                    ],
                ],
            ]);

            break;
        }

        return $list;
    }
}
