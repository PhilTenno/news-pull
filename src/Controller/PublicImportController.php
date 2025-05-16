<?php

namespace PhilTenno\NewsPull\Controller;

use PhilTenno\NewsPull\Service\NewsImportService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class PublicImportController extends AbstractController
{
    public function __construct(
        private NewsImportService $newsImportService,
        private LoggerInterface $logger
    ) {}

    public function importAction(): Response
    {
        try {
            $this->newsImportService->importNews();
            return new Response('News erfolgreich importiert', Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Import fehlgeschlagen: ' . $e->getMessage());
            return new Response('Fehler beim Import', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}