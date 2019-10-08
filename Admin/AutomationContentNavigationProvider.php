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

use Sulu\Bundle\AdminBundle\Navigation\ContentNavigationItem;
use Sulu\Bundle\AdminBundle\Navigation\ContentNavigationProviderInterface;
use Sulu\Bundle\AdminBundle\Navigation\DisplayCondition;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * Integrates automation-tab into content-navigation.
 */
class AutomationContentNavigationProvider implements ContentNavigationProviderInterface
{
    /**
     * @var SecurityCheckerInterface
     */
    private $securityChecker;

    /**
     * @var TaskRepositoryInterface
     */
    private $taskRepository;

    /**
     * @var string
     */
    private $entityClass;

    /**
     * @var int
     */
    private $position;

    /**
     * @var array
     */
    private $displayConditions;

    /**
     * @param SecurityCheckerInterface $securityChecker
     * @param TaskRepositoryInterface $taskRepository
     * @param string $entityClass
     * @param int $position
     * @param array $displayConditions
     */
    public function __construct(
        SecurityCheckerInterface $securityChecker,
        TaskRepositoryInterface $taskRepository,
        $entityClass,
        $position = 45,
        array $displayConditions = []
    ) {
        $this->securityChecker = $securityChecker;
        $this->taskRepository = $taskRepository;
        $this->entityClass = $entityClass;
        $this->position = $position;
        $this->displayConditions = $displayConditions;
    }

    /**
     * {@inheritdoc}
     */
    public function getNavigationItems(array $options = [])
    {
        if (!$this->securityChecker->hasPermission(AutomationAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return [];
        }

        $automation = new ContentNavigationItem('sulu_automation.automation');
        $automation->setId('tab-automation');
        $automation->setPosition($this->position);
        $automation->setAction('automation');
        $automation->setComponent('automation-tab@suluautomation');
        $automation->setDisplay(['edit']);
        $automation->setDisplayConditions(
            array_map(function($displayCondition) {
                return new DisplayCondition(
                    $displayCondition['property'],
                    $displayCondition['operator'],
                    $displayCondition['value']
                );
            }, $this->displayConditions)
        );

        $componentOptions = ['entityClass' => $this->entityClass];
        if (array_key_exists('id', $options)) {
            $locale = array_key_exists('locale', $options) ? $options['locale'] : null;
            $componentOptions['notificationBadge'] = $this->taskRepository->countFutureTasks(
                $this->entityClass,
                $options['id'],
                $locale
            );
            $automation->setNotificationBadge($componentOptions['notificationBadge']);
        }
        $automation->setComponentOptions($componentOptions);

        return [$automation];
    }
}
