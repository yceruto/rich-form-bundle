<?php

namespace Yceruto\Bundle\RichFormBundle\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Form\ChoiceList\IdReader;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Yceruto\Bundle\RichFormBundle\Request\SearchOptions;

class Entity2SearchAction extends AbstractSearchAction
{
    private $propertyAccessor;

    public function __construct(ManagerRegistry $registry, PropertyAccessorInterface $propertyAccessor)
    {
        parent::__construct($registry);

        $this->propertyAccessor = $propertyAccessor;
    }

    protected function createResults(Paginator $paginator, SearchOptions $options): array
    {
        $count = 0;
        $results = [];
        $text = $options->getText();
        $em = $paginator->getQuery()->getEntityManager();
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
            // through a database query, instead we wait for an extra request
            // (this will only happen if the total records is multiple of max_results)
            // then empty results and has_next_page will be "false"
            'has_next_page' => $count > 0 && $count === $options->getMaxResults(),
        ];
    }
}
