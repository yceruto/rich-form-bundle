<?php

namespace Yceruto\Bundle\RichFormBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Yceruto\Bundle\RichFormBundle\Form\Type\Entity2Type;

class Select2TypeExtension extends AbstractTypeExtension
{
    private $globalOptions;

    public function __construct(array $globalOptions = [])
    {
        $this->globalOptions = $globalOptions;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['select2']['options'] = $options['select2'];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // Add a custom block prefix to ease select2 theming:
        \array_splice($view->vars['block_prefixes'], -1, 0, 'entity2_select2');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'select2' => function (OptionsResolver $resolver, Options $parent) {
                $defaultTemplate = function (Options $options) {
                    return $options['template'];
                };

                $resolver->setDefaults([
                    'theme' => $this->globalOptions['theme'] ?? 'default',
                    'allow_clear' => $this->globalOptions['allow_clear'] ?? function (Options $options) use ($parent) {
                        return !$parent['required'];
                    },
                    'minimum_input_length' => $this->globalOptions['minimum_input_length'] ?? 0,
                    'minimum_results_for_search' => $this->globalOptions['minimum_results_for_search'] ?? 10,
                    'template' => null,
                    'result_template' => $defaultTemplate,
                    'selection_template' => $defaultTemplate,
                    'ajax' => function (OptionsResolver $resolver) {
                        $resolver->setDefaults([
                            'delay' => $this->globalOptions['ajax_delay'] ?? 250,
                            'cache' => $this->globalOptions['ajax_cache'] ?? true,
                        ]);
                        $resolver->setAllowedTypes('delay', 'int');
                        $resolver->setAllowedTypes('cache', 'bool');
                    },
                ]);
                $resolver->setAllowedTypes('theme', ['null', 'string']);
                $resolver->setAllowedTypes('allow_clear', 'bool');
                $resolver->setAllowedTypes('minimum_input_length', 'int');
                $resolver->setAllowedTypes('minimum_results_for_search', 'int');
                $resolver->setAllowedTypes('template', ['null', 'string']);
                $resolver->setAllowedTypes('result_template', ['null', 'string']);
                $resolver->setAllowedTypes('selection_template', ['null', 'string']);
            },
        ]);
    }

    /**
     * Symfony 3.4 Compatibility
     */
    public function getExtendedType(): string
    {
        foreach (static::getExtendedTypes() as $extendedType) {
            return $extendedType;
        }
    }

    public static function getExtendedTypes(): iterable
    {
        return [Entity2Type::class];
    }
}
