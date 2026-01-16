# Upgrade

## 3.0.0

This release upgrades the bundle to be compatible with Sulu 3.0.

### Minimum Requirements Updated

Following dependencies have new minimum requirements:

- sulu/sulu: ^2.6 -> ^3.0
- symfony/config: ^4.3 || ^5.4 || ^6.3 || ^7.0 -> ^6.4 || ^7.0
- symfony/dependency-injection: ^4.3 || ^5.4 || ^6.3 || ^7.0 -> ^6.4 || ^7.0
- symfony/http-foundation: ^4.3 || ^5.4 || ^6.3 || ^7.0 -> ^6.4 || ^7.0
- symfony/http-kernel: ^4.3 || ^5.4 || ^6.3 || ^7.0 -> ^6.4 || ^7.0

### DateTime to DateTimeImmutable

The `schedule` property in the `Task` entity has been changed from `DateTime` to `DateTimeImmutable` to align with Sulu 3.0's immutable datetime approach.

If you have custom code that extends or interacts with the `TaskInterface`, you need to update the type hints:

```diff
- public function getSchedule(): \DateTime;
- public function setSchedule(\DateTime $schedule): self;
+ public function getSchedule(): \DateTimeImmutable;
+ public function setSchedule(\DateTimeImmutable $schedule): self;
```

### Database Migration

The `schedule` column in the `au_task` table has been changed from `datetime` to `datetime_immutable`. This change is backwards compatible, but you should run doctrine schema update to ensure consistency:

```bash
php bin/adminconsole doctrine:schema:update --force
```

### Sulu 3.0 Namespace Changes

Sulu 3.0 introduced significant namespace reorganization:

- `Sulu\Bundle\PageBundle\Admin\PageAdmin` → `Sulu\Page\Infrastructure\Sulu\Admin\PageAdmin`
- `Sulu\Bundle\PageBundle\Document\BasePageDocument` → `Sulu\Page\Domain\Model\PageInterface`

If you have custom Admin classes that use these imports, update them accordingly.

### PageTree Functionality Removed

The `PageTreeUpdaterInterface` and related classes (`AutomationPageTreeUpdater`, `PageTreeRouteUpdateHandler`) have been **removed** from this bundle.

Sulu 3.0 replaced PHPCR with Doctrine ORM and completely rewrote the routing system. The `PageTreeUpdaterInterface` no longer exists in Sulu 3.0, and the document manager approach is no longer used.

If you were using the PageTree route update functionality, you will need to implement a custom solution based on Sulu 3.0's new routing architecture. Consult the [Sulu 3.0 Upgrade Guide](https://github.com/sulu/sulu/blob/3.0/UPGRADE-3.x.md) for details on the new routing system.

### Document Publish/Unpublish Handlers Removed

The `DocumentPublishHandler`, `DocumentUnpublishHandler`, and `BaseDocumentHandler` classes have been **removed** from this bundle.

These handlers depended on the `DocumentManagerInterface` which was part of the PHPCR-based document management system in Sulu 2.x. Since Sulu 3.0 uses Doctrine ORM instead of PHPCR, these handlers are no longer compatible.

If you need publish/unpublish automation for pages or articles in Sulu 3.0, you will need to implement custom handlers using the new Sulu 3.0 API (e.g., `PageRepositoryInterface` for pages or the message bus for workflow transitions).

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
