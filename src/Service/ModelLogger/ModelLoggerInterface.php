<?php

namespace Experteam\ApiCrudBundle\Service\ModelLogger;

use Doctrine\ORM\UnitOfWork;
use Experteam\ApiBaseBundle\Service\ELKLogger\ELKLogger;

interface ModelLoggerInterface
{
    /**
     * @param UnitOfWork $uow
     * @return void
     */
    public function logEntity(UnitOfWork $uow): void;
}