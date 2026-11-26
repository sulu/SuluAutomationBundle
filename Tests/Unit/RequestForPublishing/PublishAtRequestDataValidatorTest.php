<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Tests\Unit\RequestForPublishing;

use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AutomationBundle\RequestForPublishing\PublishAtRequestDataValidator;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

class PublishAtRequestDataValidatorTest extends TestCase
{
    public function testRefusesTheSwitchWithoutADate(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Choose the date to publish at');

        (new PublishAtRequestDataValidator())($this->createEvent(['publishAutomatically' => true, 'publishAt' => null]));
    }

    public function testRefusesTheSwitchWithAMissingOrEmptyDate(): void
    {
        $validator = new PublishAtRequestDataValidator();

        foreach ([['publishAutomatically' => true], ['publishAutomatically' => true, 'publishAt' => '']] as $data) {
            try {
                $validator($this->createEvent($data));
                $this->fail('A request without a date must be refused.');
            } catch (BadRequestHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRefusesADateThatCannotBeRead(): void
    {
        $this->expectException(BadRequestHttpException::class);

        (new PublishAtRequestDataValidator())($this->createEvent(['publishAutomatically' => true, 'publishAt' => 'next week maybe']));
    }

    public function testAcceptsTheSwitchWithADate(): void
    {
        $validator = new PublishAtRequestDataValidator(new \DateTimeImmutable('2026-11-24T09:00:00+00:00'));

        $validator($this->createEvent(['publishAutomatically' => true, 'publishAt' => '2026-12-01T10:00:00+00:00']));

        $this->addToAssertionCount(1);
    }

    public function testRefusesADateInThePast(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Choose a date in the future');

        $validator = new PublishAtRequestDataValidator(new \DateTimeImmutable('2026-12-02T09:00:00+00:00'));
        $validator($this->createEvent(['publishAutomatically' => true, 'publishAt' => '2026-12-01T10:00:00+00:00']));
    }

    public function testRefusesTheMomentThatIsNow(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $validator = new PublishAtRequestDataValidator(new \DateTimeImmutable('2026-12-01T10:00:00+00:00'));
        $validator($this->createEvent(['publishAutomatically' => true, 'publishAt' => '2026-12-01T10:00:00+00:00']));
    }

    public function testIgnoresAPastDateWhileTheSwitchIsOff(): void
    {
        $validator = new PublishAtRequestDataValidator(new \DateTimeImmutable('2026-12-02T09:00:00+00:00'));
        $validator($this->createEvent(['publishAutomatically' => false, 'publishAt' => '2026-12-01T10:00:00+00:00']));

        $this->addToAssertionCount(1);
    }

    /**
     * The form keeps a date that was filled in before the switch went off again.
     */
    public function testAcceptsARequestWithTheSwitchOff(): void
    {
        $validator = new PublishAtRequestDataValidator();
        $validator($this->createEvent(['publishAutomatically' => false, 'publishAt' => null]));
        $validator($this->createEvent([]));

        $this->addToAssertionCount(2);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return TransitionEvent<object>
     */
    private function createEvent(array $data): TransitionEvent
    {
        return new TransitionEvent(
            new \stdClass(),
            new Marking(),
            new Transition('request_for_review', 'unpublished', 'review'),
            null,
            [ContentWorkflowInterface::WORKFLOW_TRANSITION_REQUEST_DATA_CONTEXT_KEY => $data],
        );
    }
}
