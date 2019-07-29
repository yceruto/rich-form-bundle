<?php

namespace Yceruto\Bundle\RichFormBundle\Form\Type;

use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
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
use Yceruto\Bundle\RichFormBundle\Form\ChoiceList\Loader\Entity2LoaderDecorator;
use Yceruto\Bundle\RichFormBundle\Request\SearchRequest;

class Entity2Type extends AbstractType
{
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
            $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) {
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
            'search_callback' => $options['search_callback'],
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
        $this->session->set(SearchRequest::SESSION_ID.$queryHash, $autocompleteOptions);

        $view->vars['entity2']['query_hash'] = $queryHash;
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $dynamicParams = [];
        foreach ($options['dynamic_params'] as $selector => $name) {
            if (null !== $view->parent && isset($view->parent->children[$selector]->vars['id'])) {
                unset($dynamicParams[$selector]);
                $selector = '#'.$view->parent->children[$selector]->vars['id'];
            }

            $dynamicParams[$selector] = $name;
        }
        $view->vars['attr']['data-entity2-options'] = \json_encode(['dynamicParams' => $dynamicParams]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'entity_manager' => null,
            'search_by' => null,
            'search_callback' => null,
            'order_by' => null,
            'dynamic_params' => [],
            'result_fields' => null,
            'max_results' => $this->globalOptions['max_results'] ?? 10,
        ]);

        $resolver->setAllowedTypes('entity_manager', ['null', 'string']);
        $resolver->setAllowedTypes('search_by', ['null', 'string', 'string[]']);
        $resolver->setAllowedTypes('search_callback', ['null', 'callable']);
        $resolver->setAllowedTypes('order_by', ['null', 'string', 'string[]']);
        $resolver->setAllowedTypes('dynamic_params', ['array']);
        $resolver->setAllowedTypes('result_fields', ['null', 'string', 'string[]']);
        $resolver->setAllowedTypes('max_results', ['null', 'int']);
        $resolver->setAllowedTypes('group_by', ['null', 'string', PropertyPath::class]);

        $resolver->setNormalizer('expanded', \Closure::fromCallable([$this, 'extendedNormalizer']));
        $resolver->setNormalizer('order_by', \Closure::fromCallable([$this, 'orderByNormalizer']));
        $resolver->setNormalizer('choice_loader', \Closure::fromCallable([$this, 'choiceLoaderNormalizer']));
        $resolver->setNormalizer('search_callback', \Closure::fromCallable([$this, 'searchCallbackNormalizer']));
        $resolver->setNormalizer('dynamic_params', \Closure::fromCallable([$this, 'dynamicParamsNormalizer']));
    }

    public function getBlockPrefix(): string
    {
        return 'entity2';
    }

    public function getParent(): string
    {
        return EntityType::class;
    }

    private static function extendedNormalizer(Options $options, $value): bool
    {
        if (true === $value) {
            throw new \RuntimeException('The "expanded" option is not supported.');
        }

        return $value;
    }

    private static function orderByNormalizer(Options $options, $value): array
    {
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
    }

    private static function choiceLoaderNormalizer(Options $options, $loader): ?ChoiceLoaderInterface {
        if (null === $loader) {
            return null;
        }

        if (null === $options['id_reader'] || !$options['id_reader']->isSingleId()) {
            throw new \RuntimeException('Composite identifier is not supported.');
        }

        return new Entity2LoaderDecorator($loader);
    }

    private static function searchCallbackNormalizer(Options $options, $callback): ?callable {
        if (null === $callback) {
            return null;
        }

        if (!\is_string($callback) || !\is_callable($callback)) {
            throw new \RuntimeException('Expected a callable string callback function.');
        }

        return $callback;
    }

    private static function dynamicParamsNormalizer(Options $options, $value): array {
        $dynamicParams = [];
        foreach ((array) $value as $id => $name) {
            if (!\is_string($name)) {
                throw new InvalidOptionsException('Dynamic parameter name must be a string.');
            }

            if (!\is_string($id)) {
                $id = $name;
            }

            $dynamicParams[$id] = $name;
        }

        return $dynamicParams;
    }

    private function getSerializableQueryBuilderParts(QueryBuilder $queryBuilder): array
    {
        $parameters = [];
        /** @var Parameter $parameter */
        foreach ($queryBuilder->getParameters() as $parameter) {
            $value = $parameter->getValue();
            if (\is_object($value)) {
                throw new InvalidArgumentException(sprintf('The parameter "%s" with value instance of "%s" must be scalar, object given.', $parameter->getName(), \get_class($value)));
            }
            if (\is_array($value)) {
                array_walk_recursive($value, static function ($v) use ($parameter) {
                    if (\is_object($v)) {
                        throw new InvalidArgumentException(sprintf('The parameter "%s" with value instance of "%s" must be scalar, object given.', $parameter->getName(), \get_class($v)));
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
