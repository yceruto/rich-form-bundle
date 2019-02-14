<?php

namespace Yceruto\Bundle\RichFormBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Yceruto\Bundle\RichFormBundle\Form\ChoiceList\Loader\Entity2LoaderDecorator;

class Entity2Type extends AbstractType
{
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
