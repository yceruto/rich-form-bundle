<?php

namespace Yceruto\Bundle\RichFormBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\ChoiceList\IdReader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Yceruto\Bundle\RichFormBundle\Request\SearchRequest;

class Entity2SearchAction
{
    use SearchActionTrait;

    private $registry;
    private $propertyAccessor;

    public function __construct(ManagerRegistry $registry, PropertyAccessorInterface $propertyAccessor)
    {
        $this->registry = $registry;
        $this->propertyAccessor = $propertyAccessor;
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
        $results = $this->createResults($qb, $searchRequest);

        return new JsonResponse($results);
    }

    private function createResults(QueryBuilder $qb, SearchRequest $request): array
    {
        $options = $request->getOptions();
        $paginator = $this->createPaginator($qb, $request->getPage(), $options);

        $count = 0;
        $results = [];
        $text = $options->getText();
        $em = $qb->getEntityManager();
        $classMetadata = $em->getClassMetadata($options->getEntityClass());
        $idReader = new IdReader($em, $classMetadata);
        foreach ($paginator as $entity) {
            ++$count;

            $elem = [
                'id' => $idReader->getIdValue($entity),
                'text' => (string) ($text ? $this->propertyAccessor->getValue($entity, $text) : $entity),
            ];

            foreach ($options->getResultFields() as $fieldName => $fieldValuePath) {
                $value = $this->propertyAccessor->getValue($entity, $fieldValuePath);

                if (\is_object($value)) {
                    $value = (string) $value;
                }

                $resultFieldName = \is_int($fieldName) ? $fieldValuePath : $fieldName;
                $elem['data'][$resultFieldName] = $value;
            }

            if (null !== $groupBy = $options->getGroupBy()) {
                $groupText = (string) $this->propertyAccessor->getValue($entity, $groupBy);
                $results[$groupText]['text'] = $groupText;
                $results[$groupText]['children'][] = $elem;
            } else {
                $results[] = $elem;
            }
        }

        return [
            'results' => array_values($results),
            // For better performance we don't calculate the total records
            // through a database query, instead we do an extra HTTP request
            // (only if the total records is multiple of max_results)
            // then empty results and has_next_page will be "false"
            'has_next_page' => $count > 0 && $count === $options->getMaxResults(),
        ];
    }
}
