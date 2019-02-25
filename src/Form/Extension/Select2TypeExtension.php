<?php

namespace Yceruto\Bundle\RichFormBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Yceruto\Bundle\RichFormBundle\Form\Type\Entity2Type;

class Select2TypeExtension extends AbstractTypeExtension
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['select2']['options'] = $options['select2_options'];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // Add a custom block prefix to ease select2 theming:
        \array_splice($view->vars['block_prefixes'], -1, 0, 'entity2_select2');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'select2_options' => function (OptionsResolver $resolver) {
                $resolver->setDefaults([
                    'theme' => 'default',
                    'allow_clear' => true,
                    'minimum_input_length' => 1,
                    'result_template' => null,
                    'selection_template' => null,
                    'ajax' => function (OptionsResolver $resolver) {
                        $resolver->setDefaults([
                            'delay' => 250,
                            'cache' => true,
                        ]);
                        $resolver->setAllowedTypes('delay', 'int');
                        $resolver->setAllowedTypes('cache', 'bool');
                    },
                ]);
                $resolver->setAllowedTypes('theme', ['null', 'string']);
                $resolver->setAllowedTypes('allow_clear', 'bool');
                $resolver->setAllowedTypes('minimum_input_length', 'int');
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
