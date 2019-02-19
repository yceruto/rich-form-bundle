<?php

namespace Yceruto\Bundle\RichFormBundle\Form\Type;

use Doctrine\ORM\QueryBuilder;
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
use Yceruto\Bundle\RichFormBundle\Form\ChoiceList\Loader\Entity2LoaderDecorator;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class Entity2Type extends AbstractType
{
    public const SESSION_ID = 'richform.entity2.';

    private $session;

    public function __construct(Session $session = null)
    {
        $this->session = $session;
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
        $queryHash = null;

        if (null !== $this->session) {
            $context = $options['autocomplete'] + [
                'class' => $options['class'],
            ];

            if ($options['query_builder']) {
                $context['qb_parts'] = $this->getQueryBuilderPartsForSerialize($options['query_builder']);
            }

            $queryHash = CachingFactoryDecorator::generateHash($context, 'entity2_query');
            $this->session->set(self::SESSION_ID.$queryHash, $context);
        }

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

            return new Entity2LoaderDecorator($loader);
        };

        $resolver->setDefault('autocomplete', function (OptionsResolver $resolver, Options $parent) {
            $dqlPartNormalizer = function (Options $options, $dqlPart) use ($parent) {
                if (null !== $dqlPart && null !== $parent['query_builder']) {
                    throw new \LogicException('DQL options cannot be used in combination with query_builder option, use one or the other but not both.');
                }

                return $dqlPart;
            };

            $resolver->setDefaults([
                'em' => null,
                'max_results' => 10,
                'search_fields' => null,
                'dql_select' => null,
                'dql_from_alias' => null,
                'dql_join' => null,
                'dql_where' => null,
                'dql_order_by' => null,
            ]);

            $resolver->setAllowedTypes('em', ['null', 'string']);
            $resolver->setAllowedTypes('max_results', ['null', 'int']);
            $resolver->setAllowedTypes('search_fields', ['null', 'string', 'string[]']);
            $resolver->setAllowedTypes('dql_select', ['null', 'string', 'string[]']);
            $resolver->setAllowedTypes('dql_from_alias', ['null', 'string']);
            $resolver->setAllowedTypes('dql_join', ['null', 'string', 'string[]']);
            $resolver->setAllowedTypes('dql_where', ['null', 'string', 'string[]']);
            $resolver->setAllowedTypes('dql_order_by', ['null', 'string', 'string[]']);

            $resolver->setNormalizer('dql_select', $dqlPartNormalizer);
            $resolver->setNormalizer('dql_from_alias', $dqlPartNormalizer);
            $resolver->setNormalizer('dql_join', $dqlPartNormalizer);
            $resolver->setNormalizer('dql_where', $dqlPartNormalizer);
            $resolver->setNormalizer('dql_order_by', $dqlPartNormalizer);
        });

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
