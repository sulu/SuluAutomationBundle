<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\RequestForPublishing;

use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\AutomationBundle\Entity\Task;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\Event\PublishAtScheduledEvent;
use Sulu\Bundle\AutomationBundle\Security\TaskPermissionChecker;
use Sulu\Bundle\AutomationBundle\Tasks\Manager\TaskManagerInterface;
use Sulu\Bundle\AutomationBundle\Tasks\Model\TaskRepositoryInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Content\Application\RequestWorkflow\WorkflowTransitionRequestStatusResolverInterface;
use Sulu\Content\Application\WorkflowTransitionRequest\Action\WorkflowTransitionRequestActionInterface;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequest;
use Sulu\Content\Domain\Value\WorkflowTransitionRequest\WorkflowTransitionRequestStatusEnum;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Schedules the publish of an approved request for the date its form names. {@see PublishAtScheduler} does this on
 * approval, the action is the way to do it by hand. {@see PublishAtTaskCanceller} cancels the task.
 *
 * @internal
 *
 * @final
 */
class PublishAtAction implements WorkflowTransitionRequestActionInterface
{
    public const KEY = 'publish_at';

    public const DATA_KEY = 'publishAt';

    public const TOGGLE_KEY = 'publishAutomatically';

    /**
     * Ties the task to this request. The form does not know the key, so the review card does not show it.
     */
    public const TASK_ID_KEY = 'publishAtTaskId';

    public function __construct(
        private readonly PublishAtTargets $targets,
        private readonly TaskManagerInterface $taskManager,
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly TaskPermissionChecker $permissionChecker,
        private readonly WorkflowTransitionRequestStatusResolverInterface $statusResolver,
        private readonly RequestStack $requestStack,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly PublishAtResourceResolver $resourceResolver,
        private readonly ?\DateTimeImmutable $now = null,
    ) {
    }

    public static function getKey(): string
    {
        return self::KEY;
    }

    public function getLabel(): string
    {
        return 'sulu_automation.publish_on';
    }

    public function getLabelParameters(WorkflowTransitionRequest $request): array
    {
        $publishAt = $this->getPublishAt($request);

        return null === $publishAt ? [] : ['date' => $publishAt->format('d.m.Y H:i')];
    }

    public function getPermission(): string
    {
        return PermissionTypes::LIVE;
    }

    /**
     * No other publish until the date. After it the action refuses the date, so the plain publish has to work again.
     */
    public function replacesPublish(WorkflowTransitionRequest $request): bool
    {
        if (!$this->targets->supports($request->getResourceKey())) {
            return false;
        }

        $publishAt = $this->getPublishAt($request);

        return null !== $publishAt && $publishAt > ($this->now ?? new \DateTimeImmutable());
    }

    public function getStatusMessage(WorkflowTransitionRequest $request): ?array
    {
        $publishAt = $this->getPublishAt($request);
        if (null === $publishAt || !$this->targets->supports($request->getResourceKey())) {
            return null;
        }

        if (WorkflowTransitionRequestStatusEnum::APPROVED !== $this->statusResolver->resolve($request)) {
            return null;
        }

        if (!$this->hasPendingTask($request)) {
            return null;
        }

        return [
            'message' => 'sulu_automation.publish_scheduled',
            'parameters' => ['date' => $publishAt->format('d.m.Y H:i')],
        ];
    }

    public function isAvailable(WorkflowTransitionRequest $request): bool
    {
        if (!$this->targets->supports($request->getResourceKey())) {
            return false;
        }

        if (WorkflowTransitionRequestStatusEnum::APPROVED !== $this->statusResolver->resolve($request)) {
            return false;
        }

        $publishAt = $this->getPublishAt($request);

        // execute() refuses a past date, so offering the action would fail the approval
        if (null === $publishAt || $publishAt <= ($this->now ?? new \DateTimeImmutable())) {
            return false;
        }

        return !$this->hasPendingTask($request);
    }

    /**
     * A task someone scheduled by hand for the same content is not the task of this request.
     */
    private function hasPendingTask(WorkflowTransitionRequest $request): bool
    {
        $taskId = $request->getData()[self::TASK_ID_KEY] ?? null;
        if (!\is_string($taskId) || '' === $taskId) {
            return false;
        }

        $resourceKey = $request->getResourceKey();
        $tasks = $this->taskRepository->findPendingTasks(
            $this->targets->getHandlerClass($resourceKey),
            $this->targets->getEntityClass($resourceKey),
            $request->getResourceId(),
            $request->getLocale(),
        );

        foreach ($tasks as $task) {
            if ($taskId === $task->getId()) {
                return true;
            }
        }

        return false;
    }

    public function execute(WorkflowTransitionRequest $request): void
    {
        $publishAt = $this->getPublishAt($request);
        if (null === $publishAt) {
            throw new BadRequestHttpException('The request names no date to publish at.');
        }

        if ($publishAt <= ($this->now ?? new \DateTimeImmutable())) {
            throw new BadRequestHttpException('The date to publish at lies in the past.');
        }

        $resourceKey = $request->getResourceKey();
        $httpRequest = $this->requestStack->getCurrentRequest();

        $task = new Task();
        $task->setHandlerClass($this->targets->getHandlerClass($resourceKey));
        $task->setEntityClass($this->targets->getEntityClass($resourceKey));
        $task->setEntityId($request->getResourceId());
        $task->setLocale($request->getLocale());
        $task->setSchedule($publishAt);
        $task->setScheme($httpRequest?->getScheme() ?? 'https');
        $task->setHost($httpRequest?->getHost() ?? 'localhost');

        // same check as for a task scheduled by hand
        $this->permissionChecker->checkCurrentUser(
            $task->getHandlerClass(),
            $task->getEntityClass(),
            $task->getEntityId(),
            $task->getLocale(),
        );

        $task = $this->taskManager->create($task);

        // flushed with the request, like the rest of the action
        $request->setData([...$request->getData(), self::TASK_ID_KEY => $task->getId()]);

        $resource = $this->resourceResolver->resolve($resourceKey, $request->getResourceId(), $request->getLocale());
        $this->domainEventCollector->collect(new PublishAtScheduledEvent(
            $request,
            $publishAt,
            $task->getId(),
            $resource['title'],
            $resource['webspaceKey'],
            $resource['securityContext'],
        ));
    }

    private function getPublishAt(WorkflowTransitionRequest $request): ?\DateTimeImmutable
    {
        return self::readPublishAt($request->getData());
    }

    /**
     * The form keeps a hidden field's value, so the date counts only while the toggle is on.
     * A date without a zone is read in the server's default zone.
     *
     * @param array<string, mixed> $data
     */
    public static function readPublishAt(array $data): ?\DateTimeImmutable
    {
        if (true !== ($data[self::TOGGLE_KEY] ?? null)) {
            return null;
        }

        $value = $data[self::DATA_KEY] ?? null;
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        return \date_create_immutable($value) ?: null;
    }
}
