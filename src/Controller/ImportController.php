<?php

namespace PhilTenno\NewsPull\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use PhilTenno\NewsPull\Service\Importer;
use PhilTenno\NewsPull\Model\NewspullModel;
use PhilTenno\NewsPull\Model\NewspullKeywordsModel;
use Contao\NewsModel;

class ImportController
{
    #[Route('/newspullimport', name: 'news_import', methods: ['POST'])]
    public function __invoke(Request $request, Importer $importer, ContaoFramework $framework): JsonResponse
    {
        $framework->initialize();

        // 0) Token aus Query (einfach für n8n), alternativ könntest du auch Header "Authorization: Bearer" unterstützen
        $token = (string) $request->query->get('token', '');

        if ($token === '') {
            return new JsonResponse(['error' => 'Missing token'], 400);
        }

        // 1) Konfiguration anhand Token laden
        $config = NewspullModel::findOneBy('token', $token);
        if (!$config instanceof NewspullModel) {
            return new JsonResponse(['error' => 'Invalid token'], 403);
        }

        // 2) Optional: Payload-Größe prüfen (max_payload_size_kb aus DCA)
        $rawBody = (string) $request->getContent();
        $maxKb = (int) ($config->max_payload_size_kb ?? 0);
        if ($maxKb > 0) {
            $maxBytes = $maxKb * 1024;
            if (strlen($rawBody) > $maxBytes) {
                return new JsonResponse([
                    'error' => 'Payload too large',
                    'limit_kb' => $maxKb
                ], 413);
            }
        }

        // 3) JSON parsen
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], 400);
        }

        // 4) Items extrahieren; auch Einzelobjekte zulassen
        $items = $payload['items'] ?? null;

        // Fall A: Es gibt kein "items" – prüfen, ob ein Einzelobjekt geschickt wurde (z.B. direkt Felder wie title, article ...)
        if ($items === null) {
            $likelyItemKeys = ['title', 'article', 'teaser', 'dateShow', 'metaTitle', 'metaDescription', 'keywords', 'image', 'imageAlt'];
            $isSingleItem = is_array($payload) && count(array_intersect(array_keys($payload), $likelyItemKeys)) >= 2;

            if ($isSingleItem) {
                $items = [$payload]; // Einzelobjekt in Array verpacken
            }
        }

        // Validierung: items muss jetzt ein Array sein
        if (!is_array($items)) {
            return new JsonResponse(['error' => 'Field "items" must be an array'], 400);
        }

        // Reindizieren (wichtig gegen gemischte/nicht-numerische Keys)
        $items = array_values($items);

        // 5) Aufräumen: verwaiste Keyword-Einträge löschen (wie bisher)
        $allKeywords = NewspullKeywordsModel::findAll();
        if ($allKeywords !== null) {
            foreach ($allKeywords as $keywordEntry) {
                $news = NewsModel::findByPk($keywordEntry->pid);
                if ($news === null) {
                    $keywordEntry->delete();
                }
            }
        }

        // 6) Import durchführen
        $result = $importer->runImportFromArray($config, $items);

        // 7) Konsistente JSON-Antwort
        return new JsonResponse([
            'success' => $result['success'] ?? 0,
            'fail'    => $result['fail'] ?? 0,
            'failed'  => $result['failed'] ?? [],
        ], 200);
    }
}