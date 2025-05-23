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

        $token = $request->query->getString('token');

        if ($token === 'all') {
            // Alle Konfigurationen importieren
            $configs = NewspullModel::findAll();

            if ($configs === null) {
                return new Response('Keine Konfigurationen gefunden', 404);
            }

            $results = ['success' => 0, 'fail' => 0, 'failed' => []];

            foreach ($configs as $config) {
                $result = $importer->runImport($config);
                $results['success'] += $result['success'];
                $results['fail'] += $result['fail'];
                $results['failed'] = array_merge($results['failed'], $result['failed']);
            }

            header('Content-Type: text/plain');
            $total = $results['success'] + $results['fail'];
            echo "{$results['success']} von $total News importiert, {$results['fail']} fehlgeschlagen\n";

            if (!empty($results['failed'])) {
                echo "Fehlgeschlagen:\n";
                foreach ($results['failed'] as $fail) {
                    echo "- $fail\n";
                }
            }
            exit;
        } else {
            // Einzelne Konfiguration anhand Token importieren
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
}