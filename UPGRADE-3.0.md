# Upgrade

## 3.0.0

The upgrade from SuluAutomationBundle 2.x to 3.0 is a major upgrade that requires Sulu 3.0 and includes several breaking changes.

### Pre Update Requirements

Before upgrading to 3.0, ensure you have:
- Upgraded to the latest Sulu 2.6 version
- Upgraded to the latest SuluAutomationBundle 2.x version
- Completed all Sulu 2.6 to 3.0 migration steps from the [Sulu UPGRADE-3.x.md](https://github.com/sulu/sulu/blob/3.x/UPGRADE-3.x.md)

### Update Dependencies

The following dependencies have been updated and have new minimum requirements:

- **sulu/sulu**: `^2.6` → `^3.0`
- **doctrine/orm**: `^2.5.3` → `^2.17.3 || ^3.3`
- **symfony components**: `^4.3 || ^5.4 || ^6.3 || ^7.0` → `^6.4 || ^7.1`
- **php-task/php-task**: `^1.3.3 || ^2.0` → `^3.0.1`
- **php-task/task-bundle**: `^2.0 || ^3.0` → `^4.0.1`

### DateTime to DateTimeImmutable Migration

All `DateTime` instances have been replaced with `DateTimeImmutable` throughout the bundle. This affects:

### Task Changes

The `Sulu\Bundle\AutomationBundle\Entity\Task` entity now has a direct relation to the PhpTask entity instead of storing a task ID. The `getTaskId()` method has been removed and replaced with `getTask()`, which returns a `Task\TaskInterface`. The `setTask()` method signature has also been updated accordingly.

```diff
    /**
-    * @var string|null
+    * @var Task\TaskInterface|null
     */
    private $taskId;
```

```diff
-    public function getTaskId(): ?string
+    public function getTask(): ?Task\TaskInterface
     {
-        return $this->taskId;
+        return $this->task;
     }
```

```diff
-    public function setTaskId(?string $taskId): TaskInterface
+    public function setTask(?Task\TaskInterface $task): self
     {
-        $this->taskId = $taskId;
+        $this->task = $task;
```


### TaskRepositoryInterface Changes

The `TaskRepositoryInterface` has now a findByTask instead of findByTaskId.

```diff
-    public function findByTaskId(string $id): ?TaskInterface;
+    public function findByTask(PHPTaskInterface $task): ?TaskInterface;
```

### Handler Architecture Refactoring

The generic document handler architecture has been replaced with specific resource-based task handlers.

#### Removed Classes

The following handler classes have been removed:

- `Sulu\Bundle\AutomationBundle\Handler\BaseDocumentHandler`
- `Sulu\Bundle\AutomationBundle\Handler\DocumentPublishHandler`
- `Sulu\Bundle\AutomationBundle\Handler\DocumentUnpublishHandler`

#### New TaskHandler Classes

New dedicated task handlers have been introduced for each resource type:

**Pages:**
- `Sulu\Bundle\AutomationBundle\TaskHandler\PagePublishTaskHandler`
- `Sulu\Bundle\AutomationBundle\TaskHandler\PageUnpublishTaskHandler`

**Articles:**
- `Sulu\Bundle\AutomationBundle\TaskHandler\ArticlePublishTaskHandler`
- `Sulu\Bundle\AutomationBundle\TaskHandler\ArticleUnpublishTaskHandler`

**Snippets:**
- `Sulu\Bundle\AutomationBundle\TaskHandler\SnippetPublishTaskHandler`
- `Sulu\Bundle\AutomationBundle\TaskHandler\SnippetUnpublishTaskHandler`

These new handlers use Sulu 3.0's message bus architecture instead of the DocumentManager.

### TaskController Changes

#### Removed Interface Implementation

`Sulu\Bundle\AutomationBundle\Controller\TaskController` no longer implements `FOS\RestBundle\Routing\ClassResourceInterface`:

```diff
-use FOS\RestBundle\Routing\ClassResourceInterface;

-class TaskController extends AbstractRestController implements ClassResourceInterface, SecuredControllerInterface
+class TaskController extends AbstractRestController implements SecuredControllerInterface
```

#### Constructor Changes

```diff
 public function __construct(
     ...
     protected EntityManagerInterface $entityManager,
-    ?SerializerInterface $serializer,
     protected FieldDescriptorFactoryInterface $fieldDescriptorFactory,
     rotected AutomationTaskRepositoryInterface $automationTaskRepository,,
+    protected TranslatorInterface $translator,
 ) {
```

### AutomationAdmin Changes

#### View Configuration Refactoring

The admin class now configures separate views for Pages, Snippets, and Articles:

#### Constructor Changes

```diff
 public function __construct(
     private SecurityCheckerInterface $securityChecker,
+    private GroupProviderInterface $groupProvider,
 ) {
```

#### Domain Models Instead of Documents

The bundle now uses Sulu 3.0 domain models instead of PHPCR document classes:

```diff
-use Sulu\Bundle\PageBundle\Document\BasePageDocument;
+use Sulu\Page\Domain\Model\PageInterface;
+use Sulu\Snippet\Domain\Model\SnippetInterface;
+use Sulu\Article\Domain\Model\ArticleInterface;
```

**Migration**: Run the following command to update your existing data:

`bin/adminconsole sulu:phpcr-migration:migrate`