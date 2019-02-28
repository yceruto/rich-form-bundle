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

        $searchQuery = $request->query->get('query', '');
        $qb = $this->createSearchQueryBuilder($searchQuery, $em, $options);

        $page = $request->query->get('page', 1);
        $results = $this->createResults($page, $qb, $options);
        $count = \count($results);

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
        $qb = clone $this->createQueryBuilder($em, $options);

        if ('' === $searchQuery) {
            return $qb;
        }

        $isSearchQueryNumeric = \is_numeric($searchQuery);
        $isSearchQuerySmallInteger = \ctype_digit($searchQuery) && $searchQuery >= -32768 && $searchQuery <= 32767;
        $isSearchQueryInteger = \ctype_digit($searchQuery) && $searchQuery >= -2147483648 && $searchQuery <= 2147483647;
        $isSearchQueryUuid = 1 === \preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $searchQuery);
        $lowerSearchQuery = \mb_strtolower($searchQuery);

        $fields = $this->getSearchableFields((array) $options['search_fields'], $options['class'], $qb, $em);

        // SELECT entity + (result_fields? + text? field)?

        $orX = $qb->expr()->orX();
        foreach ($fields as $fieldName => $fieldMapping) {
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
        } else {
            $qb->select('entity')->from($options['class'], 'entity');
        }

        return $qb;
    }

    private function createResults(int $page, QueryBuilder $qb, array $options): array
    {
        $em = $qb->getEntityManager();
        $classMetadata = $em->getClassMetadata($options['class']);
        $idReader = new IdReader($em, $classMetadata);

        $qb->setFirstResult($options['max_results'] * ($page - 1));
        $qb->setMaxResults($options['max_results']);

        $paginator = new Paginator($qb, [] !== $qb->getDQLPart('join'));
        $paginator->setUseOutputWalkers(false);

        $results = [];
        foreach ($paginator as $entity) {
            $data = [
                'id' => $idReader->getIdValue($entity),
                'text' => $options['text'] ? $this->propertyAccessor->getValue($entity, $options['text']) : (string) $entity,
            ];

            if (null !== $options['result_fields']) {
                foreach ((array) $options['result_fields'] as $field) {
                    $value = $this->propertyAccessor->getValue($entity, $field);

                    if (\is_object($value)) {
                        $value = (string) $value;
                    }

                    $data[$field] = $value;
                }
            }

            $results[] = $data;
        }

        return $results;
    }

    private function getSearchableFields(array $fieldNames, string $class, QueryBuilder $qb, EntityManagerInterface $em, string $alias = null): iterable
    {
        $alias = $alias ?? current($qb->getRootAliases());

        if ([] === $fieldNames) {
            $fieldNames = $em->getClassMetadata($class)->fieldNames;
        }

        foreach ($fieldNames as $fieldName) {
            yield from $this->getSearchableField($alias, $fieldName, $class, $qb, $em);
        }
    }

    private function getSearchableField(string $alias, string $fieldName, string $class, QueryBuilder $qb, EntityManagerInterface $em): iterable
    {
        $classMetadata = $em->getClassMetadata($class);

        if ($classMetadata->hasField($fieldName)) {
            // (1) Column/Embedded field

            $fieldMapping = $this->getFieldInfo($fieldName, $classMetadata);

            if ($fieldMapping['is_searchable_field']) {
                yield $alias.'.'.$fieldName => $fieldMapping;
            }
        } elseif ($classMetadata->hasAssociation($fieldName)) {
            // (2) Association field

            if (!\in_array($fieldName, $qb->getAllAliases(), true)) {
                $qb->leftJoin($alias.'.'.$fieldName, $fieldName);
            }

            yield from $this->getSearchableFields([], $classMetadata->getAssociationTargetClass($fieldName), $qb, $em, $fieldName);
        } elseif (false !== \strpos($fieldName, '.')) {
            // (3) Chain fields (e.g. foo.bar.baz)

            // [foo, bar.baz]
            [$firstFieldName, $secondFieldName] = \explode('.', $fieldName, 2);

            if ($classMetadata->hasAssociation($firstFieldName)) {
                if (!\in_array($firstFieldName, $qb->getAllAliases(), true)) {
                    $qb->leftJoin($alias.'.'.$firstFieldName, $firstFieldName);
                }

                yield from $this->getSearchableField($firstFieldName, $secondFieldName, $classMetadata->getAssociationTargetClass($firstFieldName), $qb, $em);
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
