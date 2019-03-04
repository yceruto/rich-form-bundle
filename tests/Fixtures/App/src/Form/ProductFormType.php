<?php

namespace App\Test\Form;

use App\Test\Entity\Category;
use App\Test\Entity\Product;
use App\Test\Entity\ProductType;
use App\Test\Entity\Tag;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Yceruto\Bundle\RichFormBundle\Doctrine\Query\DynamicParameter;
use Yceruto\Bundle\RichFormBundle\Form\Type\Entity2Type;

class ProductFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dynTypeParam = (new DynamicParameter('type'))
            ->where('c.type = :type')
            ->optional(true)
        ;

        $builder
            ->add('name')
            ->add('type', EntityType::class, [
                'class' => ProductType::class,
                'placeholder' => 'None',
            ])
            ->add('category', Entity2Type::class, [
                'class' => Category::class,
                'query_builder' => function (EntityRepository $r) {
                    return $r->createQueryBuilder('c')->where('c.enabled = true');
                },
                'dynamic_params' => ['type' => $dynTypeParam], // field name or CSS selector e.g. ['#product_form_type' => ...]
                'result_fields' => 'description',
                'select2_options' => [
                    'result_template' => '<strong>{{ text }}</strong><br><small>{{ description }}</small>',
                    'selection_template' => '<strong>{{ text }}</strong> <small>{{ description }}</small>',
                ],
            ])
            ->add('tags', Entity2Type::class, [
                'class' => Tag::class,
                'multiple' => true,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', Product::class);
    }
}
