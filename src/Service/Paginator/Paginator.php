<?php

namespace Experteam\ApiCrudBundle\Service\Paginator;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Gedmo\Translatable\Translatable;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Paginator implements PaginatorInterface
{
    const LIMIT_DEFAULT = 50;
    const LIMIT_MAXIMUM = 1000;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
        $result = $this->queryForTranslatable(
            $this->queryBuilderForResult($queryBuilder, $request, $criteria),
            $request
        )->getResult();

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
    private function offsetLimitOrder(Request $request, string $entityClass): array
    {
        $offset = $request->query->getInt('offset');
        $limit = $request->query->getInt('limit', self::LIMIT_DEFAULT);
        $order = $request->query->get('order', []);

        if (!is_array($order))
            throw new BadRequestHttpException('Invalid parameter order, incorrect format.');

        foreach ($order as $field => $direction) {
            if (!in_array(strtoupper($direction), ['ASC', 'DESC']))
                throw new BadRequestHttpException(sprintf('Invalid parameter order, value "%s" is not allowed', $direction));

            $this->validateField($field, $entityClass);
        }

        if ($limit > self::LIMIT_MAXIMUM) {
            $limit = self::LIMIT_MAXIMUM;
        } elseif ($limit <= 0) {
            $limit = self::LIMIT_DEFAULT;
        }

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

        foreach ($order as $field => $direction)
            $this->addOrderBy($queryBuilderResult, $field, $direction, $rootAlias);

        return $queryBuilderResult;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param Request $request
     * @return Query
     */
    public function queryForTranslatable(QueryBuilder $queryBuilder, Request $request): Query
    {
        $entityClass = $queryBuilder->getDQLPart('from')[0]->getFrom();
        $query = $queryBuilder->getQuery();

        if (in_array(Translatable::class, array_values(class_implements($entityClass)))) {
            $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, 'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker');
            $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $request->query->get('locale'));
            $query->setHint(TranslatableListener::HINT_FALLBACK, 1);
        }

        return $query;
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
        return $this->entityManager->getClassMetadata($className);
    }

    /**
     * @param string $field
     * @param string $entityClass
     */
    protected function validateField(string $field, string $entityClass)
    {
        $metadata = $this->getClassMetadata($entityClass);
        $_field = $field;

        if ($this->isFieldNested($field)) {
            [$associations, $_field] = $this->splitFieldParts($field);

            foreach ($associations as $association) {
                if (!$metadata->hasAssociation($association))
                    throw new BadRequestHttpException(sprintf('Invalid field, "%s" not found', $field));

                $metadata = $this->getClassMetadata($metadata->getAssociationTargetClass($association));
            }
        }

        if (!$metadata->hasField($_field))
            throw new BadRequestHttpException(sprintf('Invalid field, "%s" not found', $field));
    }

    /**
     * @param string $field
     * @return array [associations, field]
     */
    protected function splitFieldParts(string $field)
    {
        $parts = explode('.', $field);
        $field = array_pop($parts);
        return [$parts, $field];
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function isFieldNested(string $field): bool
    {
        return (strpos($field, '.') !== false);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array $associations
     * @param string $rootAlias
     * @param bool $leftJoin
     * @return string
     */
    protected function addJoinForAssociations(QueryBuilder $queryBuilder, array $associations, string $rootAlias, bool $leftJoin = false)
    {
        $parentAlias = $alias = $rootAlias;
        $incrementAlias = 0;

        foreach ($associations as $association) {
            $alias = sprintf('%s_a%d', $association, $incrementAlias);
            $join = "$parentAlias.$association";

            if (!$this->joinExists($queryBuilder, $alias, $association, $rootAlias)) {
                if ($leftJoin)
                    $queryBuilder->leftJoin($join, $alias);
                else
                    $queryBuilder->innerJoin($join, $alias);
            }

            $parentAlias = $alias;
        }

        return $alias;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $alias
     * @param string $association
     * @param string $rootAlias
     * @return bool
     */
    protected function joinExists(QueryBuilder $queryBuilder, string $alias, string $association, string $rootAlias)
    {
        $dqlParts = $queryBuilder->getDQLPart('join');

        foreach ($dqlParts[$rootAlias] ?? [] as $join)
            if (sprintf('%s.%s', $alias, $association) === $join->getJoin())
                return true;

        return false;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $field
     * @param string $direction
     * @param string $rootAlias
     */
    protected function addOrderBy(QueryBuilder $queryBuilder, string $field, string $direction, string $rootAlias)
    {
        $alias = $rootAlias;

        if ($this->isFieldNested($field)) {
            [$associations, $field] = $this->splitFieldParts($field);
            $alias = $this->addJoinForAssociations($queryBuilder, $associations, $rootAlias, true);
        }

        $queryBuilder
            ->addOrderBy(sprintf('%s.%s', $alias, $field), strtoupper($direction));
    }
}