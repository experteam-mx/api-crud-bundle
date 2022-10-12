<?php

namespace Experteam\ApiCrudBundle\Service\ModelLogger;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Experteam\ApiBaseBundle\Security\User;

interface ModelLoggerInterface
{
    /**
     * @param OnFlushEventArgs $eventArgs
     * @param User $user
     * @return void
     */
    public function dispatchChanges(OnFlushEventArgs $eventArgs, User $user): void;

    /**
     * @param array $current
     * @param array $changes
     * @param string $className
     * @param array $user
     * @return void
     */
    public function logChanges(
        array $current,
        array $changes,
        string $className,
        array $user
    ): void;
}