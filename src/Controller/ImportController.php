<?php

namespace PhilTenno\NewsPull\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhilTenno\NewsPull\Service\Importer;
use PhilTenno\NewsPull\Model\NewspullModel;
use PhilTenno\NewsPull\Model\NewspullKeywordsModel;
use Contao\NewsModel;

class ImportController
{
    #[Route('/newspullimport', name: 'news_import')]
    public function __invoke(Request $request, Importer $importer, ContaoFramework $framework): Response
    {
        $framework->initialize();

        $token = $request->query->getString('token');

        // --- Aufräumen: Verwaiste Keyword-Einträge löschen ---
        $allKeywords = NewspullKeywordsModel::findAll();
        if ($allKeywords !== null) {
            foreach ($allKeywords as $keywordEntry) {
                $news = NewsModel::findByPk($keywordEntry->pid);
                if ($news === null) {
                    $keywordEntry->delete();
                }
            }
        }
        // --- Ende Aufräumen ---

        if ($token === 'all') {
            // Alle Konfigurationen importieren
            $configs = NewspullModel::findAll();

            if ($configs === null) {
                return new Response('no Configuration found', 404);
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
            echo "{$results['success']} of $total News imported, {$results['fail']} failed\n";

            if (!empty($results['failed'])) {
                echo "failed:\n";
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
            echo "{$result['success']} of $total News imported, {$result['fail']} failed\n";

            if (!empty($result['failed'])) {
                echo "failed:\n";
                foreach ($result['failed'] as $fail) {
                    echo "- $fail\n";
                }
            }
            exit;
        }
    }
}