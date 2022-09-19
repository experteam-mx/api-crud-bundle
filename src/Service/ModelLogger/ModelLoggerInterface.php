<?php

namespace Experteam\ApiCrudBundle\Service\ModelLogger;

use Doctrine\ORM\Event\OnFlushEventArgs;

interface ModelLoggerInterface
{
    /**
     * @param array $current
     * @param array $changes
     * @param string $className
     * @return void
     */
    public function logEntityChanges(array $current, array $changes, string $className): void;

    /**
     * @param OnFlushEventArgs $eventArgs
     * @return void
     */
    public function dispatchEntityChanges(OnFlushEventArgs $eventArgs): void;
}