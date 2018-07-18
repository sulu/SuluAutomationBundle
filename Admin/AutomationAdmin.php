<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Component\Security\Authorization\PermissionTypes;

/**
 * Admin integration of the bundle.
 */
class AutomationAdmin extends Admin
{
    const TASK_SECURITY_CONTEXT = 'sulu.automation.tasks';

    /**
     * @var string
     */
    protected $title;

    /**
     * @param string $title
     */
    public function __construct($title)
    {
        $this->title = $title;
    }

    /**
     * {@inheritdoc}
     */
    public function getJsBundleName()
    {
        return 'suluautomation';
    }

    /**
     * {@inheritdoc}
     */
    public function getSecurityContexts()
    {
        return [
            'Sulu' => [
                'Automation' => [
                    self::TASK_SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::ADD,
                        PermissionTypes::EDIT,
                        PermissionTypes::DELETE,
                    ],
                ],
            ],
        ];
    }
}
