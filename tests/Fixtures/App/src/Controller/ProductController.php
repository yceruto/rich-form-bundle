<?php

namespace App\Test\Controller;

use App\Test\Entity\Product;
use App\Test\Form\ProductFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    /**
     * @Route("/index", name="app_index")
     */
    public function index(): Response
    {
        $products = $this->getDoctrine()->getRepository(Product::class)->findAll();

        return $this->render('index.html.twig', ['products' => $products]);
    }

    /**
     * @Route("/new", name="app_new")
     */
    public function new(Request $request): Response
    {
        $product = new Product();

        $form = $this->createForm(ProductFormType::class, $product)
            ->handleRequest($request)
        ;

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($product);
            $em->flush();

            return $this->redirectToRoute('app_index');
        }

        return $this->render('form.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/edit/{id}", name="app_edit")
     */
    public function edit(Request $request, int $id): Response
    {
        $product = $this->getDoctrine()->getRepository(Product::class)->find($id);

        $form = $this->createForm(ProductFormType::class, $product)
            ->handleRequest($request)
        ;

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('app_index');
        }

        return $this->render('form.html.twig', ['form' => $form->createView()]);
    }
}
