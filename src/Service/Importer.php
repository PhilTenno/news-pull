<?php

namespace PhilTenno\NewsPull\Service;

use Contao\ContentModel;
use Contao\NewsModel;
use Contao\NewsArchiveModel;
use Contao\UserModel;
use PhilTenno\NewsPull\Model\NewspullConfigModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
sleep(1);

class Importer
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function runImport(NewspullConfigModel $config): array
    {
        $uploadDir = TL_ROOT . '/' . $config->upload_dir;
        $batchSize = $config->batch_size ?? 10;
        $maxFileSize = ($config->max_file_size ?? 256) * 1024; // in bytes

        $fs = new Filesystem();
        $finder = new Finder();

        if (!$fs->exists($uploadDir)) {
            $this->logger->error("Upload-Verzeichnis existiert nicht: $uploadDir");
            return ['success' => 0, 'fail' => 0];
        }

        $folders = iterator_to_array($finder->in($uploadDir)->directories()->depth(0)->sortByName());
        $imported = 0;
        $errors = 0;

        foreach ($folders as $index => $folder) {
            if ($index > 0 && $index % $batchSize === 0) {
                sleep(2); // zur Serverentlastung
            }

            $folderPath = $folder->getRealPath();

            // Fehlerhafte Ordner überspringen
            if (str_ends_with($folderPath, '_error')) {
                continue;
            }

            $result = $this->importNewsFolder($folderPath, $config, $maxFileSize);
            $result ? $imported++ : $errors++;
        }

        return ['success' => $imported, 'fail' => $errors];
    }

    private function importNewsFolder(string $folderPath, NewspullConfigModel $config, int $maxFileSize): bool
    {
        $requiredFiles = ['news.json', 'teaser.txt', 'article.txt'];

        foreach ($requiredFiles as $file) {
            if (!file_exists($folderPath . '/' . $file)) {
                $this->logger->error("Datei fehlt: $file in $folderPath");
                return $this->markFolderAsError($folderPath);
            }
        }

        // Datei-Größenprüfung
        foreach (['teaser.txt', 'article.txt'] as $file) {
            if (filesize($folderPath . '/' . $file) > $maxFileSize) {
                $this->logger->error("Datei zu groß: $file in $folderPath");
                return $this->markFolderAsError($folderPath);
            }
        }

        $json = json_decode(file_get_contents($folderPath . '/news.json'), true);
        if (!$json || empty($json['title']) || empty($json['dateShow'])) {
            $this->logger->error("Ungültige oder unvollständige news.json in $folderPath");
            return $this->markFolderAsError($folderPath);
        }

        // Sanitize Text
        $teaserHtml = $this->sanitizeHtml(file_get_contents($folderPath . '/teaser.txt'));
        $articleHtml = $this->sanitizeHtml(file_get_contents($folderPath . '/article.txt'));

        // News anlegen
        $news = new NewsModel();
        $news->pid = $config->news_archive;
        $news->tstamp = time();
        $news->headline = $json['title'];
        $news->alias = ''; // wird automatisch generiert
        $news->author = $config->author;
        $news->date = strtotime($json['dateShow'] . ' 00:00:01');
        $news->time = time();
        $news->published = $config->auto_publish;
        $news->teaser = $teaserHtml;

        if (!empty($json['metaTitle'])) {
            $news->metaTitle = $json['metaTitle'];
        }
        if (!empty($json['metaDescription'])) {
            $news->description = $json['metaDescription'];
        }

        $news->save();

        // Inhaltselemente erstellen
        $this->createContentElement($news->id, $teaserHtml, 'newsReader__teaser');
        $this->createContentElement($news->id, $articleHtml, 'newsReader__article');

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
        $content->cssID = serialize([$cssClass, '']);
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
            'p', 'a', 'strong', 'em', 'ul', 'ol', 'li', 'br', 'span', 'div',
            'table', 'thead', 'tbody', 'tr', 'th', 'td', 'img', 'blockquote'
        ];

        $allowedAttributes = [
            'href', 'src', 'alt', 'title', 'style', 'class', 'colspan', 'rowspan'
        ];

        $html = preg_replace('/<\?(php)?[\s\S]*?\?>/i', '', $html);

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $this->cleanDomNode($doc, $allowedTags, $allowedAttributes);

        return trim($doc->saveHTML());
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
