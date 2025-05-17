<?php

namespace PhilTenno\NewsPull\Service;

use Contao\FilesModel;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\Config;
use Contao\NewsModel;
use Contao\ContentModel;
use Contao\Database;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

class NewsImportService
{
    private VirtualFilesystemInterface $filesystem;
    private string $projectDir;
    private LoggerInterface $logger;
    private ContaoFramework $framework;
    private ?array $settings = null;

    public function __construct(
        VirtualFilesystemInterface $filesystem,
        string $projectDir,
        LoggerInterface $logger,
        ContaoFramework $framework
    ) {
        $this->filesystem = $filesystem;
        $this->projectDir = $projectDir;
        $this->logger = $logger;
        $this->framework = $framework;
    }

    private function getSettings(): ?array
    {
        
        if ($this->settings === null) {
            $db = Database::getInstance();
            $result = $db->prepare('SELECT * FROM tl_newspull_settings LIMIT 1')->execute();
            
            if ($result->numRows) {
                $this->settings = $result->row();
            } else {
                $this->logger->error('Keine NewsPull-Einstellungen gefunden. Bitte im Backend konfigurieren.');
                return null;
            }
        }
        
        return $this->settings;
    }

    public function importNews(?string $newsDir = null): string
    {
        $importedNews = [];
        $errors = [];

        if ($newsDir === null) {
            $settings = $this->getSettings();
            if (!$settings) {
                return 'Fehler: NewsPull-Einstellungen nicht gefunden.';
            }
            
            $newsDir = $settings['news_pull_upload_dir'];
            if (!$newsDir) {
                $this->logger->error('Upload-Verzeichnis nicht in den Einstellungen gesetzt.');
                return 'Fehler: Upload-Verzeichnis nicht gesetzt.';
            }
        }

        $baseDir = $this->projectDir . '/' . rtrim($newsDir, '/');
        if (!is_dir($baseDir)) {
            $this->logger->error('Basisverzeichnis für News-Import existiert nicht: ' . $baseDir);
            return 'Fehler: Basisverzeichnis existiert nicht: ' . $baseDir;
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

                $settings = $this->getSettings();
                if (!$settings) {
                    throw new \Exception('NewsPull-Einstellungen nicht gefunden.');
                }

                $newsItem = new NewsModel();
                $newsItem->tstamp = time();
                $newsItem->headline = $newsData['title'];
                $newsItem->alias = StringUtil::generateAlias($newsData['title']);
                $newsItem->date = strtotime($newsData['date']);
                $newsItem->time = strtotime($newsData['date']);
                $newsItem->published = (bool)$settings['news_pull_auto_publish'];
                $newsItem->pid = $settings['news_pull_news_archive'];
                
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
                $importedNews[] = $newsData['title'];

                $this->filesystem->delete($dir);

            } catch (\Exception $e) {
                $errors[] = 'Fehler beim Import aus ' . $dir . ': ' . $e->getMessage();
                $this->logger->error($errors[count($errors)-1]);
                // Ordner nicht löschen, damit Fehler geprüft werden kann
            }
        }

        // Zusammenfassung erstellen
        $summary = [];
        if (count($importedNews) > 0) {
            $summary[] = count($importedNews) . ' News importiert: ' . implode(', ', $importedNews);
        }
        if (count($errors) > 0) {
            $summary[] = count($errors) . ' Fehler aufgetreten: ' . implode(', ', $errors);
        }
        if (empty($summary)) {
            return 'Keine News zum Import gefunden.';
        }

        return implode("\n", $summary);
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
        $settings = $this->getSettings();
        if (!$settings || !$settings['news_pull_image_dir']) {
            $this->logger->error('Bild-Upload-Verzeichnis nicht konfiguriert.');
            return null;
        }

        $targetPath = rtrim($settings['news_pull_image_dir'], '/') . '/' . uniqid() . '_' . basename($sourcePath);
        $absoluteTarget = $this->projectDir . '/' . $targetPath;

        try {
            // 1. Datei physisch kopieren
            $filesystem = new Filesystem();
            $filesystem->copy($sourcePath, $absoluteTarget);

            // 2. In Contao-Datenbank registrieren (Synchronisierung)
            $fileModel = FilesModel::findByPath($targetPath);
            if (!$fileModel) {
                $fileModel = new FilesModel();
                $fileModel->path = $targetPath;
                $fileModel->type = 'file';
                $fileModel->uuid = StringUtil::uuidToBin(Uuid::v4()->toRfc4122());
                $fileModel->tstamp = time();
                $fileModel->save();
            }
            return StringUtil::binToUuid($fileModel->uuid);

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