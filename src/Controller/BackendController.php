<?php

namespace PhilTenno\NewsPull\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhilTenno\NewsPull\Service\NewsImportService;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class BackendController extends AbstractController
{
    private NewsImportService $newsImportService;
    private FormFactoryInterface $formFactory;
    private RouterInterface $router;

    public function __construct(
        NewsImportService $newsImportService,
        FormFactoryInterface $formFactory,
        RouterInterface $router
    ) {
        $this->newsImportService = $newsImportService;
        $this->formFactory = $formFactory;
        $this->router = $router;
    }

    public function indexAction(Request $request): Response
    {
        $this->newsImportService->importNews();

        return new Response('News import triggered. Check the logs for details.');
    }
}