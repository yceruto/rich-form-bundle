<?php

namespace Yceruto\Bundle\RichFormBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Yceruto\Bundle\RichFormBundle\Request\SearchOptions;
use Yceruto\Bundle\RichFormBundle\Request\SearchRequest;

abstract class AbstractSearchAction
{
    private $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function __invoke(Request $request)
    {
        $searchRequest = new SearchRequest($request);
        $searchOptions = $searchRequest->getOptions();

        if (null !== $name = $searchOptions->getEntityManagerName()) {
            $em = $this->registry->getManager($name);
        } else {
            $em = $this->registry->getManagerForClass($searchOptions->getEntityClass());

            if (null === $em) {
                throw new \RuntimeException(sprintf('Class "%s" seems not to be a managed Doctrine entity. Did you forget to map it?', $searchOptions->getEntityClass()));
            }
        }

        /** @var EntityManagerInterface $em */
        $qb = $this->createSearchQueryBuilder($em, $searchRequest);
        $paginator = $this->createPaginator($qb, $searchRequest->getPage(), $searchOptions);
        $results = $this->createResults($paginator, $searchOptions);

        return new JsonResponse($results);
    }

    abstract protected function createResults(Paginator $paginator, SearchOptions $options): array;

    private function createPaginator(QueryBuilder $qb, int $page, SearchOptions $options): Paginator
    {
        $maxResults = $options->getMaxResults();
        $em = $qb->getEntityManager();
        $classMetadata = $em->getClassMetadata($options->getEntityClass());

        $qb->setFirstResult($maxResults * ($page - 1));
        $qb->setMaxResults($maxResults);

        $paginator = new Paginator($qb, [] !== $qb->getDQLPart('join'));
        $paginator->setUseOutputWalkers($classMetadata->hasAssociation($classMetadata->getSingleIdentifierFieldName()));

        return $paginator;
    }

    private function createQueryBuilder(EntityManagerInterface $em, SearchOptions $options): QueryBuilder
    {
        $qb = $em->createQueryBuilder();
        $class = $options->getEntityClass();

        if (null === $qbParts = $options->getQueryBuilderParts()) {
            $qb->select('entity')->from($class, 'entity');
        } else {
            foreach ($qbParts['dql_parts'] as $name => $part) {
                $qb->add($name, $part);
            }

            foreach ($qbParts['parameters'] as $parameter) {
                $qb->setParameter($parameter['name'], $parameter['value'], $parameter['type']);
            }

            $qb = clone $qb;
        }

        $dynamicParamsValues = $options->getQueryBuilderDynamicParamsValues();
        foreach ($options->getQueryBuilderDynamicParams() as $param) {
            $value = $dynamicParamsValues[$param->getName()] ?? null;

            if (null === $value) {
                throw new \RuntimeException(sprintf('Missing value for dynamic parameter "%s".', $param->getName()));
            }

            if ('' === $value) {
                if ($param->isOptional() && null === $param->getValue()) {
                    continue;
                }

                $value = $param->getValue();
            }

            foreach ($param->getWhere() as $condition) {
                $op = key($condition);
                $where = current($condition);
                if ('AND' === $op) {
                    $qb->andWhere($where);
                } else {
                    $qb->orWhere($where);
                }
            }

            $qb->setParameter($param->getName(), $value, $param->getType());
        }

        if (null !== $orderBy = $options->getOrderBy()) {
            $rootAlias = current($qb->getRootAliases());
            foreach ($orderBy as $field => $order) {
                foreach ($this->getField($rootAlias, $field, $class, $qb, $em, false) as $fieldName => $_) {
                    $qb->addOrderBy($fieldName, $order);
                }
            }
        }

        return $qb;
    }

    private function createSearchQueryBuilder(EntityManagerInterface $em, SearchRequest $request): QueryBuilder
    {
        $options = $request->getOptions();
        $qb = $this->createQueryBuilder($em, $options);

        $term = $request->getTerm();
        if ('' === $term) {
            return $qb;
        }

        $isSearchQueryNumeric = \is_numeric($term);
        $isSearchQuerySmallInteger = \ctype_digit($term) && $term >= -32768 && $term <= 32767;
        $isSearchQueryInteger = \ctype_digit($term) && $term >= -2147483648 && $term <= 2147483647;
        $isSearchQueryUuid = 1 === \preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $term);
        $lowerSearchQuery = \mb_strtolower($term);

        $fields = $this->getFields($options->getSearchBy(), $options->getEntityClass(), $qb, $em);

        // SELECT entity, result_fields?

        $orX = $qb->expr()->orX();
        foreach ($fields as $fieldName => $fieldMapping) {
            if (!$fieldMapping['is_searchable_field']) {
                continue;
            }

            // this complex condition is needed to avoid issues on PostgreSQL databases
            if (
                ($fieldMapping['is_small_integer_field'] && $isSearchQuerySmallInteger) ||
                ($fieldMapping['is_integer_field'] && $isSearchQueryInteger) ||
                ($fieldMapping['is_numeric_field'] && $isSearchQueryNumeric)
            ) {
                $orX->add("$fieldName = :numeric_query");
                // adding '0' turns the string into a numeric value
                $qb->setParameter('numeric_query', 0 + $term);
            } elseif ($isSearchQueryUuid && $fieldMapping['is_guid_field']) {
                $orX->add("$fieldName = :uuid_query");
                $qb->setParameter('uuid_query', $term);
            } elseif ($fieldMapping['is_text_field']) {
                $orX->add("LOWER($fieldName) LIKE :fuzzy_query");
                $qb->setParameter('fuzzy_query', '%'.$lowerSearchQuery.'%');

                $orX->add("LOWER($fieldName) IN (:words_query)");
                $qb->setParameter('words_query', \explode(' ', $lowerSearchQuery));
            }
        }

        if ($orX->count() > 0) {
            $qb->andWhere($orX);
        }

        return $qb;
    }

    private function getFields(array $fieldNames, string $class, QueryBuilder $qb, EntityManagerInterface $em, string $alias = null): iterable
    {
        $alias = $alias ?? current($qb->getRootAliases());

        if ([] === $fieldNames) {
            $fieldNames = $em->getClassMetadata($class)->fieldNames;
        }

        foreach ($fieldNames as $fieldName) {
            yield from $this->getField($alias, $fieldName, $class, $qb, $em);
        }
    }

    private function getField(string $alias, string $fieldName, string $class, QueryBuilder $qb, EntityManagerInterface $em, bool $allowAssoc = true): iterable
    {
        $classMetadata = $em->getClassMetadata($class);

        if ($classMetadata->hasField($fieldName)) {
            // (1) Column/Embedded field

            $fieldMapping = $this->getFieldInfo($fieldName, $classMetadata);

            yield $alias.'.'.$fieldName => $fieldMapping;
        } elseif ($allowAssoc && $classMetadata->hasAssociation($fieldName)) {
            // (2) Association field

            $fieldAlias = $fieldName;
            if (!\in_array($fieldName, $qb->getAllAliases(), true)) {
                $qb->leftJoin($alias.'.'.$fieldName, $fieldAlias);
            } elseif ($alias === $fieldName && [$alias] === $qb->getAllAliases()) {
                $qb->leftJoin($alias.'.'.$fieldName, $fieldAlias .= '1');
            }

            yield from $this->getFields([], $classMetadata->getAssociationTargetClass($fieldName), $qb, $em, $fieldAlias);
        } elseif (false !== \strpos($fieldName, '.')) {
            // (3) Chain fields (e.g. foo.bar.baz)

            // [foo, bar.baz]
            [$firstFieldName, $secondFieldName] = \explode('.', $fieldName, 2);

            if ($classMetadata->hasAssociation($firstFieldName)) {
                $fieldAlias = $firstFieldName;
                if (!\in_array($firstFieldName, $qb->getAllAliases(), true)) {
                    $qb->leftJoin($alias.'.'.$firstFieldName, $fieldAlias);
                } elseif ($alias === $firstFieldName && [$alias] === $qb->getAllAliases()) {
                    $qb->leftJoin($alias.'.'.$firstFieldName, $fieldAlias .= '1');
                }

                yield from $this->getField($fieldAlias, $secondFieldName, $classMetadata->getAssociationTargetClass($firstFieldName), $qb, $em, $allowAssoc);
            }
        }

        // Unknown fields are ignored
        return [];
    }

    private function getFieldInfo(string $fieldName, ClassMetadata $classMetadata): array
    {
        $type = $classMetadata->getTypeOfField($fieldName);

        $isSmallIntegerField = 'smallint' === $type;
        $isIntegerField = 'integer' === $type;
        $isNumericField = \in_array($type, ['number', 'bigint', 'decimal', 'float']);
        $isGuidField = \in_array($type, ['guid', 'uuid']);
        $isTextField = \in_array($type, ['string', 'text', 'citext', 'array', 'simple_array']);
        $isSearchableField = $isSmallIntegerField || $isIntegerField || $isNumericField || $isTextField || $isGuidField;

        return [
            'is_searchable_field' => $isSearchableField,
            'is_small_integer_field' => $isSmallIntegerField,
            'is_integer_field' => $isIntegerField,
            'is_numeric_field' => $isNumericField,
            'is_guid_field' => $isGuidField,
            'is_text_field' => $isTextField,
        ];
    }
}
