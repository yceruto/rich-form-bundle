<?php

namespace Yceruto\Bundle\RichFormBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Yceruto\Bundle\RichFormBundle\Form\Type\Entity2Type;

class Select2TypeExtension extends AbstractTypeExtension
{
    private $propertyAccessor;
    private $globalOptions;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null, array $globalOptions = [])
    {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
        $this->globalOptions = $globalOptions;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Normalize placeholder
        if (null !== $options['select2_options']['placeholder']['text']) {
            $options['select2_options']['placeholder']['id'] = '';
        } else {
            $options['select2_options']['placeholder'] = null;
        }

        $view->vars['select2']['options'] = $options['select2_options'];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // Setting <option> attr with selected data
        if ($view->vars['choices'] && $options['result_fields'] && $options['select2_options']['selection_template']) {
            $this->preselectData($view->vars['choices'], (array) $options['result_fields']);
        }

        // Add a custom block prefix to ease select2 theming:
        \array_splice($view->vars['block_prefixes'], -1, 0, 'entity2_select2');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'select2_options' => function (OptionsResolver $resolver, Options $parent) {
                $defaultTemplate = function (Options $options) {
                    return $options['template'];
                };

                $resolver->setDefaults([
                    'theme' => $this->globalOptions['theme'] ?? 'default',
                    'allow_clear' => $this->globalOptions['allow_clear'] ?? function (Options $options) use ($parent) {
                        return !$parent['required'];
                    },
                    'minimum_input_length' => $this->globalOptions['minimum_input_length'] ?? 0,
                    'minimum_results_for_search' => $this->globalOptions['minimum_results_for_search'] ?? $parent['max_results'] ?? 10,
                    'template' => null,
                    'result_template' => $defaultTemplate,
                    'selection_template' => $defaultTemplate,
                    'placeholder' => function (OptionsResolver $resolver) use ($parent) {
                        $resolver->setDefined(['data']);
                        $resolver->setDefaults([
                            'text' => $parent['placeholder'],
                        ]);
                        $resolver->setAllowedTypes('text', ['null', 'string']);
                        $resolver->setAllowedTypes('data', ['null', 'array']);
                    },
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

    protected function preselectData(array $choices, array $resultFields): void
    {
        foreach ($choices as $choiceView) {
            if ($choiceView instanceof ChoiceGroupView) {
                $this->preselectData($choiceView->choices, $resultFields);
                continue;
            }

            $data = [
                'id' => $choiceView->value,
                'text' => $choiceView->label,
                'title' => $choiceView->label,
                'selected' => true,
            ];

            /** @var ChoiceView $choiceView */
            foreach ($resultFields as $fieldName => $fieldValuePath) {
                $value = $this->propertyAccessor->getValue($choiceView->data, $fieldValuePath);

                if (\is_object($value)) {
                    $value = (string) $value;
                }

                $resultFieldName = \is_int($fieldName) ? $fieldValuePath : $fieldName;
                $data['data'][$resultFieldName] = $value;
            }

            $choiceView->attr['data-data'] = \json_encode($data);
        }
    }
}
