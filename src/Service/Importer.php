<?php

namespace PhilTenno\NewsPull\Service;

use Contao\ContentModel;
use Contao\NewsModel;
use PhilTenno\NewsPull\Model\NewspullModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Contao\FilesModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Symfony\Component\String\Slugger\AsciiSlugger;
use PhilTenno\NewsPull\Model\NewspullKeywordsModel;

class Importer
{
    private string $projectDir;

    public function __construct(
        private readonly LoggerInterface $logger,
        ParameterBagInterface $params
    ) {
        $this->projectDir = $params->get('kernel.project_dir');
    }

    public function runImport(NewspullModel $config): array
    {
        $uploadDir = $config->upload_dir;
        $model = FilesModel::findByUuid($uploadDir);

        if ($model !== null) {
            $uploadDir = $this->projectDir . '/' . $model->path;
        } else {
            $msg = "Upload UUID could not be resolved: " . bin2hex($config->upload_dir);
            $this->logger->error(
                $msg,
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            return ['success' => 0, 'fail' => 0];
        }

        $batchSize = $config->batch_size ?? 10;
        $maxFileSize = ($config->max_file_size ?? 256) * 1024; // in bytes

        $fs = new Filesystem();
        if (!$fs->exists($uploadDir)) {
            $msg = "Upload directory does not exist: $uploadDir";
            $this->logger->error(
                $msg,
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            return ['success' => 0, 'fail' => 0];
        }

        // Alle news*.json außer *_error.json finden
        $finder = new Finder();
        $finder->files()
            ->in($uploadDir)
            ->name('/^news.*\.json$/i')
            ->notName('/_error\.json$/i')
            ->sortByName();

        $imported = 0;
        $errors = 0;
        $failed = [];

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $fileName = $file->getFilename();
            $importDate = date('Y-m-d H:i:s');

            // Dateigröße prüfen
            $fileSize = filesize($filePath);
            if ($fileSize > $maxFileSize) {
                $errorFile = preg_replace('/\.json$/i', '_error.json', $filePath);
                rename($filePath, $errorFile);
                $msg = "Fehler NewsPull $importDate: Datei zu groß ($fileSize Bytes)";
                $this->logger->error(
                    $msg,
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
                $errors++;
                $failed[] = "$fileName (Datei zu groß)";
                sleep(2); // Pause nach jeder Datei
                continue;
            }

            // Datei einlesen und News importieren
            $result = $this->importNewsJsonFile($filePath, $config, $batchSize, $importDate, $failed);
            $imported += $result['success'];
            $errors += $result['fail'];
            // Fehlerhafte Items werden im $failed-Array gesammelt

            sleep(2); // Pause nach jeder Datei
        }

        // Zusammenfassende Log-Ausgabe nach dem Import
        $summaryMsg = sprintf('News import: %d successful, %d failed.', $imported, $errors);
        $this->logger->info(
            $summaryMsg,
            ['contao' => new ContaoContext(__METHOD__, $errors > 0 ? ContaoContext::ERROR : ContaoContext::CRON)]
        );

        return [
            'success' => $imported,
            'fail' => $errors,
            'failed' => $failed,
        ];
    }

    private function importNewsJsonFile(string $filePath, NewspullModel $config, int $batchSize, string $importDate, array &$failed): array
    {
        $fs = new Filesystem();
        $fileName = basename($filePath);

        $json = json_decode(file_get_contents($filePath), true);
        if (!is_array($json)) {
            // Ganze Datei fehlerhaft
            $errorFile = preg_replace('/\.json$/i', '_error.json', $filePath);
            rename($filePath, $errorFile);
            $msg = "Fehler NewsPull $importDate: Datei nicht lesbar oder kein Array";
            $this->logger->error(
                $msg,
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            $failed[] = "$fileName (Datei nicht lesbar)";
            return ['success' => 0, 'fail' => 1];
        }

        $success = 0;
        $fail = 0;
        $errorItems = [];
        $itemCount = count($json);

        foreach (array_chunk($json, $batchSize, true) as $batch) {
            foreach ($batch as $idx => $item) {
                $itemNr = $idx + 1;
                $error = $this->validateNewsItem($item);
                if ($error !== null) {
                    $msg = "NewsPull $importDate: Item Nr. $itemNr fehlerhaft ($error)";
                    $this->logger->error(
                        $msg,
                        ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                    );
                    $fail++;
                    $failed[] = "$fileName: Item $itemNr ($error)";
                    $errorItems[] = $item;
                    continue;
                }

                // Importiere Item
                $this->importNewsItem($item, $config);
                $success++;
            }
            sleep(2); // Pause nach jedem Batch
        }

        // Fehlerhafte Items in news_error.json speichern, falls vorhanden
        if (count($errorItems) > 0) {
            $errorFile = preg_replace('/\.json$/i', '_error.json', $filePath);
            file_put_contents($errorFile, json_encode(array_values($errorItems), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $msg = "Fehler NewsPull $importDate: $fail fehlerhafte Items in $fileName";
            $this->logger->error(
                $msg,
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
        }

        // Datei löschen, wenn alle erfolgreich
        if ($success === $itemCount) {
            $fs->remove($filePath);
            $msg = "NewsPull $importDate erfolgreich importiert";
            $this->logger->info(
                $msg,
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
            );
        } elseif ($success > 0) {
            // Teilweise Erfolg: Originaldatei löschen, Fehlerdatei bleibt
            $fs->remove($filePath);
        } else {
            // Alle fehlerhaft: Originaldatei löschen, Fehlerdatei bleibt
            $fs->remove($filePath);
        }

        return ['success' => $success, 'fail' => $fail];
    }

    private function validateNewsItem(array $item): ?string
    {
        if (empty($item['title'])) {
            return 'title fehlt';
        }
        if (empty($item['teaser'])) {
            return 'teaser fehlt';
        }
        if (empty($item['article'])) {
            return 'article fehlt';
        }
        return null;
    }

    private function importNewsItem(array $item, NewspullModel $config): void
    {

$this->logger->info('no_htmltags: ' . var_export($config->no_htmltags, true));
$this->logger->info('no_imagetags: ' . var_export($config->no_imagetags, true));


        // --- Hier bleibt die Logik wie bisher, nur die Quelle ist jetzt das Array $item ---
        $news = new NewsModel();
        $news->pid = $config->news_archive;
        $news->tstamp = time();
        $news->headline = $item['title'];
        $news->date = time();
        $news->author = $config->author;
        $news->time = time();
        $news->published = !empty($config->auto_publish) ? 1 : 0;
        $news->teaser = $this->stripHtmlTags($item['teaser']);
        $dateShow = $item['dateShow'] ?? '';
        if ($dateShow !== '') {
            $timestamp = strtotime($dateShow);
            if ($timestamp !== false) {
                $news->start = $timestamp;
            }
        }
        $slugger = new AsciiSlugger('de');
        $alias = strtolower($slugger->slug($item['title'])->toString());
        $existing = NewsModel::findByAlias($alias);
        if ($existing !== null) {
            $alias .= '-' . uniqid();
        }
        $news->alias = $alias;
        $news->pageTitle = !empty($item['metaTitle']) ? $item['metaTitle'] : $item['title'];
        $news->description = !empty($item['metaDescription']) ? $item['metaDescription'] : $item['teaser'];
        $news->save();

        // Keywords wie gehabt
        if (!empty($item['keywords'])) {
            $keywords = implode(',', array_map('trim', explode(',', $item['keywords'])));
            $existingKeywords = NewspullKeywordsModel::findByPid((int)$news->id);
            if ($existingKeywords === null) {
                $keywordModel = new NewspullKeywordsModel();
                $keywordModel->pid = (int)$news->id;
                $keywordModel->keywords = $keywords;
                $keywordModel->tstamp = time();
                $keywordModel->save();
            }
        }

        // Inhaltselemente wie gehabt
        $news->teaser_news = !empty($config->teaser_news) ? '1' : '';
        if ($news->teaser_news === '1') {
            $this->createContentElement($news->id, $news->teaser, 'newsPull__teaser');
        }

        // Artikel ggf. manipulieren
        $articleHtml = $item['article'];

        if (!empty($config->no_htmltags)) {
            // Alle HTML-Tags entfernen (plain text)
            $articleHtml = $this->stripHtmlTags($articleHtml);
        } elseif (!empty($config->no_imagetags)) {
            // Nur <img>-Tags entfernen, Rest bleibt erhalten
            $articleHtml = $this->removeImageTags($articleHtml);
        }

        // Jetzt das manipulierte $articleHtml weiterverarbeiten!
        $articleHtml = $this->sanitizeHtml($articleHtml);
        $articleHtml = $this->wrapTablesWithContentTableClass($articleHtml);
        $this->createContentElement($news->id, $articleHtml, 'newsPull__article');
    }

    // ... (Restliche Methoden wie createContentElement, sanitizeHtml, wrapTablesWithContentTableClass bleiben unverändert)
    private function createContentElement(int $pid, string $html, string $cssClass): void
    {
        $content = new ContentModel();
        $content->pid = $pid;
        $content->ptable = 'tl_news';
        $content->sorting = 128;
        $content->type = 'text';
        $content->text = $html;
        $content->cssID = serialize(['', $cssClass]);
        $content->tstamp = time();
        $content->save();
    }

    private function sanitizeHtml(string $html): string
    {
        $allowedTags = [
            'p', 'a', 'strong', 'em','u','i','b','br', 'ul', 'ol', 'li', 'br', 'span', 'div',
            'table', 'thead', 'tbody', 'tr', 'th', 'td', 'img', 'blockquote','pre','code','img',
            'h1','h2','h3','h4','h5','h6','svg','path', 'rect', 'circle', 'g', 'line', 'polyline', 'polygon', 'ellipse', 'text', 'defs', 'use', 'symbol', 'clipPath', 'mask'
        ];

        $allowedAttributes = [
            'href', 'src', 'alt', 'target', 'rel', 'title', 'style', 'class', 'colspan', 'rowspan','width', 'height', 'viewBox', 'fill', 'stroke', 'stroke-width', 'x', 'y', 'cx', 'cy', 'r', 'd', 'points', 'transform', 'opacity','x1', 'y1', 'x2', 'y2', 'rx', 'ry', 'style', 'class', 'id',
            'xlink:href', 'xmlns', 'xmlns:xlink', 'marker-end', 'marker-mid', 'marker-start','font-size', 'font-family', 'text-anchor', 'dominant-baseline', 'clip-path', 'mask'
        ];

        $html = preg_replace('/<\?(php)?[\s\S]*?\?>/i', '', $html);

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $this->cleanDomNode($doc, $allowedTags, $allowedAttributes);

        return trim($doc->saveHTML());
    }

    public function wrapTablesWithContentTableClass($html)
    {
        $doc = new \DOMDocument();
        // Fehler unterdrücken, falls HTML nicht 100% valide
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $tables = $doc->getElementsByTagName('table');
        // Da getElementsByTagName live ist, erst alle Tabellen in ein Array kopieren
        $tableNodes = [];
        foreach ($tables as $table) {
            $tableNodes[] = $table;
        }

        foreach ($tableNodes as $table) {
            $wrapper = $doc->createElement('div');
            $wrapper->setAttribute('class', 'content-table');
            // Tabelle aus dem DOM entfernen und ins Wrapper-Div einfügen
            $clonedTable = $table->cloneNode(true);
            $wrapper->appendChild($clonedTable);
            $table->parentNode->replaceChild($wrapper, $table);
        }

        // Rückgabe als HTML-String
        $result = $doc->saveHTML();
        // Optional: XML-Prolog entfernen
        $result = preg_replace('/^\s*<\?xml.*?\?>\s*/is', '', $result);
        return $result;
    }    
    private function cleanDomNode(\DOMNode $node, array $allowedTags, array $allowedAttributes): void
    {
      if ($node instanceof \DOMElement) {
        if (!in_array($node->tagName, $allowedTags, true)) {
          $node->parentNode?->removeChild($node);
          return;
        }

        foreach (iterator_to_array($node->attributes ?? []) as $attr) {
          if (!in_array($attr->name, $allowedAttributes, true)) {
            $node->removeAttribute($attr->name);
          }
        }
      }
      foreach (iterator_to_array($node->childNodes ?? []) as $child) {
        $this->cleanDomNode($child, $allowedTags, $allowedAttributes);
      }
    }
    private function stripHtmlTags(string $html): string
    {
        // Entfernt alle HTML-Tags und trimmt das Ergebnis
        return trim(strip_tags($html));
    }
    private function removeImageTags(string $html): string
    {
        // Entfernt alle <img ...> Tags, lässt den Rest des HTML unangetastet
        return preg_replace('/<img\b[^>]*>/i', '', $html);
    }    
}