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

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var int
     */
    protected $incrementAlias = 0;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->request = $requestStack->getCurrentRequest();
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
            $total = intval($this->queryBuilderForTotal($queryBuilder, $criteria)->getQuery()->getSingleScalarResult());
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

        $this->addCriteria($queryBuilderResult, $criteria, $rootAlias);

        $this->addOrder($queryBuilderResult, $order, $rootAlias);

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

        $this->addCriteria($queryBuilderCount, $criteria, $rootAlias);

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
        $parts = explode(self::NESTED_SEPARATOR, $field);
        $field = array_pop($parts);
        return [$parts, $field];
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function isFieldNested(string $field): bool
    {
        return (strpos($field, self::NESTED_SEPARATOR) !== false);
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
     * @param string $rootAlias
     * @param bool $leftJoin
     * @return array [alias, field]
     */
    protected function getNestedAliasField(QueryBuilder $queryBuilder, string $field, string $rootAlias, bool $leftJoin = false): array
    {
        [$associations, $field] = $this->splitFieldParts($field);

        $alias = $this->addJoinForAssociations($queryBuilder, $associations, $rootAlias, $leftJoin);

        return [$alias, $field];
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array $order
     * @param string $rootAlias
     */
    protected function addOrder(QueryBuilder $queryBuilder, array $order, string $rootAlias)
    {
        foreach ($order as $field => $direction) {
            [$alias, $_field] = $this->isFieldNested($field)
                ? $this->getNestedAliasField($queryBuilder, $field, $rootAlias, true)
                : [$rootAlias, $field];

            $queryBuilder
                ->addOrderBy("$alias.$_field", strtoupper($direction));
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array $criteria
     * @param string $rootAlias
     */
    protected function addCriteria(QueryBuilder $queryBuilder, array $criteria, string $rootAlias)
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

    /**
     * @param array $criteria
     * @return array
     */
    protected function sortCriteriaByRequest(array $criteria)
    {
        $ordered = [];

        foreach(array_keys($this->request->query->all()) as $key) {
            if (array_key_exists($key, $criteria))
                $ordered[$key] = $criteria[$key];
        }

        return $ordered;
    }

    /**
     * @param string $dql
     * @param string $operator
     * @param string $expression
     */
    protected function updateDQL(string &$dql, string $operator, string $expression)
    {
        $dql .= empty($dql) ? "$expression" : " $operator $expression";
    }

    /**
     * @param string $filter
     * @param string $alias
     * @param string $field
     * @param string|null $value
     * @return string[]
     */
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
}
