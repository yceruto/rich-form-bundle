<?php

namespace App\Test\Form;

use App\Test\Entity\Category;
use App\Test\Entity\Product;
use App\Test\Entity\ProductType;
use App\Test\Entity\Tag;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Yceruto\Bundle\RichFormBundle\Form\Type\Entity2Type;
use Yceruto\Bundle\RichFormBundle\Request\SearchRequest;

class ProductFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('category', Entity2Type::class, [
                'class' => Category::class,
                'query_builder' => static function (EntityRepository $r) {
                    return $r->createQueryBuilder('c')->where('c.enabled = true');
                },
                'search_callback' => __CLASS__.'::search',
                'dynamic_params' => ['type'], // field name or CSS selector e.g. ['#product_form_type' => ...]
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
            ->add('type', EntityType::class, [
                'class' => ProductType::class,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', Product::class);
    }

    public static function search(QueryBuilder $qb, SearchRequest $request): void
    {
        if ($type = $request->getDynamicParamValue('type')) {
            $qb->andWhere('c.type = :type')->setParameter('type', $type);
        }
    }
}
