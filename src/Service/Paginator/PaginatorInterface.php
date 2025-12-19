<?php

namespace Experteam\ApiCrudBundle\Service\Paginator;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

interface PaginatorInterface
{
    public function paginate(string $collectionKey, ServiceEntityRepository $repository, array $criteria = []): array;

    public function queryBuilderForResult(QueryBuilder $queryBuilder, array $criteria = []): QueryBuilder;

    public function queryForTranslatable(QueryBuilder $queryBuilder): Query;

    public function queryBuilderForTotal(QueryBuilder $queryBuilder, array $criteria = []): QueryBuilder;
}
