<?php

namespace PhilTenno\NewsPull\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicImportController
{
    #[Route('/news-pull/import', name: 'news_pull_import', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response('News-Pull Import ausgeführt!');
    }
}