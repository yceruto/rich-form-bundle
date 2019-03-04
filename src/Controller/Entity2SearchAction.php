<?php

namespace Yceruto\Bundle\RichFormBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Form\ChoiceList\IdReader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Yceruto\Bundle\RichFormBundle\Doctrine\Query\DynamicParameter;
use Yceruto\Bundle\RichFormBundle\Form\Type\Entity2Type;

class Entity2SearchAction
{
    private $registry;
    private $propertyAccessor;

    public function __construct(ManagerRegistry $registry, PropertyAccessorInterface $propertyAccessor)
    {
        $this->registry = $registry;
        $this->propertyAccessor = $propertyAccessor;
    }

    public function __invoke(Request $request, string $hash = null)
    {
        $options = $this->getOptions($request, $hash);
        $em = $this->getEntityManager($options);

        $searchQuery = $request->get('query', '');
        $qb = $this->createSearchQueryBuilder($searchQuery, $em, $options);

        $page = $request->get('page', 1);
        $results = $this->createResults($page, $qb, $count, $options);

        return new JsonResponse([
            'results' => $results,
            // For better performance we don't calculate the total records
            // through a database query, instead we do an extra HTTP request
            // (only if the total records is multiple of max_results)
            // then empty results and has_next_page will be "false"
            'has_next_page' => $count > 0 && $count === $options['max_results'],
        ]);
    }

    private function getOptions(Request $request, ?string $hash): array
    {
        if (null === $hash) {
            throw new \RuntimeException('Missing hash value.');
        }

        if (!$request->hasSession()) {
            throw new \RuntimeException('Missing session.');
        }

        $session = $request->getSession();
        $options = $session->get(Entity2Type::SESSION_ID.$hash);

        if (!\is_array($options)) {
            throw new \RuntimeException('Missing options.');
        }

        $options['dynamic_params_values'] = $request->get('dyn', []);

        return $options;
    }

    private function getEntityManager(array $options): EntityManagerInterface
    {
        if (null !== $options['em']) {
            return $this->registry->getManager($options['em']);
        }

        $em = $this->registry->getManagerForClass($options['class']);

        if (null === $em) {
            throw new \RuntimeException(sprintf('Class "%s" seems not to be a managed Doctrine entity. Did you forget to map it?', $options['class']));
        }

        return $em;
    }

    private function createSearchQueryBuilder(string $searchQuery, EntityManagerInterface $em, array $options): QueryBuilder
    {
        $qb = $this->createQueryBuilder($em, $options);

        if ('' === $searchQuery) {
            return $qb;
        }

        $isSearchQueryNumeric = \is_numeric($searchQuery);
        $isSearchQuerySmallInteger = \ctype_digit($searchQuery) && $searchQuery >= -32768 && $searchQuery <= 32767;
        $isSearchQueryInteger = \ctype_digit($searchQuery) && $searchQuery >= -2147483648 && $searchQuery <= 2147483647;
        $isSearchQueryUuid = 1 === \preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $searchQuery);
        $lowerSearchQuery = \mb_strtolower($searchQuery);

        $fields = $this->getFields((array) $options['search_by'], $options['class'], $qb, $em);

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
                $qb->setParameter('numeric_query', 0 + $searchQuery);
            } elseif ($isSearchQueryUuid && $fieldMapping['is_guid_field']) {
                $orX->add("$fieldName = :uuid_query");
                $qb->setParameter('uuid_query', $searchQuery);
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

    private function createQueryBuilder(EntityManagerInterface $em, array $options): QueryBuilder
    {
        $qb = $em->createQueryBuilder();

        if (isset($options['qb_parts'])) {
            foreach ($options['qb_parts']['dql_parts'] as $name => $part) {
                $qb->add($name, $part);
            }

            foreach ($options['qb_parts']['parameters'] as $parameter) {
                $qb->setParameter($parameter['name'], $parameter['value'], $parameter['type']);
            }

            $qb = clone $qb;
        } else {
            $qb->select('entity')->from($options['class'], 'entity');
        }

        if (isset($options['qb_dynamic_params'])) {
            /** @var DynamicParameter $param */
            foreach ($options['qb_dynamic_params'] as $param) {
                $value = $options['dynamic_params_values'][$param->getName()] ?? null;

                if (null === $value || '' === $value) {
                    if ($param->isOptional() && null === $param->getValue()) {
                        continue;
                    }

                    throw new \RuntimeException(sprintf('Missing value for dynamic parameter "%s".', $param->getName()));
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

                $value = null !== $value && '' !== $value ? $value : $param->getValue();
                $qb->setParameter($param->getName(), $value, $param->getType());
            }
        }

        if ($options['order_by']) {
            $rootAlias = current($qb->getRootAliases());
            foreach ($options['order_by'] as $field => $order) {
                foreach ($this->getField($rootAlias, $field, $options['class'], $qb, $em, false) as $fieldName => $_) {
                    $qb->addOrderBy($fieldName, $order);
                }
            }
        }

        return $qb;
    }

    private function createResults(int $page, QueryBuilder $qb, ?int &$count, array $options): array
    {
        $em = $qb->getEntityManager();
        $classMetadata = $em->getClassMetadata($options['class']);
        $idReader = new IdReader($em, $classMetadata);

        $qb->setFirstResult($options['max_results'] * ($page - 1));
        $qb->setMaxResults($options['max_results']);

        $paginator = new Paginator($qb, [] !== $qb->getDQLPart('join'));
        $paginator->setUseOutputWalkers($classMetadata->hasAssociation($classMetadata->getSingleIdentifierFieldName()));

        $count = 0;
        $results = [];
        foreach ($paginator as $entity) {
            ++$count;

            $elem = [
                'id' => $idReader->getIdValue($entity),
                'text' => (string) ($options['text'] ? $this->propertyAccessor->getValue($entity, $options['text']) : $entity),
            ];

            if (null !== $options['result_fields']) {
                foreach ((array) $options['result_fields'] as $fieldName => $fieldValuePath) {
                    $value = $this->propertyAccessor->getValue($entity, $fieldValuePath);

                    if (\is_object($value)) {
                        $value = (string) $value;
                    }

                    $resultFieldName = \is_int($fieldName) ? $fieldValuePath : $fieldName;
                    $elem['data'][$resultFieldName] = $value;
                }
            }

            if (null !== $options['group_by']) {
                $groupText = (string) $this->propertyAccessor->getValue($entity, $options['group_by']);
                $results[$groupText]['text'] = $groupText;
                $results[$groupText]['children'][] = $elem;
            } else {
                $results[] = $elem;
            }
        }

        return array_values($results);
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

                yield from $this->getField($fieldAlias, $secondFieldName, $classMetadata->getAssociationTargetClass($firstFieldName), $qb, $em);
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
