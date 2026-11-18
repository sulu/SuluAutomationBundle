# Upgrade

## 3.1.0

### Request for publishing

The bundle requires Sulu 3.1. It adds a switch "Publish automatically" and a date to the form of the request. With the switch on,
the publish task is scheduled for that date as soon as the request is approved, for the user who gives the last approval and
only if that user has the permission `live`. The date has to be in the future, otherwise the request is refused with the
status 400. Until the date has passed, the content can only be published through the task,
so the plain publish buttons are hidden and the workflow refuses a publish by a user. The banner of the form tells that the
publish is scheduled. Cancelling the request or publishing the content by hand aborts the task. It stays in the list with
the status aborted. The action `publish_at` in the footer of the review overlay stays as the way to schedule by hand when
that did not happen. Scheduling dispatches the domain event `PublishAtScheduledEvent` of the type `publish_scheduled`.

`TaskRepositoryInterface` has the new method `findPendingTasks()`. Custom repositories have to implement it.

### Publish at in the save dropdown

The save dropdown of pages, articles and snippets gets the entry "Publish at" for users with the permission `live`. It saves
the content and schedules its publish for the picked date. The entry needs no admin JavaScript of the bundle. It uses the
form toolbar action `sulu_admin.save_and_create` of Sulu 3.1 and is added through the tag
`sulu_content.save_toolbar_action_provider`, so it only shows where the bundle is installed.

## 3.0.2

### Permission of the task handler

Roles without `live` can no longer schedule, change or delete publish or unpublish tasks. Tasks scheduled before this update run
unchecked until they are scheduled again.

The `TaskController` has a new required constructor argument, the `TaskPermissionChecker`. Subclasses calling the
parent constructor have to pass it.

`AutomationViewBuilderFactoryInterface::createTaskListViewBuilder()` gets the argument `bool $addable = true` in the next
minor release. Implementations should add it now. Calling the method without it is deprecated, the add action stays
visible then.

## 2.2.0

### Deprecate usage of fos rest routing

We are no longer considering the [fos rest routing](https://github.com/handcraftedinthealps/RestRoutingBundle) as a best practice.
All bundles should use the Symfony routing system instead.

Inside your `config/routes/sulu_admin.yaml` and `config/routes/sulu_website.yaml`, you can remove the fos rest routing configuration.
First, remove all instances of `type: rest` and also replace `.yml` with `.yaml`:

```diff
# config/routes/sulu_admin.yaml`
 sulu_automation_api:
-    type: rest
-    resource: '@SuluAutomationBundle/Resources/config/routing_api.yml'
+    resource: '@SuluAutomationBundle/Resources/config/routing_api.yaml'
     prefix: /admin/api
```

## 2.0.1

A new method has been added to the `TaskRepositoryInterface`

```php
public function countPendingTasks(string $entityClass, string $entityId, string $locale = null): int
```

## 2.0.0

Following dependencies are updated and have new minimum requirements:

 - sulu/sulu: ^1.6 -> ^2.2
 - php-task/task-bundle: ^1.2 -> ^2.0
 - jms/serializer-bundle: ^1.1 -> ^3.0
 - friendsofsymfony/rest-bundle: ^1.6 -> ^2.0 || ^3.0

The bundle was so refractored to be compatible with [Sulu 2.0](https://github.com/sulu/sulu/blob/2.x/UPGRADE.md#200).

### AutomationViewBuilder refactored to AutomationViewBuilderFactory

The `AutomationViewBuilder` has been refactored and renamed to `AutomationViewBuilderFactory` to be consistent with the Sulu core.

In order to add the automation tab to a custom view. You now have to inject the `AutomationViewBuilderFactory` into your
custom admin class.

#### Custom-Admin class

```diff
{
+    $viewCollection->add(
-        (new AutomationViewBuilder(static::EDIT_FORM_AUTOMATION_VIEW, '/automation'))
-            ->setEntityClass($this->customEntityClass)
-            ->setParent(static::EDIT_FORM_VIEW)
+        $this->automationViewBuilderFactory->createTaskListViewBuilder(
+            static::EDIT_FORM_AUTOMATION_VIEW,
+            '/automation',
+            $this->customEntityClass
+        )
+            ->setTabOrder(4096)
+            ->setParent(static::EDIT_FORM_VIEW)
+    );
}
```

## 1.2.0

### Fake-Request for task handlers
 
The task was extended by the column `host` and `scheme` to fake a similar request. This will ensure that the Cache
will be delete correctly.

Update your database by running following SQL-Statement:

```sql
ALTER TABLE au_task ADD scheme VARCHAR(5) DEFAULT '' NOT NULL, ADD host VARCHAR(255) DEFAULT '' NOT NULL;
ALTER TABLE au_task CHANGE scheme scheme VARCHAR(5) NOT NULL, CHANGE host host VARCHAR(255) NOT NULL;
```
