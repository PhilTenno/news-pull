<?php

namespace PhilTenno\NewsPull\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhilTenno\NewsPull\Service\Importer;
use PhilTenno\NewsPull\Model\NewspullModel;

class ImportController
{
    #[Route('/newspullimport', name: 'news_import')]
    public function __invoke(Request $request, Importer $importer, ContaoFramework $framework): Response
    {
        $framework->initialize();

        // Token als String holen (ab Symfony 5.1)
        $token = $request->query->getString('token');

        // Konfiguration zum Token laden
        $config = NewspullModel::findOneBy('token', $token);

        if (!$config instanceof NewspullModel) {
            return new Response('Ungültiger Token', 403);
        }

        $result = $importer->runImport($config);

        header('Content-Type: text/plain');

        $total = $result['success'] + $result['fail'];
        echo "{$result['success']} von $total News importiert, {$result['fail']} fehlgeschlagen\n";

        if (!empty($result['failed'])) {
            echo "Fehlgeschlagen:\n";
            foreach ($result['failed'] as $fail) {
                echo "- $fail\n";
            }
        }
        exit;
    }
}