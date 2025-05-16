<?php

namespace PhilTenno\NewsPull\Service;

use Doctrine\DBAL\Connection;
use Contao\StringUtil;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\NewsModel;
use Contao\ContentModel;
use Contao\File;
use Contao\Folder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Contao\NewsArchiveModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Contao\PageModel;
use Contao\Config;
use Contao\FilesModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Symfony\Component\Uid\Uuid;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;



class NewsImportService
{
    private string $projectDir;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;
    private Filesystem $filesystem;
    private ContaoFramework $framework;
    private Connection $connection;

    public function __construct(
        private VirtualFilesystemInterface $filesystem,
        private string $projectDir,
        private LoggerInterface $logger,
        private ContaoFramework $framework
    ) {
        $this->framework->initialize();
    }

    private function copyImage(string $sourcePath): ?string
    {
        $targetPath = 'files/news_import/' . uniqid() . '_' . basename($sourcePath);
        
        try {
            $this->filesystem->writeStream($targetPath, fopen($sourcePath, 'r'));
            return StringUtil::binToUuid($this->filesystem->getUuid($targetPath));
        } catch (\Exception $e) {
            $this->logger->error('Bildimport fehlgeschlagen: ' . $e->getMessage());
            return null;
        }
    }

    public function importNews(?string $newsDir = null): void
    {
        $this->framework->initialize();

        if ($newsDir === null) {
            $uuid = Config::get('news_pull_upload_dir');
            $fileModel = FilesModel::findByUuid($uuid);
            $newsDir = $fileModel->path;
        }

        $baseDir = $this->projectDir . '/' . $newsDir;

        $dirs = array_filter(glob($baseDir . '/*'), 'is_dir');

        foreach ($dirs as $dir) {
            $jsonFile = $dir . '/news.json';
            if (!file_exists($jsonFile)) {
                $this->logger->warning(
                    'Keine news.json in: ' . $dir,
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                );
                continue;
            }

            $jsonContent = file_get_contents($jsonFile);
            $newsData = json_decode($jsonContent, true);

            if (!is_array($newsData)) {
                $this->logger->error(
                    'Ungültiges JSON-Format in: ' . $jsonFile,
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
                continue;
            }

            try {
                $this->validateJson($newsData);

                $existingNews = NewsModel::findBy(
                    ['headline=?', 'date=?'],
                    [$newsData['title'], strtotime($newsData['date'])]
                );

                if ($existingNews !== null) {
                    $this->logger->info(
                        'News existiert bereits: ' . $newsData['title'],
                        ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                    );
                    continue;
                }

                $newsItem = new NewsModel();
                $newsItem->tstamp = time();
                $newsItem->headline = $newsData['title'];
                $newsItem->alias = StringUtil::generateAlias($newsData['title']);
                $newsItem->date = time();
                $newsItem->time = time();
                $newsItem->published = true;
                $newsItem->pid = Config::get('news_pull_news_archive');
                if (!$newsItem->pid) {
                    $this->logger->error(
                        'Kein News-Archiv in der Konfiguration gesetzt!',
                        ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                    );
                    throw new \RuntimeException('News-Archiv nicht konfiguriert.');
                }

                // Bild importieren wenn vorhanden (nur hier)
                $imageUuid = null;

                if (isset($newsData['image'])) {
                    $imagePath = FilesystemUtil::normalizePath($dir . '/' . $newsData['image']);
                    $uuid = $this->copyImage($imagePath);
                    
                    if ($uuid) {
                        $newsItem->addImage = true;
                        $newsItem->singleSRC = $uuid;
                    }
                }

                $this->logger->info(
                    'Speichere News: ' . $newsItem->headline . ', pid=' . $newsItem->pid . ', singleSRC=' . $newsItem->singleSRC . ', date=' . date('Y-m-d H:i:s', $newsItem->date),
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                );

                $this->createNewsArticle($newsItem, $newsData, $imageUuid);

                $newsItem->save();

                $this->logger->info(
                    'News erfolgreich importiert: ' . $newsData['title'],
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                );

                $this->filesystem->remove($dir);

            } catch (\Exception $e) {
                $this->logger->error(
                    'Fehler beim Import aus ' . $dir . ': ' . $e->getMessage(),
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
                // Ordner NICHT löschen, damit du den Fehler später prüfen kannst
            }
        }
    }

    private function createNewsArticle(NewsModel $newsItem, array $newsData, ?string $imageUuid): void
    {
        // Teaser-Text
        $this->createTextElement($newsItem->id, 'teaser', $newsData['teaser'] ?? '');

        // Bild-Element nur mit UUID, kein Kopieren mehr
        if ($imageUuid) {
            $this->createImageElement($newsItem->id, $imageUuid, $newsData['image_alt'] ?? '');
        }

        // Haupttext
        $this->createTextElement($newsItem->id, 'text', $newsData['text'] ?? '');
    }

    private function createTextElement(int $newsId, string $type, string $text): void
    {
        $contentElement = new ContentModel();
        $contentElement->pid = $newsId;
        $contentElement->ptable = 'tl_news';
        $contentElement->type = 'text';
        $contentElement->text = $text;
        $contentElement->invisible = 0;
        $contentElement->tstamp = time();
        $contentElement->save();
    }

    private function createImageElement(int $newsId, string $imageUuid, string $altText): void
    {
        $contentElement = new ContentModel();
        $contentElement->pid = $newsId;
        $contentElement->ptable = 'tl_news';
        $contentElement->type = 'image';
        $contentElement->singleSRC = $imageUuid; // UUID direkt speichern
        $contentElement->alt = $altText;
        $contentElement->tstamp = time();
        $contentElement->imagemargin = serialize([
            'top' => '0',
            'right' => '0',
            'bottom' => '0',
            'left' => '0',
            'unit' => 'px'
        ]);
        $contentElement->invisible = 0;
        $contentElement->save();
    }

    private function copyImage(string $sourcePath): ?string
    {
        try {
            $uploadDir = Config::get('news_pull_upload_dir');
            if (!$uploadDir) {
                throw new \RuntimeException('Upload-Verzeichnis nicht konfiguriert!');
            }

            $targetPath = FilesystemUtil::normalizePath(
                $uploadDir . '/' . uniqid() . '_' . basename($sourcePath)
            );

            // Datei kopieren und UUID automatisch generieren
            $this->filesStorage->writeStream($targetPath, fopen($sourcePath, 'r'));
            $uuid = $this->filesStorage->getUuid($targetPath);

            return StringUtil::binToUuid($uuid);

        } catch (\Exception $e) {
            $this->logger->error("Bildimport fehlgeschlagen: " . $e->getMessage());
            return null;
        }
    }    

    private function validateJson(array $newsData): void
    {
        if (empty($newsData['title'])) {
            $this->logger->error(
                'Title is required',
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            throw new \Exception('Title is required');
        }

        if (empty($newsData['teaser'])) {
            $this->logger->error(
                'Teaser is required',
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            throw new \Exception('Teaser is required');
        }

        if (empty($newsData['text'])) {
            $this->logger->error(
                'Text is required',
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            throw new \Exception('Text is required');
        }

        if (!empty($newsData['lang']) && !in_array($newsData['lang'], ['de', 'en'])) {
            $this->logger->error(
                'Invalid language code: ' . $newsData['lang'],
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            throw new \Exception('Invalid language code: ' . $newsData['lang']);
        }
    }

}