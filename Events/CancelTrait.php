<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\AutomationBundle\Events;

/**
 * Provides implementation for cancelable events.
 */
trait CancelTrait
{
    /**
     * @var bool
     */
    private $canceled = false;

    /**
     * Returns canceled.
     */
    public function isCanceled(): bool
    {
        return $this->canceled;
    }

    /**
     * Cancel Event.
     */
    public function cancel(): self
    {
        $this->canceled = true;
        $this->stopPropagation();

        return $this;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    abstract public function stopPropagation();
}
