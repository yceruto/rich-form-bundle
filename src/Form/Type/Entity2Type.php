<?php

namespace Yceruto\Bundle\RichFormBundle\Form\Type;

use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\OptionsResolver\Exception\InvalidArgumentException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyPath;
use Yceruto\Bundle\RichFormBundle\Doctrine\Query\DynamicParameter;
use Yceruto\Bundle\RichFormBundle\Form\ChoiceList\Loader\Entity2LoaderDecorator;

class Entity2Type extends AbstractType
{
    public const SESSION_ID = 'richform.entity2.';

    private $session;
    private $globalOptions;

    public function __construct(Session $session, array $globalOptions = [])
    {
        $this->session = $session;
        $this->globalOptions = $globalOptions;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (Kernel::MAJOR_VERSION < 4) {
            // Avoid caching in LazyChoiceList - Symfony 3.4
            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                $choiceList = $event->getForm()->getConfig()->getAttribute('choice_list');
                if ($choiceList instanceof LazyChoiceList) {
                    $loaded = (new \ReflectionObject($choiceList))->getProperty('loaded');
                    $loaded->setAccessible(true);
                    $loaded->setValue($choiceList, false);
                    $loaded->setAccessible(false);
                }
            });
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $autocompleteOptions = [
            'class' => $options['class'],
            'em' => $options['entity_manager'],
            'max_results' => $options['max_results'],
            'search_by' => $options['search_by'],
            'order_by' => $options['order_by'],
            'result_fields' => $options['result_fields'],
            'group_by' => $options['group_by'],
        ];

        if (\is_string($options['choice_label'])) {
            $autocompleteOptions['text'] = $options['choice_label'];
        } elseif ($options['choice_label'] instanceof PropertyPath) {
            $autocompleteOptions['text'] = (string) $options['choice_label'];
        } else {
            $autocompleteOptions['text'] = null;
        }

        if (null !== $options['query_builder']) {
            $autocompleteOptions['qb_parts'] = $this->getSerializableQueryBuilderParts($options['query_builder']);
        }

        if ([] !== $options['dynamic_params']) {
            $autocompleteOptions['qb_dynamic_params'] = array_values($options['dynamic_params']);
        }

        $queryHash = CachingFactoryDecorator::generateHash($autocompleteOptions, 'entity2_query');
        $this->session->set(self::SESSION_ID.$queryHash, $autocompleteOptions);

        $view->vars['entity2']['query_hash'] = $queryHash;
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        //
        $dynamicParams = [];
        /** @var DynamicParameter $dynamicParam */
        foreach ($options['dynamic_params'] as $selector => $dynamicParam) {
            if (null !== $view->parent && isset($view->parent->children[$selector]->vars['id'])) {
                unset($dynamicParams[$selector]);
                $selector = '#'.$view->parent->children[$selector]->vars['id'];
            }

            $dynamicParams[$selector] = $dynamicParam->getName();
        }
        $view->vars['attr']['data-entity2-options'] = \json_encode(['dynamicParams' => $dynamicParams]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $extendedNormalizer = function (Options $options, $expanded) {
            if (true === $expanded) {
                throw new \RuntimeException('The "expanded" option is not supported.');
            }

            return $expanded;
        };

        $choiceLoaderNormalizer = function (Options $options, $loader) {
            if (null === $loader) {
                return null;
            }

            if (!$options['id_reader']->isSingleId()) {
                throw new \RuntimeException('Composite identifier is not supported.');
            }

            return new Entity2LoaderDecorator($loader);
        };

        $orderByNormalizer = function (Options $options, $value) {
            $orderBy = [];

            if (null !== $options['group_by']) {
                array_unshift($orderBy, (string) $options['group_by']);
            }

            foreach ((array) $value as $field => $order) {
                if (\is_int($field)) {
                    $field = $order;
                    $order = 'ASC';
                }

                $order = strtoupper($order);

                if ('ASC' !== $order && 'DESC' !== $order) {
                    throw new InvalidArgumentException(sprintf('Invalid order "%s" for "order_by" option, the allowed values are "ASC" and "DESC".', $order));
                }

                $orderBy[$field] = $order;
            }

            return $orderBy;
        };

        $dynamicParamsNormalizer = function (Options $options, $value) {
            $dynamicParams = [];
            /** @var DynamicParameter $dynamicParam */
            foreach ((array) $value as $id => $dynamicParam) {
                if (!\is_string($id)) {
                    throw new InvalidArgumentException(sprintf('The option "dynamic_params" expects as key of the array a string (field name or CSS selector), %s given.', gettype($id)));
                }

                if ([] === $dynamicParam->getWhere()) {
                    throw new InvalidOptionsException('Dynamic parameters must have a "where" statement.');
                }

                $dynamicParams[$id] = $dynamicParam;
            }

            return $dynamicParams;
        };

        $resolver->setDefaults([
            'entity_manager' => null,
            'search_by' => null,
            'order_by' => null,
            'dynamic_params' => null,
            'result_fields' => null,
            'max_results' => $this->globalOptions['max_results'] ?? 10,
        ]);

        $resolver->setAllowedTypes('entity_manager', ['null', 'string']);
        $resolver->setAllowedTypes('search_by', ['null', 'string', 'string[]']);
        $resolver->setAllowedTypes('order_by', ['null', 'string', 'string[]']);
        $resolver->setAllowedTypes('dynamic_params', ['null', DynamicParameter::class.'[]']);
        $resolver->setAllowedTypes('result_fields', ['null', 'string', 'string[]']);
        $resolver->setAllowedTypes('max_results', ['null', 'int']);
        $resolver->setAllowedTypes('group_by', ['null', 'string', 'Symfony\Component\PropertyAccess\PropertyPath']);

        $resolver->setNormalizer('expanded', $extendedNormalizer);
        $resolver->setNormalizer('choice_loader', $choiceLoaderNormalizer);
        $resolver->setNormalizer('order_by', $orderByNormalizer);
        $resolver->setNormalizer('dynamic_params', $dynamicParamsNormalizer);
    }

    public function getBlockPrefix(): string
    {
        return 'entity2';
    }

    public function getParent(): string
    {
        return EntityType::class;
    }

    protected function getSerializableQueryBuilderParts(QueryBuilder $queryBuilder): array
    {
        $parameters = [];
        foreach ($queryBuilder->getParameters() as $parameter) {
            $value = $parameter->getValue();
            if (\is_object($value)) {
                throw new InvalidArgumentException('The parameter value must be scalar, object given.');
            }
            if (\is_array($value)) {
                array_walk_recursive($value, function ($v) {
                    if (\is_object($v)) {
                        throw new InvalidArgumentException('The parameter value must be scalar, object given.');
                    }
                });
            }

            $parameters[] = [
                'name' => $parameter->getName(),
                'value' => $value,
                'type' => $parameter->getType(),
            ];
        }

        return [
            'dql_parts' => array_filter($queryBuilder->getDQLParts()),
            'parameters' => $parameters,
        ];
    }
}
