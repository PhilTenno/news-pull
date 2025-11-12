<?php

namespace PhilTenno\NewsPull\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

        // 1) Token prüfen
        $token = (string) $request->query->get('token', '');
        if ($token === '') {
            return new JsonResponse(['error' => 'Missing token'], 400);
        }

        $config = NewspullModel::findOneBy('token', $token);
        if (!$config instanceof NewspullModel) {
            return new JsonResponse(['error' => 'Invalid token'], 403);
        }

        // 2) Anfrageart erkennen
        $contentType = (string) $request->headers->get('Content-Type', '');
        $isMultipart = str_starts_with($contentType, 'multipart/form-data');

        $items = [];
        $uploadedImage = null;

        // 3) Multipart (neuer Weg)
        if ($isMultipart) {
            // a) optionales Bild hochladen
            $uploadedImage = $request->files->get('image');
            if ($uploadedImage !== null && !$uploadedImage->isValid()) {
                return new JsonResponse(['error' => 'Invalid image upload'], 400);
            }

            // b) JSON‐Payload extrahieren
            $payloadField = (string) $request->request->get('payload', '');
            $payload = $payloadField !== '' ? json_decode($payloadField, true) : [];

            if (!is_array($payload)) {
                return new JsonResponse(['error' => 'Invalid JSON in "payload"'], 400);
            }

            // c) Items bestimmen
            $items = $payload['items'] ?? null;
            if ($items === null && $this->looksLikeSingleItem($payload)) {
                $items = [$payload];
            }

            if (!is_array($items)) {
                return new JsonResponse(['error' => 'Missing or invalid "items" data'], 400);
            }

            // d) Datei an Item anhängen (optional)
            if ($uploadedImage instanceof UploadedFile) {
                foreach ($items as &$item) {
                    $item['_uploadedFile'] = $uploadedImage;
                }
                unset($item);
            }
        }

        // 4) JSON (alter Weg, fürs reine Text‑Import)
        else {
            $payload = json_decode((string) $request->getContent(), true);
            if (!is_array($payload)) {
                return new JsonResponse(['error' => 'Invalid JSON payload'], 400);
            }

            $items = $payload['items'] ?? null;
            if ($items === null && $this->looksLikeSingleItem($payload)) {
                $items = [$payload];
            }

            if (!is_array($items)) {
                return new JsonResponse(['error' => 'Field "items" must be an array'], 400);
            }
        }

        // 5) Array normalisieren
        $items = array_values($items);

        // 6) Verwaiste Keywords bereinigen (wie bisher)
        $keywords = NewspullKeywordsModel::findAll();
        if ($keywords !== null) {
            foreach ($keywords as $keyword) {
                if (NewsModel::findByPk($keyword->pid) === null) {
                    $keyword->delete();
                }
            }
        }

        // 7) Übergabe an Importer – dort erfolgt Speicherung/Registrierung
        $result = $importer->runImportFromArray($config, $items);

        // 8) Antwort
        return new JsonResponse([
            'success' => $result['success'] ?? 0,
            'fail'    => $result['fail'] ?? 0,
            'failed'  => $result['failed'] ?? [],
        ], 200);
    }

    private function looksLikeSingleItem(array $data): bool
    {
        $keys = ['title', 'teaser', 'article', 'metaTitle', 'metaDescription', 'keywords', 'dateShow', 'imageAlt'];
        return \count(array_intersect(array_keys($data), $keys)) >= 2;
    }
}