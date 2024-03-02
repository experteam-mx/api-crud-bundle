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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Paginator implements PaginatorInterface
{
    const LIMIT_DEFAULT = 50;
    const LIMIT_MAXIMUM = 1000;
    const NESTED_SEPARATOR = '@';
    const AND = 'AND';
    const OR = 'OR';

    protected EntityManagerInterface $entityManager;
    protected Request $request;
    protected int $incrementAlias = 0;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->request = $requestStack->getCurrentRequest();
    }

    public function paginate(string $collectionKey, Request $request, ServiceEntityRepository $repository, array $criteria = []): array
    {
        $queryBuilder = $repository->createQueryBuilder('e');
        $queryBuilderForResult = $this->queryBuilderForResult($queryBuilder, $request, $criteria);
        $result = $this->queryForTranslatable($queryBuilderForResult, $request)->getResult();

        try {
            $queryBuilderForTotal = $this->queryBuilderForTotal($queryBuilder, $criteria);
            $total = intval($this->queryForTranslatable($queryBuilderForTotal, $request)->getSingleScalarResult());
        } catch (NoResultException|NonUniqueResultException) {
            $total = 0;
        }

        return ['total' => $total, $collectionKey => $result];
    }

    public function queryBuilderForResult(QueryBuilder $queryBuilder, Request $request, array $criteria = []): QueryBuilder
    {
        $entityClass = $queryBuilder->getDQLPart('from')[0]->getFrom();
        [$offset, $limit, $order] = $this->offsetLimitOrder($request, $entityClass);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        $queryBuilderResult = clone $queryBuilder;
        $queryBuilderResult
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $this->addCriteria($queryBuilderResult, $criteria, $rootAlias);

        $this->addOrder($queryBuilderResult, $order, $rootAlias);

        return $queryBuilderResult;
    }

    private function offsetLimitOrder(Request $request, string $entityClass): array
    {
        $offset = $request->query->getInt('offset');
        $limit = $request->query->getInt('limit', self::LIMIT_DEFAULT);
        $order = ($request->query->has('order') ? $request->query->all('order') : []);

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

    protected function validateField(string $field, string $entityClass): void
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

    protected function getClassMetadata(string $className): ClassMetadata
    {
        return $this->entityManager->getClassMetadata($className);
    }

    protected function isFieldNested(string $field): bool
    {
        return (str_contains($field, self::NESTED_SEPARATOR));
    }

    protected function splitFieldParts(string $field): array
    {
        $parts = explode(self::NESTED_SEPARATOR, $field);
        $field = array_pop($parts);
        return [$parts, $field];
    }

    protected function addCriteria(QueryBuilder $queryBuilder, array $criteria, string $rootAlias): void
    {
        $criteria = $this->sortCriteriaByRequest($criteria);
        $dql = '';
        $parameters = [];
        $whereGrouped = [];

        foreach ($criteria as $field => $value) {
            [$alias, $_field] = $this->isFieldNested($field)
                ? $this->getNestedAliasField($queryBuilder, $field, $rootAlias, true)
                : [$rootAlias, $field];

            if (is_array($value)) {
                $filter = array_key_first($value);
                $v = $value[$filter];

                if (is_numeric($filter))
                    $whereGrouped[$filter][] = [$alias, $_field, $v];
                elseif (!is_array($v)) {
                    [$operator, $expression, $parameter, $value] = $this->getFilterWhere($filter, $alias, $_field, $v);
                    $this->updateDQL($dql, $operator, $expression);
                    $parameters[$parameter] = $value;
                } else
                    throw new BadRequestHttpException(sprintf('Incorrect format for "%s" parameter', $field));
            } else {
                $parameter = "{$alias}_$_field";
                $this->updateDQL($dql, self::AND, "$alias.$_field = :$parameter");
                $parameters[$parameter] = $value;
            }
        }

        if (!empty($whereGrouped))
            foreach ($whereGrouped as $group) {
                $_dql = '';

                foreach ($group as [$alias, $_field, $value]) {
                    if (is_array($value)) {
                        $filter = array_key_first($value);
                        $v = $value[$filter];
                    } else {
                        $filter = 'eq';
                        $v = $value;
                    }

                    [$operator, $expression, $parameter, $value] = $this->getFilterWhere($filter, $alias, $_field, $v);
                    $this->updateDQL($_dql, $operator, $expression);
                    $parameters[$parameter] = $value;
                }

                $this->updateDQL($dql, self::AND, empty($dql) ? $_dql : "($_dql)");
            }

        if (!empty($dql)) {
            $queryBuilder->andWhere($dql);

            foreach ($parameters as $key => $value) {
                if (!is_null($value)) {
                    $queryBuilder->setParameter($key, $value);
                }
            }
        }
    }

    protected function sortCriteriaByRequest(array $criteria): array
    {
        $ordered = [];

        foreach (array_keys($this->request->query->all()) as $key) {
            if (array_key_exists($key, $criteria))
                $ordered[$key] = $criteria[$key];
        }

        return $ordered;
    }

    protected function getNestedAliasField(QueryBuilder $queryBuilder, string $field, string $rootAlias, bool $leftJoin = false): array
    {
        [$associations, $field] = $this->splitFieldParts($field);

        $alias = $this->addJoinForAssociations($queryBuilder, $associations, $rootAlias, $leftJoin);

        return [$alias, $field];
    }

    protected function addJoinForAssociations(QueryBuilder $queryBuilder, array $associations, string $rootAlias, bool $leftJoin = false): string
    {
        $parentAlias = $alias = $rootAlias;

        foreach ($associations as $association) {
            $alias = sprintf('%s_a%d', $association, $this->incrementAlias);
            $join = "$parentAlias.$association";

            if (!$this->joinExists($queryBuilder, $alias, $association, $rootAlias)) {
                if ($leftJoin)
                    $queryBuilder->leftJoin($join, $alias);
                else
                    $queryBuilder->innerJoin($join, $alias);
            }

            $parentAlias = $alias;
            $this->incrementAlias++;
        }

        return $alias;
    }

    protected function joinExists(QueryBuilder $queryBuilder, string $alias, string $association, string $rootAlias): bool
    {
        $dqlParts = $queryBuilder->getDQLPart('join');

        foreach ($dqlParts[$rootAlias] ?? [] as $join)
            if (sprintf('%s.%s', $alias, $association) === $join->getJoin())
                return true;

        return false;
    }

    protected function getFilterWhere(string $filter, string $alias, string $field, ?string $value): array
    {
        $parameter = "{$alias}_$field";

        switch ($filter) {
            case 'lk':
                return [self::AND, "$alias.$field LIKE :$parameter", $parameter, "%$value%"];
            case 'olk':
                return [self::OR, "$alias.$field LIKE :$parameter", $parameter, "%$value%"];
            case 'gt':
                return [self::AND, "$alias.$field > :$parameter", $parameter, $value];
            case 'ogt':
                return [self::OR, "$alias.$field > :$parameter", $parameter, $value];
            case 'gte':
                return [self::AND, "$alias.$field >= :$parameter", $parameter, $value];
            case 'ogte':
                return [self::OR, "$alias.$field >= :$parameter", $parameter, $value];
            case 'lt':
                return [self::AND, "$alias.$field < :$parameter", $parameter, $value];
            case 'olt':
                return [self::OR, "$alias.$field < :$parameter", $parameter, $value];
            case 'lte':
                return [self::AND, "$alias.$field <= :$parameter", $parameter, $value];
            case 'olte':
                return [self::OR, "$alias.$field <= :$parameter", $parameter, $value];
            case 'eq':
            case 'oeq':
                return [(($filter === 'eq') ? self::AND : self::OR), "$alias.$field " . (is_null($value) ? 'IS NULL' : "= :$parameter"), $parameter, $value];
            case 'neq':
            case 'oneq':
                return [(($filter === 'neq') ? self::AND : self::OR), "$alias.$field " . (is_null($value) ? 'IS NOT NULL' : "<> :$parameter"), $parameter, $value];
            default:
                throw new BadRequestHttpException(sprintf('Invalid filter "%s"', $filter));
        }
    }

    protected function updateDQL(string &$dql, string $operator, string $expression): void
    {
        $dql .= empty($dql) ? "$expression" : " $operator $expression";
    }

    protected function addOrder(QueryBuilder $queryBuilder, array $order, string $rootAlias): void
    {
        foreach ($order as $field => $direction) {
            [$alias, $_field] = $this->isFieldNested($field)
                ? $this->getNestedAliasField($queryBuilder, $field, $rootAlias, true)
                : [$rootAlias, $field];

            $queryBuilder
                ->addOrderBy("$alias.$_field", strtoupper($direction));
        }
    }

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

    public function queryBuilderForTotal(QueryBuilder $queryBuilder, array $criteria = []): QueryBuilder
    {
        $entityClass = $queryBuilder->getDQLPart('from')[0]->getFrom();
        $metadata = $this->getClassMetadata($entityClass);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        $queryBuilderCount = clone $queryBuilder;
        $queryBuilderCount->resetDQLPart('select');
        $queryBuilderCount->select(sprintf('count(%s.%s)', $rootAlias, $metadata->getSingleIdentifierFieldName()));

        $this->addCriteria($queryBuilderCount, $criteria, $rootAlias);

        return $queryBuilderCount;
    }
}
