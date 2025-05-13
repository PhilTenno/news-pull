<?php

namespace PhilTenno\NewsPull\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhilTenno\NewsPull\Service\NewsImportService;

class PublicImportController extends AbstractController
{
    private NewsImportService $newsImportService;

    public function __construct(NewsImportService $newsImportService)
    {
        $this->newsImportService = $newsImportService;
    }

    #[Route('/news-pull/import', name: 'news_pull_import', methods: ['GET'])]
    public function __invoke(): Response
    {
        $this->newsImportService->importNews();
        return new Response('News-Pull Import ausgeführt!');
    }
}