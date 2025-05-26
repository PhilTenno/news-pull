<?php

namespace PhilTenno\NewsPull\Service;

use Contao\ContentModel;
use Contao\NewsModel;
use Contao\NewsArchiveModel;
use Contao\UserModel;
use PhilTenno\NewsPull\Model\NewspullModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Contao\FilesModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Symfony\Component\String\Slugger\AsciiSlugger;

sleep(1);

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
        $uploadDir = $config->upload_dir; // binary(16)
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
        $finder = new Finder();

        if (!$fs->exists($uploadDir)) {
            $msg = "Upload directory does not exist: $uploadDir";
            $this->logger->error(
                $msg,
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            return ['success' => 0, 'fail' => 0];
        }

        $folders = iterator_to_array($finder->in($uploadDir)->directories()->depth(0)->sortByName());
        $imported = 0;
        $errors = 0;
        $failed = [];

        foreach ($folders as $index => $folder) {
            if ($index > 0 && $index % $batchSize === 0) {
                sleep(2); // zur Serverentlastung
            }

            $folderPath = $folder->getRealPath();

            // Fehlerhafte Ordner überspringen
            if (str_ends_with($folderPath, '_error')) {
                continue;
            }

            $result = $this->importNewsFolder($folderPath, $config, $maxFileSize, $failed);
            $result ? $imported++ : $errors++;
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
    private function importNewsFolder(string $folderPath, NewspullModel $config, int $maxFileSize, array &$failed): bool
    {
        $requiredFiles = ['news.json', 'teaser.txt', 'article.txt'];

        foreach ($requiredFiles as $file) {
            if (!file_exists($folderPath . '/' . $file)) {
                $failed[] = basename($folderPath) . " (missing file: $file)";
                $msg = "missing file: $file in $folderPath";
                $this->logger->error(
                    $msg,
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
                return $this->markFolderAsError($folderPath);
            }
        }

        // Datei-Größenprüfung
        foreach (['teaser.txt', 'article.txt'] as $file) {
            if (filesize($folderPath . '/' . $file) > $maxFileSize) {
                $failed[] = basename($folderPath) . " (File size too large: $file)";
                $msg = "File size too large: $file in $folderPath";
                $this->logger->error(
                    $msg,
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
                return $this->markFolderAsError($folderPath);
            }
        }

        $json = json_decode(file_get_contents($folderPath . '/news.json'), true);
        if (!$json || empty($json['title'])) {
            $msg = "Invalid or incomplete news.json in news.json in $folderPath";
            $this->logger->error(
                $msg,
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            return $this->markFolderAsError($folderPath);
        }

        // Sanitize Text
        $teaser = file_get_contents($folderPath . '/teaser.txt');
        $teaser = preg_replace('/^\xEF\xBB\xBF/', '', $teaser); // BOM entfernen
        $teaserHtml = $this->sanitizeHtml($teaser);
        // Prolog nach dem Sanitizen entfernen
        $teaserHtml = preg_replace('/^\s*<\?xml.*?\?>\s*/is', '', $teaserHtml);

        $article = file_get_contents($folderPath . '/article.txt');
        $article = preg_replace('/^\xEF\xBB\xBF/', '', $article);
        $articleHtml = $this->sanitizeHtml($article);
        $articleHtml = $this->wrapTablesWithContentTableClass($articleHtml);
        $articleHtml = preg_replace('/^\s*<\?xml.*?\?>\s*/is', '', $articleHtml);

        // News anlegen
        $news = new NewsModel();
        $news->pid = $config->news_archive;
        $news->tstamp = time();
        $news->headline = $json['title'];
        $news->date = time();
        $news->author = $config->author;
        $news->time = time();
        $news->published = !empty($config->auto_publish) ? 1 : 0;
        $news->teaser = $teaserHtml;
        //Startzeit der News -> Anzeigedatum
        $dateShow = $json['dateShow'] ?? '';

        if ($dateShow !== '') {
            $timestamp = strtotime($dateShow);
            if ($timestamp !== false) {
                $news->start = $timestamp;
            } else {
                $msg = "Invalid date format in news.json ($dateShow) in $folderPath";
                $this->logger->info(
                    $msg,
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
            }
        }

        // Alias generieren (wie Contao-Backend):
        $slugger = new AsciiSlugger('de'); // oder 'fr', 'en', je nach Sprache
        $alias = strtolower($slugger->slug($json['title'])->toString());

        // Prüfe auf Kollisionen:
        $existing = NewsModel::findByAlias($alias);
        if ($existing !== null) {
            $alias .= '-' . uniqid();
        }
        $news->alias = $alias;

        if (!empty($json['metaTitle'])) {
            $news->pageTitle = $json['metaTitle'];
        }
        if (!empty($json['metaDescription'])) {
            $news->description = $json['metaDescription'];
        }
        $news->save();

        // Inhaltselemente erstellen
        $news->teaser_news = !empty($config->teaser_news) ? '1' : '';

        if ($news->teaser_news === '1') {
            $this->createContentElement($news->id, $teaserHtml, 'newsPull__teaser');
        }
        $this->createContentElement($news->id, $articleHtml, 'newsPull__article');

        // Erfolgsmeldung für jede importierte News (optional)
        $msg = sprintf('News "%s" (ID %d) imported from %s.', $news->headline, $news->id, $folderPath);
        $this->logger->info(
            $msg,
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::CRON)]
        );

        // Ordner löschen
        $this->deleteFolder($folderPath);
        return true;
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

    private function deleteFolder(string $folderPath): void
    {
        $fs = new Filesystem();
        $fs->remove($folderPath);
    }

    private function markFolderAsError(string $folderPath): bool
    {
        $newPath = $folderPath . '_error';
        if (file_exists($newPath)) {
            $newPath .= '_' . uniqid();
        }
        rename($folderPath, $newPath);
        return false;
    }

    private function sanitizeHtml(string $html): string
    {
        $allowedTags = [
            'p', 'a', 'strong', 'em','u','i','b','br', 'ul', 'ol', 'li', 'br', 'span', 'div',
            'table', 'thead', 'tbody', 'tr', 'th', 'td', 'img', 'blockquote','pre','code','img',
            'h1','h2','h3','h4','h5','h6','svg','path', 'rect', 'circle', 'g', 'line', 'polyline', 'polygon', 'ellipse', 'text', 'defs', 'use', 'symbol', 'clipPath', 'mask'
        ];

        $allowedAttributes = [
            'href', 'src', 'alt', 'target', 'rel', 'title', 'style', 'class', 'colspan', 'rowspan','width', 'height', 'viewBox', 'fill', 'stroke', 'stroke-width', 'x', 'y', 'cx', 'cy', 'r', 'd', 'points', 'transform', 'opacity',
    'x1', 'y1', 'x2', 'y2', 'rx', 'ry', 'style', 'class', 'id', 'xlink:href', 'xmlns', 'xmlns:xlink', 'marker-end', 'marker-mid', 'marker-start',
    'font-size', 'font-family', 'text-anchor', 'dominant-baseline', 'clip-path', 'mask'
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
}