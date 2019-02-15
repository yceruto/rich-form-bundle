<?php

namespace Yceruto\Bundle\RichFormBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Yceruto\Bundle\RichFormBundle\Form\ChoiceList\Loader\Entity2LoaderDecorator;

class Entity2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (Kernel::MAJOR_VERSION < 4) {
            // Avoid choice list caching in LazyChoiceList - Symfony 3.4
            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                $choiceList = $event->getForm()->getConfig()->getAttribute('choice_list');
                if ($choiceList instanceof LazyChoiceList) {
                    $p = (new \ReflectionObject($choiceList))->getProperty('loaded');
                    $p->setAccessible(true);
                    $p->setValue($choiceList, false);
                    $p->setAccessible(false);
                }
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setNormalizer('expanded', function (Options $options, $expanded) {
            if (true === $expanded) {
                throw new \LogicException('Enabling the "expanded" option is not supported.');
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
