<?php

namespace Yceruto\Bundle\RichFormBundle\Tests\Form\Type;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Test\TypeTestCase;
use Yceruto\Bundle\RichFormBundle\Form\Type\Entity2Type;

class Entity2TypeTest extends TypeTestCase
{
    public const TESTED_TYPE = Entity2Type::class;

    public const ITEM_GROUP_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\GroupableEntity';
    public const SINGLE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity';
    public const SINGLE_IDENT_NO_TO_STRING_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity';
    public const SINGLE_STRING_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity';
    public const SINGLE_ASSOC_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity';
    public const SINGLE_STRING_CASTABLE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringCastableIdEntity';
    public const COMPOSITE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity';
    public const COMPOSITE_STRING_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeStringIdEntity';

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ManagerRegistry
     */
    private $emRegistry;

    protected function setUp()
    {
        $this->em = DoctrineTestHelper::createTestEntityManager();
        $this->emRegistry = $this->createRegistryMock('default', $this->em);

        parent::setUp();

        $schemaTool = new SchemaTool($this->em);
        $classes = [
            $this->em->getClassMetadata(self::ITEM_GROUP_CLASS),
            $this->em->getClassMetadata(self::SINGLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_IDENT_NO_TO_STRING_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_ASSOC_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_CASTABLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_IDENT_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_STRING_IDENT_CLASS),
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

    protected function createRegistryMock($name, $em)
    {
        $registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();
        $registry->expects($this->any())
            ->method('getManager')
            ->with($this->equalTo($name))
            ->will($this->returnValue($em));

        return $registry;
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->em = null;
        $this->emRegistry = null;
    }

    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), [
            new DoctrineOrmExtension($this->emRegistry),
        ]);
    }

    protected function persist(array $entities)
    {
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
        // no clear, because entities managed by the choice field must
        // be managed!
    }

    /**
     * @expectedException \LogicException
     */
    public function testExpandedOptionIsNotSupported()
    {
        $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'expanded' => true,
        ]);
    }

    public function testEmptyChoicesWithNullData()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $this->assertCount(0, $field->createView()->vars['choices']);
    }

    public function testSingleChoiceEqualToPassedData()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, $entity1, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $this->assertCount(1, $field->createView()->vars['choices']);
        $this->assertEquals([1 => new ChoiceView($entity1, '1', 'Foo')], $field->createView()->vars['choices']);
    }

    public function testEmptyChoicesWithNullDataMultiple()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'multiple' => true,
        ]);

        $this->assertCount(0, $field->createView()->vars['choices']);
    }

    public function testSingleChoiceEqualToPassedDataMultiple()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, new ArrayCollection([$entity1]), [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'multiple' => true,
            'choice_label' => 'name',
        ]);

        $this->assertCount(1, $field->createView()->vars['choices']);
        $this->assertEquals([1 => new ChoiceView($entity1, '1', 'Foo')], $field->createView()->vars['choices']);
    }

    public function testSubmitNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ]);
        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData(), 'View data is always a string');
    }

    public function testSubmitNullMultiple()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'multiple' => true,
        ]);
        $form->submit(null);

        $collection = new ArrayCollection();

        $this->assertEquals($collection, $form->getData());
        $this->assertEquals($collection, $form->getNormData());
        $this->assertSame([], $form->getViewData(), 'View data is always an array');
    }

    public function testSetDataEmptyArraySubmitNullMultiple()
    {
        $emptyArray = [];
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'multiple' => true,
        ]);
        $form->setData($emptyArray);
        $form->submit(null);
        $this->assertInternalType('array', $form->getData());
        $this->assertEquals([], $form->getData());
        $this->assertEquals([], $form->getNormData());
        $this->assertSame([], $form->getViewData(), 'View data is always an array');
    }

    public function testSetDataNonEmptyArraySubmitNullMultiple()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $this->persist([$entity1]);
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'multiple' => true,
        ]);
        $existing = [0 => $entity1];
        $form->setData($existing);
        $form->submit(null);
        $this->assertInternalType('array', $form->getData());
        $this->assertEquals([], $form->getData());
        $this->assertEquals([], $form->getNormData());
        $this->assertSame([], $form->getViewData(), 'View data is always an array');
    }

    public function testSubmitNullUsesDefaultEmptyData()
    {
        $emptyData = '1';
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $this->persist([$entity1]);

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertSame($entity1, $form->getNormData());
        $this->assertSame($entity1, $form->getData());
    }

    public function testSubmitNullMultipleUsesDefaultEmptyData()
    {
        $emptyData = ['1'];
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $this->persist([$entity1]);

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
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

    public function testSubmitMultipleNonExpandedSingleIdentifier()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $form = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
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

    public function testSubmitMultipleNonExpandedSingleAssocIdentifier()
    {
        $innerEntity1 = new SingleIntIdNoToStringEntity(1, 'InFoo');
        $innerEntity2 = new SingleIntIdNoToStringEntity(2, 'InBar');
        $innerEntity3 = new SingleIntIdNoToStringEntity(3, 'InBaz');

        $entity1 = new SingleAssociationToIntIdEntity($innerEntity1, 'Foo');
        $entity2 = new SingleAssociationToIntIdEntity($innerEntity2, 'Bar');
        $entity3 = new SingleAssociationToIntIdEntity($innerEntity3, 'Baz');

        $this->persist([$innerEntity1, $innerEntity2, $innerEntity3, $entity1, $entity2, $entity3]);

        $form = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_ASSOC_IDENT_CLASS,
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

    public function testSubmitMultipleNonExpandedSingleIdentifierForExistingData()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $form = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
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

    public function testSubmitMultipleNonExpandedCompositeIdentifier()
    {
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');
        $entity3 = new CompositeIntIdEntity(50, 60, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $form = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'multiple' => true,
            'choice_label' => 'name',
        ]);

        $this->assertCount(0, $form->createView()->vars['choices']);

        // because of the composite key collection keys are used
        $form->submit(['0', '2']);

        $expected = new ArrayCollection([$entity1, $entity3]);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expected, $form->getData());
        $this->assertSame(['0', '2'], $form->getViewData());
    }

    public function testSubmitMultipleNonExpandedCompositeIdentifierExistingData()
    {
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');
        $entity3 = new CompositeIntIdEntity(50, 60, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $form = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'multiple' => true,
            'choice_label' => 'name',
        ]);

        $existing = new ArrayCollection([0 => $entity2]);
        $form->setData($existing);

        $this->assertCount(1, $form->createView()->vars['choices']);

        $form->submit(['0', '2']);

        // entry with index 0 ($entity2) was replaced
        $expected = new ArrayCollection([0 => $entity1, 1 => $entity3]);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expected, $form->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $form->getData());
        $this->assertSame(['0', '2'], $form->getViewData());
    }
}
