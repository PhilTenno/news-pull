<?php

namespace PhilTenno\NewsPull\Service;

use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\Config;
use Contao\NewsModel;
use Contao\ContentModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

class NewsImportService
{
    private Filesystem $symfonyFilesystem;

    public function __construct(
        private VirtualFilesystemInterface $filesystem,
        private ContaoFramework $framework,
        private LoggerInterface $logger,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        $this->symfonyFilesystem = new Filesystem();
        $this->framework->initialize();
    }

    public function importNews(?string $newsDir = null): void
    {
        if ($newsDir === null) {
            $newsDir = Config::get('news_pull_upload_dir');
            if (!$newsDir) {
                $this->logger->error('Upload-Verzeichnis nicht in den Einstellungen gesetzt.');
                return;
            }
        }

        $baseDir = $this->projectDir . '/' . rtrim($newsDir, '/');

        if (!is_dir($baseDir)) {
            $this->logger->error('Basisverzeichnis für News-Import existiert nicht: ' . $baseDir);
            return;
        }

        $dirs = array_filter(glob($baseDir . '/*'), 'is_dir');

        foreach ($dirs as $dir) {
            $jsonFile = $dir . '/news.json';
            if (!file_exists($jsonFile)) {
                $this->logger->warning('Keine news.json in: ' . $dir);
                continue;
            }

            $jsonContent = file_get_contents($jsonFile);
            $newsData = json_decode($jsonContent, true);

            if (!is_array($newsData)) {
                $this->logger->error('Ungültiges JSON-Format in: ' . $jsonFile);
                continue;
            }

            try {
                $this->validateJson($newsData);

                $existingNews = NewsModel::findBy(
                    ['headline=?', 'date=?'],
                    [$newsData['title'], strtotime($newsData['date'])]
                );

                if ($existingNews !== null) {
                    $this->logger->info('News existiert bereits: ' . $newsData['title']);
                    continue;
                }

                $newsItem = new NewsModel();
                $newsItem->tstamp = time();
                $newsItem->headline = $newsData['title'];
                $newsItem->alias = StringUtil::generateAlias($newsData['title']);
                $newsItem->date = strtotime($newsData['date']);
                $newsItem->time = strtotime($newsData['date']);
                $newsItem->published = true;
                $newsItem->pid = Config::get('news_pull_news_archive');
                if (!$newsItem->pid) {
                    $this->logger->error('Kein News-Archiv in der Konfiguration gesetzt!');
                    continue;
                }

                $imageUuid = null;
                if (isset($newsData['image'])) {
                    $imagePath = $dir . '/' . $newsData['image'];
                    if (file_exists($imagePath)) {
                        $imageUuid = $this->copyImage($imagePath);
                        if ($imageUuid) {
                            $newsItem->addImage = true;
                            $newsItem->singleSRC = $imageUuid;
                        }
                    } else {
                        $this->logger->warning('Bilddatei nicht gefunden: ' . $imagePath);
                    }
                }

                $newsItem->save();

                $this->createNewsArticle($newsItem, $newsData, $imageUuid);

                $this->logger->info('News erfolgreich importiert: ' . $newsData['title']);

                $this->symfonyFilesystem->remove($dir);

            } catch (\Exception $e) {
                $this->logger->error('Fehler beim Import aus ' . $dir . ': ' . $e->getMessage());
                // Ordner nicht löschen, damit Fehler geprüft werden kann
            }
        }
    }

    private function createNewsArticle(NewsModel $newsItem, array $newsData, ?string $imageUuid): void
    {
        $this->createTextElement($newsItem->id, 'teaser', $newsData['teaser'] ?? '');

        if ($imageUuid) {
            $this->createImageElement($newsItem->id, $imageUuid, $newsData['image_alt'] ?? '');
        }

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
        $contentElement->singleSRC = $imageUuid;
        $contentElement->alt = $altText;
        $contentElement->imagemargin = serialize([
            'top' => '0',
            'right' => '0',
            'bottom' => '0',
            'left' => '0',
            'unit' => 'px'
        ]);
        $contentElement->invisible = 0;
        $contentElement->tstamp = time();
        $contentElement->save();
    }

        private function copyImage(string $sourcePath): ?string
        {
            $uploadDir = Config::get('news_pull_upload_dir');
            if (!$uploadDir) {
                $this->logger->error('Upload-Verzeichnis nicht konfiguriert.');
                return null;
            }

            $targetPath = rtrim($uploadDir, '/') . '/' . uniqid() . '_' . basename($sourcePath);
            $absoluteTarget = $this->projectDir . '/' . $targetPath;

            try {
                // Symfony Filesystem zum Kopieren verwenden
                $filesystem = new \Symfony\Component\Filesystem\Filesystem();
                $filesystem->copy($sourcePath, $absoluteTarget);

                // FilesModel anlegen und speichern
                $fileModel = new \Contao\FilesModel();
                $fileModel->path = $targetPath;
                $fileModel->type = 'file';
                $fileModel->uuid = \Contao\StringUtil::uuidToBin(\Symfony\Component\Uid\Uuid::v4()->toRfc4122());
                $fileModel->tstamp = time();
                $fileModel->save();

                return \Contao\StringUtil::binToUuid($fileModel->uuid);

            } catch (\Exception $e) {
                $this->logger->error('Fehler beim Bildimport: ' . $e->getMessage());
                return null;
            }
        }

    private function validateJson(array $newsData): void
    {
        if (empty($newsData['title'])) {
            throw new \InvalidArgumentException('Title is required');
        }
        if (empty($newsData['teaser'])) {
            throw new \InvalidArgumentException('Teaser is required');
        }
        if (empty($newsData['text'])) {
            throw new \InvalidArgumentException('Text is required');
        }
        if (!empty($newsData['lang']) && !in_array($newsData['lang'], ['de', 'en'])) {
            throw new \InvalidArgumentException('Invalid language code: ' . $newsData['lang']);
        }
    }
}