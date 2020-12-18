<?php

namespace Experteam\ApiCrudBundle\Service\Paginator;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Paginator implements PaginatorInterface
{
    const LIMIT_DEFAULT = 50;
    const LIMIT_MAXIMUM = 1000;

    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->managerRegistry = $registry;
    }

    /**
     * @param string $collectionKey
     * @param Request $request
     * @param ServiceEntityRepository $repository
     * @param array $criteria
     * @return array
     */
    public function paginate(string $collectionKey, Request $request, ServiceEntityRepository $repository, array $criteria = []): array
    {
        $queryBuilder = $repository->createQueryBuilder('e');

        $result = $this->queryBuilderForResult($queryBuilder, $request, $criteria)->getQuery()->getResult();
        try {
            $total = $this->queryBuilderForTotal($queryBuilder, $criteria)->getQuery()->getSingleScalarResult();
        } catch (NoResultException | NonUniqueResultException $e) {
            $total = 0;
        }

        return ['total' => $total, $collectionKey => $result];
    }

    /**
     * @param Request $request
     * @param string $entityClass
     * @return array
     */
    private function offsetLimitOrder(Request $request, string $entityClass)
    {
        $offset = $request->query->getInt('offset', 0);
        $limit = $request->query->getInt('limit', self::LIMIT_DEFAULT);
        $order = $request->query->get('order', []);

        if (!is_array($order))
            throw new BadRequestHttpException('Invalid parameter order, incorrect format.');

        $metadata = $this->getClassMetadata($entityClass);
        foreach ($order as $field => $direction) {
            if (!in_array(strtoupper($direction), ['ASC', 'DESC']))
                throw new BadRequestHttpException(sprintf('Invalid parameter order, value "%s" is not allowed', $direction));
            if (!$metadata->hasField($field))
                throw new BadRequestHttpException(sprintf('Invalid parameter order, field "%s" not found or is not allowed', $field));
        }

        if ($limit > self::LIMIT_MAXIMUM)
            $limit = self::LIMIT_MAXIMUM;
        elseif ($limit <= 0)
            $limit = self::LIMIT_DEFAULT;

        $request->query->set('limit', $limit);
        $request->query->set('offset', $offset);
        $request->query->set('order', $order);


        return [$offset, $limit, $order];
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param Request $request
     * @param array $criteria
     * @return QueryBuilder
     */
    public function queryBuilderForResult(QueryBuilder $queryBuilder, Request $request, array $criteria = []): QueryBuilder
    {
        $entityClass = $queryBuilder->getDQLPart('from')[0]->getFrom();
        [$offset, $limit, $order] = $this->offsetLimitOrder($request, $entityClass);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        $queryBuilderResult = clone $queryBuilder;
        $queryBuilderResult
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        foreach ($criteria as $field => $value) {
            $queryBuilderResult
                ->andWhere(sprintf('%s.%s = :%s', $rootAlias, $field, $field))
                ->setParameter($field, $value);
        }

        foreach($order as $field => $direction) {
            $queryBuilderResult
                ->addOrderBy(sprintf('%s.%s', $rootAlias, $field), strtoupper($direction));
        }

        return $queryBuilderResult;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array $criteria
     * @return QueryBuilder
     */
    public function queryBuilderForTotal(QueryBuilder $queryBuilder, array $criteria = []): QueryBuilder
    {
        $entityClass = $queryBuilder->getDQLPart('from')[0]->getFrom();
        $metadata = $this->getClassMetadata($entityClass);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        $queryBuilderCount = clone $queryBuilder;
        $queryBuilderCount->resetDQLPart('select');
        $queryBuilderCount->select(sprintf('count(%s.%s)', $rootAlias, $metadata->getSingleIdentifierFieldName()));

        foreach ($criteria as $field => $value) {
            $queryBuilderCount
                ->andWhere(sprintf('%s.%s = :%s', $rootAlias, $field, $field))
                ->setParameter($field, $value);
        }

        return $queryBuilderCount;
    }

    /**
     * @param string $className
     * @return ClassMetadata
     */
    protected function getClassMetadata(string $className): ClassMetadata
    {
        return $this->managerRegistry->getManagerForClass($className)->getClassMetadata($className);
    }
}