<?php

namespace Experteam\ApiCrudBundle\Service\Paginator;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

interface PaginatorInterface
{
    /**
     * @param string $collectionKey
     * @param Request $request
     * @param ServiceEntityRepository $repository
     * @param array $criteria
     * @return array
     */
    public function paginate(string $collectionKey, Request $request, ServiceEntityRepository $repository, array $criteria = []): array;

    /**
     * @param QueryBuilder $queryBuilder
     * @param Request $request
     * @param array $criteria
     * @return QueryBuilder
     */
    public function queryBuilderForResult(QueryBuilder $queryBuilder, Request $request, array $criteria = []): QueryBuilder;

    /**
     * @param QueryBuilder $queryBuilder
     * @param array $criteria
     * @return QueryBuilder
     */
    public function queryBuilderForTotal(QueryBuilder $queryBuilder, array $criteria = []): QueryBuilder;

    /**
     * @param QueryBuilder $queryBuilder
     * @param Request $request
     * @return Query
     */
    public function queryForTranslatable(QueryBuilder $queryBuilder, Request $request): Query;
}