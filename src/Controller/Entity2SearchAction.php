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
        $searchQuery = $request->query->get('query');

        if (null === $searchQuery || '' === $searchQuery) {
            return new JsonResponse([]);
        }

        $options = $this->getOptions($request, $hash);
        $em = $this->getEntityManager($options);
        $qb = $this->createSearchQueryBuilder($searchQuery, $em, $options);
        $results = $this->createResults($request->query->get('page', 1), $qb, $options);

        return new JsonResponse([
            'results' => $results,
            // @FIXME
            'has_next_page' => \count($results) === $options['max_results'],
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
        $qb = $this->createQueryBuilder($em, $options);

        $isSearchQueryNumeric = \is_numeric($searchQuery);
        $isSearchQuerySmallInteger = \ctype_digit($searchQuery) && $searchQuery >= -32768 && $searchQuery <= 32767;
        $isSearchQueryInteger = \ctype_digit($searchQuery) && $searchQuery >= -2147483648 && $searchQuery <= 2147483647;
        $isSearchQueryUuid = 1 === \preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $searchQuery);
        $lowerSearchQuery = \mb_strtolower($searchQuery);

        /* @var ClassMetadata $classMetadata */
        $classMetadata = $em->getClassMetadata($options['class']);

        if (null === $options['search_fields']) {
            // ... search by all fields (use Doctrine metadata)
        } else {
            // ... search by configured fields (use Doctrine metadata)
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
        $classMetadata = $qb->getEntityManager()->getClassMetadata($options['class']);
        $idReader = new IdReader($options['em'], $classMetadata);

        $qb->setFirstResult($page * $options['max_results'] - $options['max_results']);
        $qb->setMaxResults($options['max_results']);

        $paginator = new Paginator($qb, [] !== $qb->getDQLPart('join'));
        $paginator->setUseOutputWalkers(false);

        $results = [];
        foreach ($paginator as $entity) {
            $data = [
                'id' => $idReader->getIdValue($entity),
                'text' => (string) $entity,
            ];

            if (null === $options['result_fields']) {
                foreach ((array) $options['result_fields'] as $field) {
                    $data[$field] = $this->propertyAccessor->getValue($entity, $field);
                }
            }

            $results[] = $data;
        }

        return $results;
    }
}
