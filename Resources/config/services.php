<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sulu\Bundle\AutomationBundle\Admin\AutomationAdmin;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactory;
use Sulu\Bundle\AutomationBundle\Admin\View\AutomationViewBuilderFactoryInterface;
use Sulu\Bundle\AutomationBundle\Controller\TaskController;
use Sulu\Bundle\AutomationBundle\Events\Events;
use Sulu\Bundle\AutomationBundle\EventSubscriber\PHPTaskEventSubscriber;
use Sulu\Bundle\AutomationBundle\Metadata\FormMetadataLoader;
use Sulu\Bundle\AutomationBundle\Serializer\TaskSerializerSubscriber;
use Sulu\Bundle\AutomationBundle\TaskHandler\ArticlePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\ArticleUnpublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\PageUnpublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetPublishTaskHandler;
use Sulu\Bundle\AutomationBundle\TaskHandler\SnippetUnpublishTaskHandler;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManager;
use Sulu\Bundle\AutomationBundle\Tasks\Scheduler\TaskScheduler;

return static function(ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();

    $parameters->set('sulu_automation.events.create', Events::TASK_CREATE_EVENT);
    $parameters->set('sulu_automation.events.update', Events::TASK_UPDATE_EVENT);
    $parameters->set('sulu_automation.events.remove', Events::TASK_REMOVE_EVENT);

    $services->set('sulu_automation.task_controller', TaskController::class)
        ->public()
        ->args([
            service('fos_rest.view_handler.default'),
            service('security.token_storage'),
            service('sulu_core.doctrine_list_builder_factory'),
            service('task.handler.factory'),
            service('task.repository.task'),
            service('task.repository.task_execution'),
            service('sulu_core.doctrine_rest_helper'),
            service('sulu_automation.tasks.manager'),
            service('doctrine.orm.entity_manager'),
            service('sulu_core.list_builder.field_descriptor_factory'),
            service('sulu.repository.task'),
            service('translator'),
        ])
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set('sulu_automation.metadata.form_metadata_loader', FormMetadataLoader::class)
        ->args([
            service('translator'),
            service('task.handler.factory'),
        ])
        ->tag('sulu_admin.form_metadata_loader');

    $services->set('sulu_automation.automation_view_builder_factory', AutomationViewBuilderFactory::class)
        ->args([
            service('sulu_admin.view_builder_factory'),
        ]);

    $services->alias(AutomationViewBuilderFactoryInterface::class, 'sulu_automation.automation_view_builder_factory');

    $services->set('sulu_automation.admin', AutomationAdmin::class)
        ->args([
            service('sulu_automation.automation_view_builder_factory'),
            service('sulu_security.security_checker'),
            service('sulu_admin.metadata_group_provider'),
        ])
        ->tag('sulu.admin')
        ->tag('sulu.context', ['context' => 'admin']);

    $services->set('sulu_automation.tasks.manager', TaskManager::class)
        ->public()
        ->args([
            service('sulu.repository.task'),
            service('sulu_automation.tasks.scheduler'),
            service('event_dispatcher'),
        ]);

    $services->set('sulu_automation.tasks.scheduler', TaskScheduler::class)
        ->args([
            service('task.storage.task'),
            service('task.storage.task_execution'),
            service('task.handler.factory'),
            service('task.scheduler'),
        ]);

    $services->set('sulu_automation.serializer.task', TaskSerializerSubscriber::class)
        ->args([
            service('task.handler.factory'),
            service('task.repository.task_execution'),
        ])
        ->tag('sulu.context', ['context' => 'admin'])
        ->tag('jms_serializer.event_subscriber');

    $services->set('sulu_automation.task.event_subscriber', PHPTaskEventSubscriber::class)
        ->args([
            service('request_stack'),
            service('sulu.repository.task'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('sulu_automation.page_publish_handler', PagePublishTaskHandler::class)
        ->args([
            service('sulu_message_bus'),
        ])
        ->tag('task.handler');

    $services->set('sulu_automation.page_unpublish_handler', PageUnpublishTaskHandler::class)
        ->args([
            service('sulu_message_bus'),
        ])
        ->tag('task.handler');

    $services->set('sulu_automation.article_publish_handler', ArticlePublishTaskHandler::class)
        ->args([
            service('sulu_message_bus'),
        ])
        ->tag('task.handler');

    $services->set('sulu_automation.article_unpublish_handler', ArticleUnpublishTaskHandler::class)
        ->args([
            service('sulu_message_bus'),
        ])
        ->tag('task.handler');

    $services->set('sulu_automation.snippet_publish_handler', SnippetPublishTaskHandler::class)
        ->args([
            service('sulu_message_bus'),
        ])
        ->tag('task.handler');

    $services->set('sulu_automation.snippet_unpublish_handler', SnippetUnpublishTaskHandler::class)
        ->args([
            service('sulu_message_bus'),
        ])
        ->tag('task.handler');
};
