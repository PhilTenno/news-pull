<?php

namespace PhilTenno\NewsPull\Controller;

use PhilTenno\NewsPull\Service\NewsImportService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class PublicImportController
{
    private NewsImportService $newsImportService;
    private LoggerInterface $logger;

    public function __construct(
        NewsImportService $newsImportService,
        LoggerInterface $logger
    ) {
        $this->newsImportService = $newsImportService;
        $this->logger = $logger;
    }

    public function importAction(): Response
    {
        $result = $this->newsImportService->importNews();
        return new Response('Import erfolgreich! Ergebnis: ' . $result);
    }
}