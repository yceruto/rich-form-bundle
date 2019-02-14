<?php

namespace Yceruto\Bundle\RichFormBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class Entity2SearchAction
{
    public function __invoke(Request $request)
    {
        return new JsonResponse([
            ['id' => 1, 'text' => 'OK'],
        ]);
    }
}
