<?php

namespace App\Test\Form;

use App\Test\Entity\Category;
use App\Test\Entity\Product;
use App\Test\Entity\Tag;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Yceruto\Bundle\RichFormBundle\Form\Type\Entity2Type;

class ProductFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('category', Entity2Type::class, [
                'class' => Category::class,
            ])
            ->add('tags', Entity2Type::class, [
                'class' => Tag::class,
                'multiple' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', Product::class);
    }
}
