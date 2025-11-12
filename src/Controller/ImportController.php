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
        // 0) Contao initialisieren
        $framework->initialize();

        // 1) Token aus Query
        $token = (string) $request->query->get('token', '');
        if ($token === '') {
            return new JsonResponse(['error' => 'Missing token'], 400);
        }

        // 2) Konfiguration anhand Token laden
        $config = NewspullModel::findOneBy('token', $token);
        if (!$config instanceof NewspullModel) {
            return new JsonResponse(['error' => 'Invalid token'], 403);
        }

        // 3) Optional: Payload-Größe prüfen (bestehende Logik für JSON bleibt erhalten)
        $rawBody = (string) $request->getContent();
        $maxKb = (int) ($config->max_payload_size_kb ?? 0);
        if ($maxKb > 0) {
            $maxBytes = $maxKb * 1024;
            if (strlen($rawBody) > $maxBytes) {
                return new JsonResponse([
                    'error' => 'Payload too large',
                    'limit_kb' => $maxKb,
                ], 413);
            }
        }

        // 4) Multipart oder JSON erkennen
        $isMultipart = 0 === strpos((string) $request->headers->get('Content-Type', ''), 'multipart/form-data');

        $items = null;
        /** @var UploadedFile|null $uploadedImage */
        $uploadedImage = null;

        if ($isMultipart) {
            // a) Datei holen (optional, je nach Anforderung)
            $uploadedImage = $request->files->get('image');
            if ($uploadedImage !== null && !$uploadedImage->isValid()) {
                return new JsonResponse(['error' => 'Invalid file upload'], 400);
            }

            // b) JSON aus Textfeld "payload" oder direkte Felder
            $payloadField = $request->request->get('payload'); // erwartet JSON-String
            if (is_string($payloadField) && $payloadField !== '') {
                $payload = json_decode($payloadField, true);
                if (!is_array($payload)) {
                    return new JsonResponse(['error' => 'Invalid JSON in "payload"'], 400);
                }
                $items = $payload['items'] ?? null;

                if ($items === null) {
                    // Einzelobjekt zulassen
                    $likelyItemKeys = ['title', 'article', 'teaser', 'dateShow', 'metaTitle', 'metaDescription', 'keywords', 'image', 'imageAlt'];
                    $isSingleItem = is_array($payload) && count(array_intersect(array_keys($payload), $likelyItemKeys)) >= 2;
                    if ($isSingleItem) {
                        $items = [$payload];
                    }
                }
            } else {
                // c) Keine "payload" vorhanden -> direkte Form-Felder als Einzelobjekt interpretieren
                $formData = $request->request->all();
                $likelyItemKeys = ['title', 'article', 'teaser', 'dateShow', 'metaTitle', 'metaDescription', 'keywords', 'image', 'imageAlt'];
                $isSingleItem = is_array($formData) && count(array_intersect(array_keys($formData), $likelyItemKeys)) >= 2;
                if ($isSingleItem) {
                    $items = [$formData];
                }
            }

            if (!is_array($items)) {
                return new JsonResponse(['error' => 'Missing or invalid "items" or form fields'], 400);
            }

            // d) Datei-Referenz für den Importer beistellen
            // Wir hängen die hochgeladene Datei an jedes Item an, wenn vorhanden
            if ($uploadedImage instanceof UploadedFile) {
                foreach ($items as &$it) {
                    if (!is_array($it)) {
                        $it = [];
                    }
                    // Feldname für Schritt 3 im Importer:
                    $it['_uploadedFile'] = $uploadedImage;
                    // Optional: Falls "image" (Dateiname) bisher genutzt wurde, lassen wir es stehen
                }
                unset($it);
            }
        } else {
            // Reiner JSON-Import (bestehende Logik)
            $payload = json_decode($rawBody, true);
            if (!is_array($payload)) {
                return new JsonResponse(['error' => 'Invalid JSON payload'], 400);
            }

            $items = $payload['items'] ?? null;
            if ($items === null) {
                $likelyItemKeys = ['title', 'article', 'teaser', 'dateShow', 'metaTitle', 'metaDescription', 'keywords', 'image', 'imageAlt'];
                $isSingleItem = is_array($payload) && count(array_intersect(array_keys($payload), $likelyItemKeys)) >= 2;
                if ($isSingleItem) {
                    $items = [$payload];
                }
            }

            if (!is_array($items)) {
                return new JsonResponse(['error' => 'Field "items" must be an array'], 400);
            }
        }

        // 5) Reindizieren (gegen gemischte Keys)
        $items = array_values($items);

        // 6) Aufräumen: verwaiste Keyword-Einträge löschen (wie bisher)
        $allKeywords = NewspullKeywordsModel::findAll();
        if ($allKeywords !== null) {
            foreach ($allKeywords as $keywordEntry) {
                $news = NewsModel::findByPk($keywordEntry->pid);
                if ($news === null) {
                    $keywordEntry->delete();
                }
            }
        }

        // 7) Import durchführen – der Importer kümmert sich in Schritt 3 um das physische Speichern des Bildes in /files
        $result = $importer->runImportFromArray($config, $items);

        // 8) Konsistente JSON-Antwort
        return new JsonResponse([
            'success' => $result['success'] ?? 0,
            'fail'    => $result['fail'] ?? 0,
            'failed'  => $result['failed'] ?? [],
        ], 200);
    }
}