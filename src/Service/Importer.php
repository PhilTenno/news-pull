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
use Contao\ImageSizeModel;
use Contao\Dbafs;

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

        // Zusammenfassende Log-Ausgabe nur bei tatsächlichem Import
        if ($imported > 0 || $errors > 0) {
            $summaryMsg = sprintf('News import: %d successful, %d failed.', $imported, $errors);
            $this->logger->info(
                $summaryMsg,
                ['contao' => new ContaoContext(__METHOD__, $errors > 0 ? ContaoContext::ERROR : ContaoContext::CRON)]
            );
        }

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
            return 'title fehlt/missing';
        }
        if (empty($item['teaser'])) {
            return 'teaser fehlt/missing';
        }
        if (empty($item['article'])) {
            return 'article fehlt/missing';
        }
        return null;
    }

    private function importNewsItem(array $item, NewspullModel $config): void
    {
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
        

        //
        // Optionales Bild-CE als erstes Element (vor Teaser/Text)
        //

        $image = $item['image'] ?? '';
        $imageAlt = $item['imageAlt'] ?? '';

        if ($image !== '') {

            // 1) Ordnerpfad aus Job-Konfiguration (Fallback: files/)
            $folderPath = 'files';
            if (!empty($config->image_dir)) {
                $folderModel = FilesModel::findByUuid($config->image_dir);
                if ($folderModel !== null && $folderModel->type === 'folder' && !empty($folderModel->path)) {
                    $folderPath = $folderModel->path;
                }
            }

            // 2) Bildpfad bilden (image enthält nur Dateiname, keine Unterordner)
            $filePath = rtrim($folderPath, '/') . '/' . ltrim($image, '/');

            // 3) Datei in tl_files suchen
            $fileModel = FilesModel::findByPath($filePath);

            if ($fileModel !== null) {
                // 4) Bildgröße validieren (ungültig → ohne size)
                $imageSizeId = (int) ($config->image_size ?? 0);
                $imageSizeValid = $imageSizeId > 0 && ImageSizeModel::findByPk($imageSizeId) !== null;

                // 5) Alt-Text (Fallback)
                $altText = $imageAlt !== '' ? $imageAlt : 'Artikel Bild';

                // 6) Sorting kleiner als das Text-CE (Text nutzt 128)
                $sorting = 64;

                $ce = new ContentModel();
                $ce->tstamp    = time();
                $ce->ptable    = 'tl_news';
                $ce->pid       = (int) $news->id;
                $ce->type      = 'image';
                $ce->sorting   = $sorting;
                $ce->singleSRC = $fileModel->uuid; // UUID (binary(16))
                if ($imageSizeValid) {
                    $ce->size = serialize([0, 0, $imageSizeId]);
                }
                
                // Metadaten überschreiben - separate Felder in Contao 5.x
                $ce->overwriteMeta = 1;
                $ce->alt = $altText;
                $ce->imageTitle = '';
                $ce->cssID = serialize(['', 'newspull__image']);

                // Teaser-Bild am News-Datensatz setzen, falls konfiguriert
                if (!empty($config->teaser_image)) {
                    $news->addImage = 1;
                    $news->singleSRC = $fileModel->uuid;  // UUID (binary(16))
                    $news->overwriteMeta = 1;
                    $news->alt = $altText;
                    $news->imageTitle = '';               // leer lassen
                    $news->save();
                }

                $ce->save();
            } else {
                // Datei nicht in tl_files – versuche gezielt zu registrieren
                $absPath = $this->projectDir . '/' . $filePath;

                if (is_file($absPath)) {
                    // Datei existiert im Dateisystem – gezielt in tl_files registrieren
                    try {
                        Dbafs::addResource($filePath);
                        // Nach Registrierung erneut suchen
                        $fileModel = FilesModel::findByPath($filePath);

                        if ($fileModel !== null) {
                            // Jetzt registriert – Bild-CE anlegen
                            $imageSizeId = (int) ($config->image_size ?? 0);
                            $imageSizeValid = $imageSizeId > 0 && ImageSizeModel::findByPk($imageSizeId) !== null;
                            $altText = $imageAlt !== '' ? $imageAlt : 'Artikel Bild';
                            $sorting = 64;

                            $ce = new ContentModel();
                            $ce->tstamp    = time();
                            $ce->ptable    = 'tl_news';
                            $ce->pid       = (int) $news->id;
                            $ce->type      = 'image';
                            $ce->sorting   = $sorting;
                            $ce->singleSRC = $fileModel->uuid;
                            if ($imageSizeValid) {
                                $ce->size = serialize([0, 0, $imageSizeId]);
                            }
                            
                            // Metadaten überschreiben - separate Felder in Contao 5.x
                            $ce->overwriteMeta = 1;
                            $ce->alt = $altText;
                            $ce->imageTitle = '';
                            $ce->cssID = serialize(['', 'newspull__image']);
                            
                            // Teaser-Bild am News-Datensatz setzen, falls konfiguriert
                            if (!empty($config->teaser_image)) {
                                $news->addImage = 1;
                                $news->singleSRC = $fileModel->uuid;  // UUID (binary(16))
                                $news->overwriteMeta = 1;
                                $news->alt = $altText;
                                $news->imageTitle = '';               // leer lassen
                                $news->save();
                            }

                            $ce->save();                            

                            $this->logger->info(
                                sprintf('Bild automatisch registriert und CE angelegt: %s', $filePath),
                                ['contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
                            );
                        } else {
                            $this->logger->warning(
                                sprintf('Bild konnte nicht registriert werden: %s', $filePath),
                                ['contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
                            );
                        }
                    } catch (\Throwable $e) {
                        $this->logger->error(
                            sprintf('Dbafs::addResource fehlgeschlagen für %s – %s', $filePath, $e->getMessage()),
                            ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                        );
                    }
                } else {
                    $this->logger->warning(
                        sprintf('Bild übersprungen: Datei nicht gefunden (%s)', $filePath),
                        ['contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
                    );
                }
            }            
        }

        //
        //Bild Ende
        //

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
        } 
        if (!empty($config->linktarget)) {
            //Links manipulieren ->target ->noopener
            $articleHtml = $this->addTargetAndRelToLinks($articleHtml);
        }
        // Jetzt das manipulierte $articleHtml weiterverarbeiten!
        $articleHtml = $this->sanitizeHtml($articleHtml);
        $articleHtml = $this->addFigureWrapperToImages($articleHtml);        
        $articleHtml = $this->wrapTablesWithContentTableClass($articleHtml);
        $this->createContentElement($news->id, $articleHtml, 'newsPull__article');
    }

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
            'table', 'thead', 'tbody', 'tr', 'th', 'td', 'img', 'blockquote','pre','code','img','sub','sup',
            'h1','h2','h3','h4','h5','h6','svg','path', 'rect', 'circle', 'g', 'line', 'polyline', 'polygon',
            'ellipse', 'text', 'defs', 'use', 'symbol', 'clipPath', 'mask', 'figure', 'figcaption','article','section'
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
    
    private function addFigureWrapperToImages(string $html): string
    {
        // Wenn kein <img> vorkommt, spare DOM-Overhead
        if (stripos($html, '<img') === false) {
            return $html;
        }

        $doc = new \DOMDocument();

        // Robust gegen unvollständiges HTML
        libxml_use_internal_errors(true);
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Alle <img>-Elemente holen (live NodeList -> erst in Array kopieren)
        $images = $doc->getElementsByTagName('img');
        $imgNodes = [];
        foreach ($images as $img) {
            $imgNodes[] = $img;
        }

        foreach ($imgNodes as $img) {
            // Prüfen, ob der direkte Parent bereits eine <figure> ist
            $parent = $img->parentNode;
            if ($parent instanceof \DOMElement && strcasecmp($parent->tagName, 'figure') === 0) {
                continue;
            }

            // Wrapper <figure class="newspull__figure"> erzeugen
            $wrapper = $doc->createElement('figure');
            $wrapper->setAttribute('class', 'newspull__figure');
            
            // IMG klonen und ins Wrapper-<figure> einfügen
            $clonedImg = $img->cloneNode(true);
            $wrapper->appendChild($clonedImg);
            
            // Wrapper an Stelle des Original-IMG einfügen
            $img->parentNode->replaceChild($wrapper, $img);
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
    private function addTargetAndRelToLinks(string $html): string
    {
        if (stripos($html, '<a ') === false) {
            return $html; // Keine Links vorhanden, nichts zu tun
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $links = $doc->getElementsByTagName('a');
        foreach ($links as $link) {
            // target='_blank' setzen, falls nicht vorhanden
            if (!$link->hasAttribute('target') || strtolower($link->getAttribute('target')) !== '_blank') {
                $link->setAttribute('target', '_blank');
            }
            // rel='nofollow noopener' setzen, falls nicht vorhanden oder unvollständig
            $rel = $link->getAttribute('rel');
            $rels = array_map('trim', explode(' ', $rel));
            if (!in_array('nofollow', $rels, true)) {
                $rels[] = 'nofollow';
            }
            if (!in_array('noopener', $rels, true)) {
                $rels[] = 'noopener';
            }
            $link->setAttribute('rel', trim(implode(' ', array_filter($rels))));
        }

        $result = $doc->saveHTML();
        // Optional: XML-Prolog entfernen
        $result = preg_replace('/^\s*<\?xml.*?\?>\s*/is', '', $result);
        return $result;
    }      
}