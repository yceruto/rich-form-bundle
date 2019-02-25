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
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyPath;
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
            // Avoid caching of the choice list in LazyChoiceList - Symfony 3.4
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
        $autocompleteOptions = $options['autocomplete'] + [
            'class' => $options['class'],
        ];

        if (\is_string($options['choice_label'])) {
            $autocompleteOptions['text'] = $options['choice_label'];
        } elseif ($options['choice_label'] instanceof PropertyPath) {
            $autocompleteOptions['text'] = (string) $options['choice_label'];
        } else {
            $autocompleteOptions['text'] = null;
        }

        if ($options['query_builder']) {
            $autocompleteOptions['qb_parts'] = $this->getQueryBuilderPartsForSerialize($options['query_builder']);
        }

        $queryHash = CachingFactoryDecorator::generateHash($autocompleteOptions, 'entity2_query');
        $this->session->set(self::SESSION_ID.$queryHash, $autocompleteOptions);

        $view->vars['entity2']['query_hash'] = $queryHash;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $extendedNormalizer = function (Options $options, $expanded) {
            if (true === $expanded) {
                throw new \LogicException('The "expanded" option is not supported.');
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

        $resolver->setDefaults([
            'autocomplete' => function (OptionsResolver $resolver) {
                $resolver->setDefaults([
                    'em' => null,
                    'max_results' => $this->globalOptions['max_results'] ?? 10,
                    'search_fields' => null,
                    'result_fields' => null,
                ]);

                $resolver->setAllowedTypes('em', ['null', 'string']);
                $resolver->setAllowedTypes('max_results', ['null', 'int']);
                $resolver->setAllowedTypes('search_fields', ['null', 'string', 'string[]']);
                $resolver->setAllowedTypes('result_fields', ['null', 'string', 'string[]']);
            },
        ]);

        $resolver->setNormalizer('expanded', $extendedNormalizer);
        $resolver->setNormalizer('choice_loader', $choiceLoaderNormalizer);
    }

    public function getBlockPrefix(): string
    {
        return 'entity2';
    }

    public function getParent(): string
    {
        return EntityType::class;
    }

    public function getQueryBuilderPartsForSerialize(QueryBuilder $queryBuilder): array
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
