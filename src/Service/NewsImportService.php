<?php

namespace PhilTenno\NewsPull\Service;

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

class NewsImportService
{
    private string $projectDir;
    private string $uploadDir;
    private string $imageDir;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;
    private Filesystem $filesystem;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        #[Autowire('%env(NEWS_PULL_UPLOAD_DIR)%')] string $uploadDir,
        string $imageDir,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LoggerInterface $logger
    ) {
        $this->projectDir = $projectDir;
        $this->uploadDir = $uploadDir;
        $this->imageDir = $imageDir;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->filesystem = new Filesystem();
    }

    public function importNews(): void
    {

        // Logging direkt am Anfang der Methode
        $this->logger->info('NewsImportService: importNews() wurde aufgerufen');


        $uploadPath = $this->projectDir . '/web/' . $this->uploadDir;

        if (!is_dir($uploadPath)) {
            $this->logger->error('Upload directory does not exist: ' . $uploadPath);
            return;
        }

        $finder = new Finder();
        $finder->directories()->in($uploadPath)->depth(0);

        foreach ($finder as $directory) {
            $newsDirectory = $directory->getRealPath();
            $jsonFile = $newsDirectory . '/news.json';
            $imageFile = null;

            if (file_exists($newsDirectory . '/image.jpg')) {
                $imageFile = $newsDirectory . '/image.jpg';
            } elseif (file_exists($newsDirectory . '/image.jpeg')) {
                $imageFile = $newsDirectory . '/image.jpeg';
            } elseif (file_exists($newsDirectory . '/image.png')) {
                $imageFile = $newsDirectory . '/image.png';
            }

            if (!file_exists($jsonFile)) {
                $this->logger->error('JSON file not found in directory: ' . $newsDirectory);
                continue;
            }

            $jsonContent = file_get_contents($jsonFile);

            try {
                $newsData = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
                $this->validateJson($newsData);
            } catch (\Exception $e) {
                $this->logger->error('Invalid JSON in file: ' . $jsonFile . ' - ' . $e->getMessage());
                continue;
            }

            try {
                $this->createNewsArticle($newsData, $imageFile);
                $this->filesystem->remove($newsDirectory);
                $this->logger->info('Successfully imported news from directory: ' . $newsDirectory);
            } catch (\Exception $e) {
                $this->logger->error('Error creating news article from directory: ' . $newsDirectory . ' - ' . $e->getMessage());
            }
        }
    }

    private function createNewsArticle(array $newsData, ?string $imageFile): void
    {
        // Hole die Archiv-ID aus den Contao-Einstellungen
        $archiveId = Config::get('news_pull_news_archive');
        
        if (!$archiveId) {
            throw new \Exception('No news archive configured in settings. Please select a news archive in the backend settings.');
        }

        // Hole das Nachrichtenarchiv
        $newsArchive = NewsArchiveModel::findByPk($archiveId);

        if (!$newsArchive) {
            throw new \Exception('Configured news archive (ID: ' . $archiveId . ') not found.');
        }

        // Create the news article
        $news = new NewsModel();
        $news->pid = $newsArchive->id;
        $news->headline = $newsData['title'];
        $news->alias = standardize($newsData['title']);
        $news->teaser = $newsData['teaser'];
        $news->author = 1; // Set a default author or retrieve from configuration
        $news->date = time();
        $news->time = time();
        $news->addImage = $imageFile !== null ? '1' : '';
        // Prüfe die Auto-Publish Einstellung
        $news->published = Config::get('news_pull_auto_publish') ? '1' : '';
        $news->metaTitle = $newsData['title'];
        $news->metaDescription = $newsData['teaser'];
        $news->save();

        // Create content elements
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
        $contentElement->save();
    }

    private function createImageElement(int $newsId, string $imageFile, string $altText): void
    {
        $contentElement = new ContentModel();
        $contentElement->pid = $newsId;
        $contentElement->ptable = 'tl_news';
        $contentElement->type = 'image';
        $contentElement->singleSRC = $imageFile;
        $contentElement->alt = $altText;
        $contentElement->imagemargin = serialize([
            'top' => '0',
            'right' => '0',
            'bottom' => '0',
            'left' => '0',
            'unit' => 'px'
        ]);
        $contentElement->save();
    }

    private function copyImage(string $imageFile): string
    {
        $imageName = basename($imageFile);
        $newPath = $this->imageDir . '/' . $imageName;
        $newFullPath = $this->projectDir . '/web' . $newPath;

        // Ensure the image directory exists
        if (!is_dir($this->projectDir . '/web' . $this->imageDir)) {
            (new Folder(str_replace('/files/', '', $this->imageDir)))->unprotect();
        }

        $this->filesystem->copy($imageFile, $newFullPath, true);

        return str_replace('/files/', '', $newPath); // Return path relative to /files/
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