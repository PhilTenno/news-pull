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
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        ContaoFramework $framework,
        Connection $connection
    ) {
        $this->projectDir = $projectDir;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->filesystem = new Filesystem();
        $this->framework = $framework;
        $this->connection = $connection;
    }

    private function getUploadDir(): string
    {
        $this->framework->initialize();
        $uuid = Config::get('news_pull_upload_dir');
        if (!$uuid) {
            $this->logger->error(
                'Kein Upload-Ordner in den Contao-Einstellungen gesetzt!',
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            throw new \RuntimeException('Upload-Ordner nicht konfiguriert.');
        }
        $fileModel = FilesModel::findByUuid($uuid);
        if ($fileModel === null) {
            $this->logger->error(
                'Upload-Ordner-UUID nicht gefunden: ' . $uuid,
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            throw new \RuntimeException('Upload-Ordner-UUID nicht gefunden.');
        }
        return $fileModel->path;
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

        // Alle Unterordner durchlaufen
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
                // Direkt das einzelne News-Array validieren
                $this->validateJson($newsData);

                // Prüfen ob die News bereits existiert
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

                // Neue News anlegen
                $newsItem = new NewsModel();
                $newsItem->tstamp = time();
                $newsItem->headline = $newsData['title'];
                $newsItem->alias = StringUtil::generateAlias($newsData['title']);
                $newsItem->date = strtotime($newsData['date']);
                $newsItem->time = strtotime($newsData['date']);
                $newsItem->published = true;
                $newsItem->pid = Config::get('news_pull_archive');
                if (!$newsItem->pid) {
                    $this->logger->error(
                        'Kein News-Archiv in der Konfiguration gesetzt!',
                        ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                    );
                    throw new \RuntimeException('News-Archiv nicht konfiguriert.');
                }

                // Bild importieren wenn vorhanden
                if (isset($newsData['image'])) {
                    $imageFile = $dir . '/' . $newsData['image'];
                    $this->logger->info(
                        'Wert von $imageFile: ' . $imageFile,
                        ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                    );

                    $uuid = $this->copyImage($imageFile);
                    if ($uuid !== null) {
                        $newsItem->addImage = true;
                        $newsItem->singleSRC = $uuid;
                        $this->logger->info(
                            'UUID für Bild erfolgreich gesetzt: ' . $uuid,
                            ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                        );
                    } else {
                        $newsItem->addImage = false;
                        $newsItem->singleSRC = null;
                        $this->logger->warning(
                            'Bild konnte nicht importiert werden: ' . $imageFile,
                            ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                        );
                    }
                }


                // Logging vor dem Speichern
                $this->logger->info(
                    'Speichere News: ' . $newsItem->headline . ', pid=' . $newsItem->pid . ', singleSRC=' . $newsItem->singleSRC . ', date=' . date('Y-m-d H:i:s', $newsItem->date),
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                );
                $newsItem->save();
                $this->logger->info(
                    'News erfolgreich importiert: ' . $newsData['title'],
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                );

                // Ordner nach erfolgreichem Import löschen
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

    private function createNewsArticle(array $newsData, ?string $imageFile): void
    {
        $this->framework->initialize();
        $this->logger->info(
            'Wert von $imageFile: ' . var_export($imageFile, true),
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
        );

        $archiveId = Config::get('news_pull_news_archive');
        if (!$archiveId) {
            $this->logger->error(
                'No news archive configured in settings. Please select a news archive in the backend settings.',
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            throw new \Exception('No news archive configured in settings. Please select a news archive in the backend settings.');
        }

        $newsArchive = NewsArchiveModel::findByPk($archiveId);
        if (!$newsArchive) {
            $this->logger->error(
                'Configured news archive (ID: ' . $archiveId . ') not found.',
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            throw new \Exception('Configured news archive (ID: ' . $archiveId . ') not found.');
        }

        // Hole den ersten Backend-User als Author
        $stmt = $this->connection->executeQuery('SELECT id FROM tl_user ORDER BY id ASC LIMIT 1');
        $defaultAuthorId = $stmt->fetchOne();

        $news = new NewsModel();
        $news->pid = $newsArchive->id;
        $news->author = $defaultAuthorId;
        $news->headline = $newsData['title'];
        $news->alias = StringUtil::standardize($newsData['title']);
        $news->teaser = $newsData['teaser'];
        $news->date = time();
        $news->time = time();
        $news->tstamp = time();
        $news->addImage = $imageFile !== null ? 1 : 0;
        $news->published = Config::get('news_pull_auto_publish') ? 1 : 0;
        $news->metaTitle = $newsData['title'];
        $news->metaDescription = $newsData['teaser'];
        $news->save();

        $this->createTextElement($news->id, 'teaser', $newsData['teaser']);

        if ($imageFile) {
            $newImageFile = $this->copyImage($imageFile);
            $this->createImageElement($news->id, $newImageFile, $newsData['image_alt'] ?? '');
        }

        $this->createTextElement($news->id, 'text', $newsData['text']);
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

    private function createImageElement(int $newsId, string $imageFile, string $altText): void
    {
        $this->framework->initialize();
        $fileModel = FilesModel::findByPath($imageFile);
        if ($fileModel === null) {
            $this->logger->error(
                'Bild nicht gefunden in der Dateiverwaltung: ' . $imageFile,
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
            throw new \RuntimeException('Bild nicht gefunden: ' . $imageFile);
        }

        $contentElement = new ContentModel();
        $contentElement->pid = $newsId;
        $contentElement->ptable = 'tl_news';
        $contentElement->type = 'image';
        $contentElement->singleSRC = $fileModel->uuid; // <-- UUID speichern!
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

    private function copyImage(string $imageFile): ?string
    {
        $this->framework->initialize();

        $uuid = Config::get('news_pull_upload_dir');
        $fileModel = FilesModel::findByUuid($uuid);
        if ($fileModel === null) {
            $this->logger->error('Upload-Ordner-UUID nicht gefunden: ' . $uuid, ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]);
            return null;
        }

        $imageDir = $fileModel->path; // z.B. 'files/contaodemo/News-Images'
        $imageName = basename($imageFile);
        $newPath = $imageDir . '/' . $imageName; // z.B. 'files/contaodemo/News-Images/Landschaft.jpg'
        $newFullPath = $this->projectDir . '/' . $newPath;

        if (!file_exists($imageFile)) {
            $this->logger->error('Quelldatei existiert nicht: ' . $imageFile, ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]);
            return null;
        }

        if (!is_dir(dirname($newFullPath))) {
            (new Folder($imageDir))->unprotect();
        }

        $this->filesystem->copy($imageFile, $newFullPath, true);

        // Datei im Contao-Dateisystem registrieren
        $file = new File($newPath);
        $file->close();

        $fileModel = FilesModel::findByPath($newPath);
        if ($fileModel === null) {
            $this->logger->error('Keine FilesModel-Instanz für ' . $newPath . ' gefunden', ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]);
            return null;
        }

        // Sicherstellen, dass wirklich eine UUID zurückgegeben wird
        if (empty($fileModel->uuid)) {
            $this->logger->error('UUID für Bild nicht gefunden: ' . $newPath, ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]);
            return null;
        }
        return $fileModel->uuid;
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