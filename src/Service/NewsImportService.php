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

    public function importNews(string $newsDir): void
    {
        $this->logger->info(
            'NewsImportService: Import start',
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
        );

        $jsonFile = $newsDir . '/news.json';
        if (!file_exists($jsonFile)) {
            throw new \RuntimeException('news.json nicht gefunden in: ' . $jsonFile);
        }

        $jsonContent = file_get_contents($jsonFile);
        $newsData = json_decode($jsonContent, true);

        if (!is_array($newsData)) {
            throw new \RuntimeException('Ungültiges JSON-Format in: ' . $jsonFile);
        }

        foreach ($newsData as $item) {
            try {
                $this->validateJson($item); 
                // Prüfen ob die News bereits existiert
                $existingNews = NewsModel::findBy(
                    ['headline=?', 'date=?'],
                    [$item['headline'], strtotime($item['date'])]
                );

                if ($existingNews !== null) {
                    $this->logger->info(
                        'News existiert bereits: ' . $item['headline'],
                        ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                    );
                    continue;
                }

                // Neue News anlegen
                $newsItem = new NewsModel();
                $newsItem->tstamp = time();
                $newsItem->headline = $item['headline'];
                $newsItem->alias = StringUtil::generateAlias($item['headline']);
                $newsItem->date = strtotime($item['date']);
                $newsItem->time = strtotime($item['date']);
                $newsItem->published = true;
                $newsItem->pid = Config::get('news_pull_archive');

                // Bild importieren wenn vorhanden
                if (isset($item['image'])) {
                    $imageFile = $newsDir . '/' . $item['image'];
                    $this->logger->info(
                        'Wert von $imageFile: \'' . $imageFile . '\'',
                        ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                    );
                    
                    $uuid = $this->copyImage($imageFile);
                    if ($uuid !== null) {
                        $newsItem->addImage = true;
                        $newsItem->singleSRC = $uuid;
                    }
                }

                // News speichern
                $newsItem->save();

                $this->logger->info(
                    'News erfolgreich importiert: ' . $item['headline'],
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
                );

            } catch (\Exception $e) {
                $this->logger->error(
                    'Error creating news article from directory: ' . $newsDir . ' - ' . $e->getMessage(),
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
            }
        }
    }

    private function createNewsArticle(array $newsData, ?string $imageFile): void
    {
        $this->logger->info(
            'Wert von $imageFile: ' . var_export($imageFile, true),
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]
        );

        $archiveId = Config::get('news_pull_news_archive');
        if (!$archiveId) {
            throw new \Exception('No news archive configured in settings. Please select a news archive in the backend settings.');
        }

        $newsArchive = NewsArchiveModel::findByPk($archiveId);
        if (!$newsArchive) {
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
        // $imageFile ist der relative Pfad, z.B. 'files/News-Upload/Landschaft-bei-Meissen-im-Sommer.jpg'
        $fileModel = FilesModel::findByPath($imageFile);
        if ($fileModel === null) {
            $this->logger->error('Bild nicht gefunden in der Dateiverwaltung: ' . $imageFile);
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

        // Zielverzeichnis aus der Konfiguration holen (enthält bereits 'files/...')
        $uuid = Config::get('news_pull_upload_dir');
        $fileModel = FilesModel::findByUuid($uuid);
        if ($fileModel === null) {
            $this->logger->error('Upload-Ordner-UUID nicht gefunden: ' . $uuid);
            return null;
        }

        $imageDir = $fileModel->path; // z.B. 'files/contaodemo/News-Images'
        $imageName = basename($imageFile);
        $newPath = $imageDir . '/' . $imageName; // z.B. 'files/contaodemo/News-Images/Landschaft-bei-Meissen-im-Sommer.jpg'
        $newFullPath = $this->projectDir . '/' . $newPath;

        // Prüfen, ob die Quelldatei existiert
        if (!file_exists($imageFile)) {
            $this->logger->error('Quelldatei existiert nicht: ' . $imageFile);
            return null;
        }

        // Zielverzeichnis anlegen, falls nicht vorhanden
        $targetDir = dirname($newFullPath);
        if (!is_dir($targetDir)) {
            (new Folder($imageDir))->unprotect();
        }

        // Datei kopieren (überschreibt ggf. vorhandene Datei)
        if (!file_exists($newFullPath)) {
            $this->filesystem->copy($imageFile, $newFullPath, true);
        }

        // Datei im DCA registrieren (wichtig für die UUID!)
        $file = new File($newPath);
        $file->close();

        // UUID der kopierten Datei holen
        $fileModel = FilesModel::findByPath($newPath);
        if ($fileModel === null) {
            $this->logger->error('Keine FilesModel-Instanz für ' . $newPath . ' gefunden');
            return null;
        }

        return $fileModel->uuid;
    }

    private function validateJson(array $newsData): void
    {
        if (empty($newsData['title'])) {
            throw new \Exception('Title is required');
        }

        if (empty($newsData['teaser'])) {
            throw new \Exception('Teaser is required');
        }

        if (empty($newsData['text'])) {
            throw new \Exception('Text is required');
        }

        if (!empty($newsData['lang']) && !in_array($newsData['lang'], ['de', 'en'])) {
            throw new \Exception('Invalid language code: ' . $newsData['lang']);
        }
    }
}