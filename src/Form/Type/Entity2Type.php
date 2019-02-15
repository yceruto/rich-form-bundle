<?php

namespace Yceruto\Bundle\RichFormBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Yceruto\Bundle\RichFormBundle\Form\ChoiceList\Loader\Entity2LoaderDecorator;

class Entity2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
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

    public function buildView(FormView $view, FormInterface $form, array $options)
    {

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setNormalizer('expanded', function (Options $options, $expanded) {
            if (true === $expanded) {
                throw new \LogicException('The "expanded" option is not supported.');
            }

            return $expanded;
        });

        $resolver->setNormalizer('choice_loader', function (Options $options, $loader) {
            if (null === $loader) {
                return null;
            }

            return new Entity2LoaderDecorator($loader);
        });
    }

    public function getBlockPrefix()
    {
        return 'entity2';
    }

    public function getParent()
    {
        return 'Symfony\Bridge\Doctrine\Form\Type\EntityType';
    }
}
