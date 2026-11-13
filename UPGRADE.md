# Upgrade

## 3.0.2

### Task handlers can require a permission

A handler implementing the new `SecuredTaskHandlerInterface` names the permission a user needs on the entity,
for example `live` to publish or `delete` to remove. The bundle's own publish and unpublish handlers require `live`.
Handlers without the interface are not checked, as before.

The permission is needed to create or change a task. The "add" overlay offers only the handlers the user may use.
The automation tab stays visible. Its add action is removed if the user may use no handler for that entity type in
any webspace or group.

`AutomationViewBuilderFactoryInterface::createTaskListViewBuilder()` got an optional fourth argument `$addable`.
Implementations of the interface have to add it.

The task runs with the user who scheduled it and checks the permission again right before it runs. It fails if that
user lost the permission, was deactivated or locked.

Roles that could schedule publish tasks without `live` lose that.

Tasks scheduled before this update have no user in their workload. They still run without the check and trigger a
deprecation. Schedule them again to add the user.

Entities of other bundles have no known security context, so their handlers have to check themselves.

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
