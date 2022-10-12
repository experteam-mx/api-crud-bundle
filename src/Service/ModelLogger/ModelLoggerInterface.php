<?php

namespace Experteam\ApiCrudBundle\Service\ModelLogger;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Symfony\Component\Security\Core\User\UserInterface;

interface ModelLoggerInterface
{
    /**
     * @param OnFlushEventArgs $eventArgs
     * @param UserInterface $user
     * @return void
     */
    public function dispatchChanges(OnFlushEventArgs $eventArgs, UserInterface $user): void;

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