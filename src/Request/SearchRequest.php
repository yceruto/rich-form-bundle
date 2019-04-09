<?php

namespace Yceruto\Bundle\RichFormBundle\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Yceruto\Bundle\RichFormBundle\Exception\MissingOptionsException;

class SearchRequest
{
    public const SESSION_ID = 'richform.request.search.';

    private $request;
    private $options;

    public function __construct(Request $request)
    {
        $this->request = $request;

        if (null === $hash = $request->attributes->get('hash')) {
            throw new \RuntimeException('Missing hash.');
        }

        if (!$request->hasSession()) {
            throw new \RuntimeException('Missing session.');
        }

        /** @var Session $session */
        $session = $request->getSession();
        $options = $session->get(self::SESSION_ID.$hash);

        if (!\is_array($options)) {
            throw new MissingOptionsException('Missing options.');
        }

        $options['dynamic_params_values'] = $request->get('dyn', []);

        $this->options = new SearchOptions($options);
    }

    public function getOptions(): SearchOptions
    {
        return $this->options;
    }

    public function getTerm(): string
    {
        return $this->request->get('term', '');
    }

    public function getPage(): int
    {
        return $this->request->get('page', 1);
    }
}
