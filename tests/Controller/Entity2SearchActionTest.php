<?php

namespace Yceruto\Bundle\RichFormBundle\Tests\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\GroupableEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Yceruto\Bundle\RichFormBundle\Controller\Entity2SearchAction;
use Yceruto\Bundle\RichFormBundle\Doctrine\Query\DynamicParameter;
use Yceruto\Bundle\RichFormBundle\Request\SearchRequest;
use Yceruto\Bundle\RichFormBundle\Tests\Fixtures\Entity\EmbeddedEntity;

class Entity2SearchActionTest extends TestCase
{
    public const ITEM_GROUP_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\GroupableEntity';
    public const SINGLE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity';
    public const SINGLE_IDENT_NO_TO_STRING_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity';
    public const SINGLE_STRING_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity';
    public const SINGLE_ASSOC_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity';
    public const SINGLE_STRING_CASTABLE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringCastableIdEntity';
    public const ASSOCIATION_ENTITY = 'Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity';
    public const EMBEDDED_ENTITY = 'Yceruto\Bundle\RichFormBundle\Tests\Fixtures\Entity\EmbeddedEntity';

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ManagerRegistry
     */
    private $registry;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var Entity2SearchAction
     */
    private $controller;

    protected function setUp()
    {
        $this->em = DoctrineTestHelper::createTestEntityManager();
        $this->registry = $this->createRegistryMock('default', $this->em);
        $this->session = new Session(new MockArraySessionStorage(), new AttributeBag(), new FlashBag());
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => null,
            'search_callback' => null,
            'order_by' => [],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::SINGLE_IDENT_CLASS,
            'text' => null,
        ]);

        $this->controller = new Entity2SearchAction($this->registry, new PropertyAccessor());

        $schemaTool = new SchemaTool($this->em);
        $classes = [
            $this->em->getClassMetadata(self::ITEM_GROUP_CLASS),
            $this->em->getClassMetadata(self::SINGLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_IDENT_NO_TO_STRING_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_ASSOC_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_CASTABLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::ASSOCIATION_ENTITY),
            $this->em->getClassMetadata(self::EMBEDDED_ENTITY),
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

    protected function tearDown()
    {
        $this->em = null;
        $this->registry = null;
        $this->session = null;
        $this->controller = null;
    }

    protected function createRegistryMock($name, $em)
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $registry->method('getManager')
            ->with($this->equalTo($name))
            ->willReturn($em);

        return $registry;
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

    public function testMissingHash(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing hash.');

        $request = Request::create('/rich-form/entity2/search?term=foo');
        $this->controller->__invoke($request);
    }

    public function testMissingSession(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing session.');

        $request = Request::create('/rich-form/entity2/{hash}/search?term=foo');
        $request->attributes->set('hash', 'hash');
        $this->session = null;

        $this->controller->__invoke($request);
    }

    public function testMissingOptions(): void
    {
        $request = Request::create('/rich-form/entity2/{hash}/search?term=foo');
        $request->attributes->set('hash', 'hash');
        $this->session->clear();
        $request->setSession($this->session);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[]}', $response->getContent());
    }

    public function testEmptyResultsIfEmptyDatabase(): void
    {
        $request = Request::create('/rich-form/entity2/{hash}/search?term=foo');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);

        $response = $this->controller->__invoke($request);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[],"has_next_page":false}', $response->getContent());
    }

    public function testFirst10ResultsIfEmptySearchQuery(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $request = Request::create('/rich-form/entity2/{hash}/search');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);

        $response = $this->controller->__invoke($request, 'hash');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"1","text":"Foo"},{"id":"2","text":"Bar"}],"has_next_page":false}', $response->getContent());
    }

    public function testMatchedSearchQuery(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $request = Request::create('/rich-form/entity2/{hash}/search?term=foo');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"1","text":"Foo"}],"has_next_page":false}', $response->getContent());
    }

    public function testUnmatchedSearchQuery(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $request = Request::create('/rich-form/entity2/{hash}/search?term=baz');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[],"has_next_page":false}', $response->getContent());
    }

    public function testSearchBy(): void
    {
        $entity1 = new SingleIntIdEntity(1, '789');
        $entity2 = new SingleIntIdEntity(2, 'Foo');
        $entity2->phoneNumbers = ['123456789'];

        $this->persist([$entity1, $entity2]);

        $request = Request::create('/rich-form/entity2/{hash}/search?term=789');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => 'phoneNumbers',
            'search_callback' => null,
            'order_by' => [],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::SINGLE_IDENT_CLASS,
            'text' => null,
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"2","text":"Foo"}],"has_next_page":false}', $response->getContent());
    }

    public function testSearchByWithAssociationEntity(): void
    {
        $entity1 = new AssociationEntity();
        $entity1->single = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new AssociationEntity();
        $entity2->single = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1->single, $entity1, $entity2->single, $entity2]);

        $request = Request::create('/rich-form/entity2/{hash}/search?term=bar');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => 'single',
            'search_callback' => null,
            'order_by' => [],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::ASSOCIATION_ENTITY,
            'text' => 'single.name',
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"2","text":"Bar"}],"has_next_page":false}', $response->getContent());
    }

    public function testSearchByWithAssociationEntityField(): void
    {
        $entity1 = new AssociationEntity();
        $entity1->single = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new AssociationEntity();
        $entity2->single = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1->single, $entity1, $entity2->single, $entity2]);

        $request = Request::create('/rich-form/entity2/{hash}/search?term=bar');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => 'single.name',
            'search_callback' => null,
            'order_by' => [],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::ASSOCIATION_ENTITY,
            'text' => 'single.name',
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"2","text":"Bar"}],"has_next_page":false}', $response->getContent());
    }

    public function testSearchByWithEmbeddedEntityField(): void
    {
        $entity1 = new EmbeddedEntity(1, 'Foo');
        $entity1->contact->phone = '1234567890';
        $entity2 = new EmbeddedEntity(2, 'Bar');
        $entity2->contact->phone = '0987654321';

        $this->persist([$entity1, $entity2]);

        $request = Request::create('/rich-form/entity2/{hash}/search?term=321');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => 'contact.phone',
            'search_callback' => null,
            'order_by' => [],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::EMBEDDED_ENTITY,
            'text' => 'name',
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"2","text":"Bar"}],"has_next_page":false}', $response->getContent());
    }

    public function testResultFieldsWithEmbeddedEntityField(): void
    {
        $entity1 = new EmbeddedEntity(1, 'Foo');
        $entity1->contact->phone = '1234567890';
        $entity2 = new EmbeddedEntity(2, 'Bar');
        $entity2->contact->phone = '0987654321';

        $this->persist([$entity1, $entity2]);

        $request = Request::create('/rich-form/entity2/{hash}/search?term=321');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => 'contact.phone',
            'search_callback' => null,
            'order_by' => [],
            'result_fields' => [
                // string cast since Contact is an object
                0 => 'contact',
                // custom result field name
                'contact_phone' => 'contact.phone',
            ],
            'group_by' => null,
            'class' => self::EMBEDDED_ENTITY,
            'text' => 'name',
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"2","text":"Bar","data":{"contact":"0987654321","contact_phone":"0987654321"}}],"has_next_page":false}', $response->getContent());
    }

    public function testCustomResultFields(): void
    {
        $entity1 = new GroupableEntity(1, 'Foo', 'F');
        $entity2 = new GroupableEntity(2, 'Bar', 'B');

        $this->persist([$entity1, $entity2]);

        $request = Request::create('/rich-form/entity2/{hash}/search?term=foo');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => null,
            'search_callback' => null,
            'order_by' => [],
            'result_fields' => 'groupName',
            'group_by' => null,
            'class' => self::ITEM_GROUP_CLASS,
            'text' => 'name',
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"1","text":"Foo","data":{"groupName":"F"}}],"has_next_page":false}', $response->getContent());
    }

    public function testGroupByResult(): void
    {
        $entity1 = new GroupableEntity(1, 'Foo', 'A');
        $entity2 = new GroupableEntity(2, 'Bar', 'A');
        $entity3 = new GroupableEntity(3, 'Baz', 'B');

        $this->persist([$entity1, $entity2, $entity3]);

        $request = Request::create('/rich-form/entity2/{hash}/search');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => null,
            'search_callback' => null,
            'order_by' => [],
            'result_fields' => null,
            'group_by' => 'groupName',
            'class' => self::ITEM_GROUP_CLASS,
            'text' => 'name',
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"text":"A","children":[{"id":"1","text":"Foo"},{"id":"2","text":"Bar"}]},{"text":"B","children":[{"id":"3","text":"Baz"}]}],"has_next_page":false}', $response->getContent());
    }

    public function testOrderByResult(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'A');
        $entity2 = new SingleIntIdEntity(2, 'B');
        $entity3 = new SingleIntIdEntity(3, 'C');

        $this->persist([$entity1, $entity2, $entity3]);

        $request = Request::create('/rich-form/entity2/{hash}/search');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => null,
            'search_callback' => null,
            'order_by' => ['name' => 'DESC'],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::SINGLE_IDENT_CLASS,
            'text' => 'name',
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"3","text":"C"},{"id":"2","text":"B"},{"id":"1","text":"A"}],"has_next_page":false}', $response->getContent());
    }

    public function testChainOrderByResult(): void
    {
        $entity1 = new SingleAssociationToIntIdEntity($entity2 = new SingleIntIdNoToStringEntity(1, 'A'), 'Foo');
        $entity3 = new SingleAssociationToIntIdEntity($entity4 = new SingleIntIdNoToStringEntity(2, 'B'), 'Bar');

        $this->persist([$entity1, $entity2, $entity3, $entity4]);

        $request = Request::create('/rich-form/entity2/{hash}/search');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => null,
            'search_callback' => null,
            'order_by' => ['entity.name' => 'DESC'],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::SINGLE_ASSOC_IDENT_CLASS,
            'text' => 'name',
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"2","text":"Bar"},{"id":"1","text":"Foo"}],"has_next_page":false}', $response->getContent());
    }

    public function testCustomQueryBuilder(): void
    {
        $entity1 = new GroupableEntity(1, 'Foo1', 'A');
        $entity2 = new GroupableEntity(2, 'Foo2', 'B');

        $this->persist([$entity1, $entity2]);

        $queryBuilder = $this->em->createQueryBuilder()
            ->select('e')
            ->from(self::ITEM_GROUP_CLASS, 'e')
            ->where('e.groupName = :group_name')
            ->setParameter('group_name', 'B')
        ;

        $request = Request::create('/rich-form/entity2/{hash}/search?term=foo');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => null,
            'search_callback' => null,
            'order_by' => [],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::ITEM_GROUP_CLASS,
            'text' => 'name',
            'qb_parts' => [
                'dql_parts' => array_filter($queryBuilder->getDQLParts()),
                'parameters' => [['name' => 'group_name', 'value' => 'B', 'type' => null]],
            ],
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"2","text":"Foo2"}],"has_next_page":false}', $response->getContent());
    }

    public function testPagination(): void
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Foobar');

        $this->persist([$entity1, $entity2]);

        $options = [
            'em' => 'default',
            'max_results' => 1,
            'search_by' => null,
            'search_callback' => null,
            'order_by' => [],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::SINGLE_IDENT_CLASS,
            'text' => null,
        ];

        $request = Request::create('/rich-form/entity2/{hash}/search?term=foo&page=1');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', $options);

        $response = $this->controller->__invoke($request, 'hash');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"1","text":"Foo"}],"has_next_page":true}', $response->getContent());

        $request = Request::create('/rich-form/entity2/{hash}/search?term=foo&page=2');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', $options);

        $response = $this->controller->__invoke($request, 'hash');

        $this->assertSame('{"results":[{"id":"2","text":"Foobar"}],"has_next_page":true}', $response->getContent());

        $request = Request::create('/rich-form/entity2/{hash}/search?term=foo&page=3');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', $options);

        $response = $this->controller->__invoke($request, 'hash');

        $this->assertSame('{"results":[],"has_next_page":false}', $response->getContent());
    }

    public function testDynamicParam(): void
    {
        $entity1 = new GroupableEntity(1, 'Foo', 'A');
        $entity2 = new GroupableEntity(2, 'Bar', 'B');
        $entity3 = new GroupableEntity(3, 'Baz', 'C');

        $this->persist([$entity1, $entity2, $entity3]);

        $request = Request::create('/rich-form/entity2/{hash}/search?dyn[group]=B');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => 'name',
            'search_callback' => static function (QueryBuilder $qb, SearchRequest $request) {
                $qb->andWhere('entity.groupName = :group')
                    ->setParameter('group', $request->getDynamicParamValue('group'));
            },
            'order_by' => ['name' => 'DESC'],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::ITEM_GROUP_CLASS,
            'text' => 'name',
            'qb_dynamic_params' => [
                'group' => 'group',
            ],
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"2","text":"Bar"}],"has_next_page":false}', $response->getContent());
    }

    public function testIgnoreOptionalDynamicParamWithoutDefault(): void
    {
        $entity1 = new GroupableEntity(1, 'Foo', 'A');
        $entity2 = new GroupableEntity(2, 'Bar', 'B');
        $entity3 = new GroupableEntity(3, 'Baz', 'C');

        $this->persist([$entity1, $entity2, $entity3]);

        $request = Request::create('/rich-form/entity2/{hash}/search?dyn[group]=');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => 'name',
            'search_callback' => static function (QueryBuilder $qb, SearchRequest $request) {
                if ($group = $request->getDynamicParamValue('group')) {
                    $qb->andWhere('entity.groupName = :group')
                        ->setParameter('group', $group);
                }
            },
            'order_by' => ['name' => 'DESC'],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::ITEM_GROUP_CLASS,
            'text' => 'name',
            'qb_dynamic_params' => [
                'group' => 'group',
            ],
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"1","text":"Foo"},{"id":"3","text":"Baz"},{"id":"2","text":"Bar"}],"has_next_page":false}', $response->getContent());
    }

    public function testOptionalDynamicParamEmptyValueWithDefault(): void
    {
        $entity1 = new GroupableEntity(1, 'Foo', 'A');
        $entity2 = new GroupableEntity(2, 'Bar', 'B');
        $entity3 = new GroupableEntity(3, 'Baz', 'C');

        $this->persist([$entity1, $entity2, $entity3]);

        $request = Request::create('/rich-form/entity2/{hash}/search?dyn[group]=');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => 'name',
            'search_callback' => static function (QueryBuilder $qb, SearchRequest $request) {
                if (!$group = $request->getDynamicParamValue('group')) {
                    $group = 'C';
                }

                $qb->andWhere('entity.groupName = :group')
                    ->setParameter('group', $group);
            },
            'order_by' => ['name' => 'DESC'],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::ITEM_GROUP_CLASS,
            'text' => 'name',
            'qb_dynamic_params' => [
                'group' => 'group',
            ],
        ]);

        $response = $this->controller->__invoke($request, 'hash');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"results":[{"id":"3","text":"Baz"}],"has_next_page":false}', $response->getContent());
    }

    public function testFailsWithMissingMandatoryDynamicParam(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing value for dynamic parameter "group".');

        $entity1 = new GroupableEntity(1, 'Foo', 'A');
        $entity2 = new GroupableEntity(2, 'Bar', 'B');
        $entity3 = new GroupableEntity(3, 'Baz', 'C');

        $this->persist([$entity1, $entity2, $entity3]);

        $request = Request::create('/rich-form/entity2/{hash}/search');
        $request->attributes->set('hash', 'hash');
        $request->setSession($this->session);
        $this->session->set(SearchRequest::SESSION_ID.'hash', [
            'em' => 'default',
            'max_results' => 10,
            'search_by' => 'name',
            'search_callback' => static function (QueryBuilder $qb, SearchRequest $request) {
                if (!$group = $request->getDynamicParamValue('group')) {
                    throw new \RuntimeException('Missing value for dynamic parameter "group".');
                }
            },
            'order_by' => ['name' => 'DESC'],
            'result_fields' => null,
            'group_by' => null,
            'class' => self::ITEM_GROUP_CLASS,
            'text' => 'name',
            'qb_dynamic_params' => [
                'group' => 'group',
            ],
        ]);

        $this->controller->__invoke($request, 'hash');
    }
}
