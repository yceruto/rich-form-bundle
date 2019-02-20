<?php

namespace Yceruto\Bundle\RichFormBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Yceruto\Bundle\RichFormBundle\Form\Type\Entity2Type;

class Entity2SearchAction
{
    private $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function __invoke(Request $request, string $hash = null)
    {
        try {
            $options = $this->getOptions($request, $hash);
            $em = $this->getEntityManager($options);
        } catch (\RuntimeException $e) {
            return new JsonResponse([['id' => 0, 'text' => 'Error!']]);
        }

        $qb = $this->createQueryBuilder($em, $options);

        $searchQuery = $request->query->get('query');
        if (null === $options['search_fields']) {
            // ... search by all fields (use Doctrine metadata)
        } else {
            // ... search by configured fields (use Doctrine metadata)
        }

        $page = $request->query->get('page') ?: 1;
        $results = $this->createResults($page, $qb, $options);

        return new JsonResponse([
            'results' => $results,
            'has_next_page' => \count($results) === $options['max_results'],
        ]);
    }

    private function getOptions(Request $request, string $hash): array
    {
        if (null === $hash) {
            throw new \RuntimeException('Missing hash value.');
        }

        $session = $request->getSession();

        if (null === $session) {
            throw new \RuntimeException('Missing session.');
        }

        $options = $session->get(Entity2Type::SESSION_ID.$hash);

        if (!\is_array($options)) {
            throw new \RuntimeException('Invalid options.');
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
        $qb->setFirstResult($page * $options['max_results'] - $options['max_results']);
        $qb->setMaxResults($options['max_results']);

        $paginator = new Paginator($qb, [] !== $qb->getDQLPart('join'));
        $paginator->setUseOutputWalkers(false);

        $results = [];
        foreach ($paginator as $entity) {
            $results[] = [
                'id' => $entity->getId(),
                'text' => (string) $entity,
            ];

            // add custom fields for custom result template
        }

        return $results;
    }
}
