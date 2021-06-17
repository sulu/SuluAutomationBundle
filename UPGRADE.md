# Upgrade

## 2.0.0

Following dependencies are updated and have new minimum requirements:

 - sulu/sulu: ^1.6 -> ^2.2
 - php-task/task-bundle: ^1.2 -> ^2.0
 - jms/serializer-bundle: ^1.1 -> ^3.0
 - friendsofsymfony/rest-bundle: ^1.6 -> ^2.0 || ^3.0

The bundle was so refractored to be compatible with [Sulu 2.0](https://github.com/sulu/sulu/blob/2.x/UPGRADE.md#200).

### AutomationViewBuilder refactored to AutomationViewBuilderFactory

For more consistency with the core bundle, the `AutomationViewBuilder` has been refactored to the `AutomationViewBuilderFactory`.

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
