<?php

namespace Yceruto\Bundle\RichFormBundle\Tests\Form\Type;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeStringIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\GroupableEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringCastableIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Yceruto\Bundle\RichFormBundle\Doctrine\Query\DynamicParameter;
use Yceruto\Bundle\RichFormBundle\Form\Extension\Select2TypeExtension;
use Yceruto\Bundle\RichFormBundle\Form\Type\Entity2Type;
use Yceruto\Bundle\RichFormBundle\Request\SearchRequest;

class Entity2TypeTest extends TypeTestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ManagerRegistry
     */
    private $emRegistry;

    /**
     * @var Session
     */
    private $session;

    protected function setUp(): void
    {
        $this->em = DoctrineTestHelper::createTestEntityManager();
        $this->emRegistry = $this->createRegistryMock('default', $this->em);
        $this->session = new Session(new MockArraySessionStorage(), new AttributeBag(), new FlashBag());

        parent::setUp();

        $schemaTool = new SchemaTool($this->em);
        $classes = [
            $this->em->getClassMetadata(GroupableEntity::class),
            $this->em->getClassMetadata(SingleIntIdEntity::class),
            $this->em->getClassMetadata(SingleIntIdNoToStringEntity::class),
            $this->em->getClassMetadata(SingleStringIdEntity::class),
            $this->em->getClassMetadata(SingleAssociationToIntIdEntity::class),
            $this->em->getClassMetadata(SingleStringCastableIdEntity::class),
            $this->em->getClassMetadata(CompositeIntIdEntity::class),
            $this->em->getClassMetadata(CompositeStringIdEntity::class),
        ];

        try {
            $schemaTool->dropSchema($classes);
        } catch (\Exception $e) {
        }

        try {
            $schemaTool->createSchema($classes);
        } catch (\Exception $e) {
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->em = null;
        $this->emRegistry = null;
        $this->session = null;
    }

    protected function createRegistryMock($name, $em)
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $registry->method('getManager')
            ->with($this->equalTo($name))
            ->willReturn($em);

        return $registry;
    }

    protected function getExtensions(): array
    {
        return array_merge(parent::getExtensions(), [
            new DoctrineOrmExtension($this->emRegistry),
        ]);
    }

    protected function getTypeExtensions(): array
    {
        return [new Select2TypeExtension()];
    }

    protected function getTypes(): array
    {
        $class = Entity2Type::class;

        return [new $class($this->session)];
    }

    protected function persist(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
        // no clear, because entities managed by the choice field must
        // be managed!
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The "expanded" option is not supported.
     */
    public function testExpandedOptionIsNotSupported(): void
    {
        $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'expanded' => true,
        ]);
    }

    public function testEmptyChoicesWithNullData(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'choice_label' => 'name',
        ]);

        $this->assertCount(0, $field->createView()->vars['choices']);
    }

    public function testSingleChoiceEqualToPassedData(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $view = $this->factory->createNamed('name', Entity2Type::class, $entity2, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'choice_label' => 'name',
        ])->createView();

        $this->assertCount(1, $view->vars['choices']);
        $this->assertEquals([2 => new ChoiceView($entity2, '2', 'Bar')], $view->vars['choices']);
    }

    public function testDefaultAttrDataForBoundObject(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity2->phoneNumbers = ['12345'];

        $this->persist([$entity1, $entity2]);

        $view = $this->factory->createNamed('name', Entity2Type::class, $entity2, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'choice_label' => 'name',
            'result_fields' => ['phoneNumbers'],
            'select2_options' => [
                'selection_template' => '{{ text }} - {{ phoneNumbers }}',
            ],
        ])->createView();

        $expectedAttr = [
            'data-data' => '{"id":"2","text":"Bar","title":"Bar","selected":true,"data":{"phoneNumbers":["12345"]}}',
        ];

        $this->assertCount(1, $view->vars['choices']);
        $this->assertEquals([2 => new ChoiceView($entity2, '2', 'Bar', $expectedAttr)], $view->vars['choices']);
    }

    public function testSingleChoiceWithCustomChoiceValue(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', Entity2Type::class, $entity1, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'choice_label' => 'name',
            'choice_value' => 'name',
        ]);

        $this->assertCount(1, $field->createView()->vars['choices']);
        $this->assertEquals(['Foo' => new ChoiceView($entity1, 'Foo', 'Foo')], $field->createView()->vars['choices']);
    }

    public function testEmptyChoicesWithNullDataMultiple(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'multiple' => true,
        ]);

        $this->assertCount(0, $field->createView()->vars['choices']);
    }

    public function testSingleChoiceEqualToPassedDataMultiple(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', Entity2Type::class, new ArrayCollection([$entity1]), [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'multiple' => true,
            'choice_label' => 'name',
        ]);

        $this->assertCount(1, $field->createView()->vars['choices']);
        $this->assertEquals([1 => new ChoiceView($entity1, '1', 'Foo')], $field->createView()->vars['choices']);
    }

    public function testSubmitNull(): void
    {
        $form = $this->factory->create(Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
        ]);

        $this->assertCount(0, $form->createView()->vars['choices']);

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData(), 'View data is always a string');
    }

    public function testSubmitNullMultiple(): void
    {
        $form = $this->factory->create(Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'multiple' => true,
        ]);

        $this->assertCount(0, $form->createView()->vars['choices']);

        $form->submit(null);

        $collection = new ArrayCollection();

        $this->assertEquals($collection, $form->getData());
        $this->assertEquals($collection, $form->getNormData());
        $this->assertSame([], $form->getViewData(), 'View data is always an array');
    }

    public function testSetDataEmptyArraySubmitNullMultiple(): void
    {
        $emptyArray = [];
        $form = $this->factory->create(Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'multiple' => true,
        ]);
        $form->setData($emptyArray);

        $this->assertCount(0, $form->createView()->vars['choices']);

        $form->submit(null);

        $this->assertInternalType('array', $form->getData());
        $this->assertEquals([], $form->getData());
        $this->assertEquals([], $form->getNormData());
        $this->assertSame([], $form->getViewData(), 'View data is always an array');
    }

    public function testSetDataNonEmptyArraySubmitNullMultiple(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $this->persist([$entity1]);
        $form = $this->factory->create(Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'multiple' => true,
        ]);
        $existing = [0 => $entity1];
        $form->setData($existing);

        $this->assertCount(1, $form->createView()->vars['choices']);

        $form->submit(null);

        $this->assertInternalType('array', $form->getData());
        $this->assertEquals([], $form->getData());
        $this->assertEquals([], $form->getNormData());
        $this->assertSame([], $form->getViewData(), 'View data is always an array');
    }

    public function testSubmitNullUsesDefaultEmptyData(): void
    {
        $emptyData = '1';
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $this->persist([$entity1]);

        $form = $this->factory->create(Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'empty_data' => $emptyData,
        ]);

        $this->assertCount(0, $form->createView()->vars['choices']);

        $form->submit(null);

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertSame($entity1, $form->getNormData());
        $this->assertSame($entity1, $form->getData());
    }

    public function testSubmitNullMultipleUsesDefaultEmptyData(): void
    {
        $emptyData = ['1'];
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $this->persist([$entity1]);

        $form = $this->factory->create(Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'multiple' => true,
            'empty_data' => $emptyData,
        ]);

        $this->assertCount(0, $form->createView()->vars['choices']);

        $form->submit(null);

        $collection = new ArrayCollection([$entity1]);

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertEquals($collection, $form->getNormData());
        $this->assertEquals($collection, $form->getData());
    }

    public function testSubmitMultipleNonExpandedSingleIdentifier(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $form = $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'multiple' => true,
            'choice_label' => 'name',
        ]);

        $this->assertCount(0, $form->createView()->vars['choices']);

        $form->submit(['1', '3']);

        $expected = new ArrayCollection([$entity1, $entity3]);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expected, $form->getData());
        $this->assertSame(['1', '3'], $form->getViewData());
    }

    public function testSubmitMultipleNonExpandedSingleAssocIdentifier(): void
    {
        $innerEntity1 = new SingleIntIdNoToStringEntity(1, 'InFoo');
        $innerEntity2 = new SingleIntIdNoToStringEntity(2, 'InBar');
        $innerEntity3 = new SingleIntIdNoToStringEntity(3, 'InBaz');

        $entity1 = new SingleAssociationToIntIdEntity($innerEntity1, 'Foo');
        $entity2 = new SingleAssociationToIntIdEntity($innerEntity2, 'Bar');
        $entity3 = new SingleAssociationToIntIdEntity($innerEntity3, 'Baz');

        $this->persist([$innerEntity1, $innerEntity2, $innerEntity3, $entity1, $entity2, $entity3]);

        $form = $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleAssociationToIntIdEntity::class,
            'multiple' => true,
            'choice_label' => 'name',
        ]);

        $this->assertCount(0, $form->createView()->vars['choices']);

        $form->submit(['1', '3']);

        $expected = new ArrayCollection([$entity1, $entity3]);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expected, $form->getData());
        $this->assertSame(['1', '3'], $form->getViewData());
    }

    public function testSubmitMultipleNonExpandedSingleIdentifierForExistingData(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $form = $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'multiple' => true,
            'choice_label' => 'name',
        ]);

        $existing = new ArrayCollection([0 => $entity2]);
        $form->setData($existing);

        $this->assertCount(1, $form->createView()->vars['choices']);

        $form->submit(['1', '3']);

        // entry with index 0 ($entity2) was replaced
        $expected = new ArrayCollection([0 => $entity1, 1 => $entity3]);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expected, $form->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $form->getData());
        $this->assertSame(['1', '3'], $form->getViewData());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Composite identifier is not supported.
     */
    public function testCompositeIdentifierIsNotSupported(): void
    {
        $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => CompositeIntIdEntity::class,
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidArgumentException
     * @expectedExceptionMessage The parameter "entity" with value instance of "stdClass" must be scalar, object given.
     */
    public function testFailsIfQueryParameterValueIsNotScalar(): void
    {
        $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => GroupableEntity::class,
            'query_builder' => static function (EntityRepository $r) {
                return $r->createQueryBuilder('entity')->where('entity = :entity')->setParameter('entity', new \stdClass());
            },
        ])->createView();
    }

    public function testSerializedAutocompleteOptions(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $dynamicParam = new DynamicParameter('number');
        $dynamicParam->where('entity.phoneNumbers like :number');

        /** @var Entity2Type $type */
        $type = Entity2Type::class;
        $view = $this->factory->createNamed('name', $type, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'choice_label' => 'name',
            'query_builder' => static function (EntityRepository $r) use (&$queryBuilder) {
                return $queryBuilder = $r->createQueryBuilder('entity')->where('entity.phoneNumbers is not null');
            },
            'dynamic_params' => [
                '#id' => $dynamicParam,
            ],
            'entity_manager' => 'default',
            'search_by' => ['name'],
            'order_by' => ['name'],
            'max_results' => 15,
            'result_fields' => ['phoneNumbers'],
        ])->createView();

        $options = [
            'class' => SingleIntIdEntity::class,
            'em' => 'default',
            'max_results' => 15,
            'search_by' => ['name'],
            'order_by' => ['name' => 'ASC'],
            'result_fields' => ['phoneNumbers'],
            'group_by' => null,
            'text' => 'name',
            'qb_parts' => [
                'dql_parts' => array_filter($queryBuilder->getDQLParts()),
                'parameters' => [],
            ],
            'qb_dynamic_params' => [$dynamicParam],
        ];
        $queryHash = CachingFactoryDecorator::generateHash($options, 'entity2_query');

        $this->assertSame($queryHash, $view->vars['entity2']['query_hash']);
        $this->assertTrue($this->session->has(SearchRequest::SESSION_ID.$queryHash));
        $this->assertSame($options, $this->session->get(SearchRequest::SESSION_ID.$queryHash));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidArgumentException
     * @expectedExceptionMessage The option "group_by" with value Closure is expected to be of type "null" or "string" or "Symfony\Component\PropertyAccess\PropertyPath", but is of type "Closure".
     */
    public function testGroupByCallableIsNotAllowed(): void
    {
        $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'group_by' => static function () {
                return 'foo';
            },
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid order "TYPO" for "order_by" option, the allowed values are "ASC" and "DESC".
     */
    public function testOrderByOptionFailsIfUnknownOrder(): void
    {
        $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'order_by' => ['name' => 'typo'],
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidArgumentException
     * @expectedExceptionMessage The option "dynamic_params" expects as key of the array a string (field name or CSS selector), integer given.
     */
    public function testDynamicParamsOptionFailsIfMissingKey(): void
    {
        $dynamicParam = new DynamicParameter('number');
        $dynamicParam->where('entity.phoneNumbers like :number');

        $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'dynamic_params' => [$dynamicParam],
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage Dynamic parameters must have a "where" statement.
     */
    public function testDynamicParamsOptionFailsIfEmptyWhere(): void
    {
        $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'dynamic_params' => ['#form_phone_number' => new DynamicParameter('number')],
        ]);
    }

    public function testValidDynamicParamsOption(): void
    {
        $dynamicParam = new DynamicParameter('number');
        $dynamicParam->where('entity.phoneNumbers like :number');

        $view = $this->factory->createNamed('name', Entity2Type::class, null, [
            'em' => 'default',
            'class' => SingleIntIdEntity::class,
            'dynamic_params' => ['#form_phone_number' => $dynamicParam],
        ])->createView();

        $expected = \json_encode(['dynamicParams' => ['#form_phone_number' => 'number']]);
        $this->assertSame($expected, $view->vars['attr']['data-entity2-options']);
    }
}
