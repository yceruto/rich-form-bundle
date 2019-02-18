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
            $context = $this->getContext($request, $hash);
            $em = $this->getEntityManager($context);
        } catch (\RuntimeException $e) {
            return new JsonResponse([['id' => 0, 'text' => 'Error!']]);
        }

        $qb = $this->createQueryBuilder($em, $context);

        $searchQuery = $request->query->get('query');
        if (null === $context['search_fields']) {
            // ... search by all fields (use Doctrine metadata)
        } else {
            // ... search by configured fields (use Doctrine metadata)
        }

        $page = $request->query->get('page') ?: 1;
        $results = $this->createResults($page, $qb, $context);

        return new JsonResponse([
            'results' => $results,
            'has_next_page' => \count($results) === $context['max_results'],
        ]);
    }

    private function getContext(Request $request, string $hash): array
    {
        if (null === $hash) {
            throw new \RuntimeException('Missing hash value.');
        }

        $session = $request->getSession();

        if (null === $session) {
            throw new \RuntimeException('Missing session.');
        }

        $context = $session->get(Entity2Type::SESSION_ID.$hash);

        if (!\is_array($context)) {
            throw new \RuntimeException('Invalid context.');
        }

        return $context;
    }

    private function getEntityManager(array $context): EntityManagerInterface
    {
        if (null !== $context['em']) {
            return $this->registry->getManager($context['em']);
        }

        $em = $this->registry->getManagerForClass($context['class']);

        if (null === $em) {
            throw new \RuntimeException(sprintf('Class "%s" seems not to be a managed Doctrine entity. Did you forget to map it?', $context['class']));
        }

        return $em;
    }

    private function createQueryBuilder(EntityManagerInterface $em, array $context): QueryBuilder
    {
        $qb = $em->createQueryBuilder();

        if (isset($context['qb_parts'])) {
            foreach ($context['qb_parts']['dql_parts'] as $name => $part) {
                $qb->add($name, $part);
            }

            foreach ($context['qb_parts']['parameters'] as $parameter) {
                $qb->setParameter($parameter['name'], $parameter['value'], $parameter['type']);
            }
        } else {
            $qb->select('entity')->from($context['class'], 'entity');
        }

        return $qb;
    }

    private function createResults(int $page, QueryBuilder $qb, array $context): array
    {
        $qb->setFirstResult($page * $context['max_results'] - $context['max_results']);
        $qb->setMaxResults($context['max_results']);

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
