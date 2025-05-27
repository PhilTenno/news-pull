<?php

declare(strict_types=1);

namespace PhilTenno\NewsPull\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\NewsModel;
use Contao\StringUtil;
use PhilTenno\NewsPull\Model\NewspullKeywordsModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsFrontendModule('newspull_related', category: 'news')]
class RelatedNewsController extends AbstractFrontendModuleController
{
    public function __construct(
        private ContaoFramework $framework,
        private TagAwareAdapterInterface $cache,
        private ContentUrlGenerator $contentUrlGenerator
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Get current news article from URL
        $currentNews = $this->getCurrentNewsArticle($request);
        
        if (!$currentNews) {
            return new Response('');
        }

        // Get cache key
        $cacheKey = 'newspull_related_' . $currentNews->id . '_' . md5(serialize($model->row()));
        $cacheDuration = (int) ($model->newspull_cache_duration ?: 3600);

        // Try to get from cache
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            $relatedNews = $cacheItem->get();
        } else {
            // Get keywords for current article
            $keywordsModel = NewspullKeywordsModel::findByPid($currentNews->id);
            
            if (!$keywordsModel) {
                return new Response('');
            }

            // Find related articles
            $archives = StringUtil::deserialize($model->news_archives, true);
            $relatedResults = NewspullKeywordsModel::findRelatedNews(
                $keywordsModel->keywords,
                $currentNews->id,
                (int) ($model->newspull_min_relevance ?: 1),
                (int) ($model->newspull_max_results ?: 5),
                $archives
            );

            $relatedNews = [];
            
            foreach ($relatedResults as $result) {
                $newsArticle = NewsModel::findByPk($result['pid']);
                
                if ($newsArticle && $newsArticle->published) {
                    // Check if article is in selected archives
                    if (!empty($archives) && !in_array($newsArticle->pid, $archives)) {
                        continue;
                    }
                    
                    $relatedNews[] = [
                        'article' => $newsArticle,
                        'relevance' => $result['relevance'],
                        'url' => $this->contentUrlGenerator->generate(
                            $newsArticle, 
                            [], 
                            UrlGeneratorInterface::ABSOLUTE_PATH
                        ),
                        'date' => date('Y-m-d', $newsArticle->date),
                        'datetime' => date('c', $newsArticle->date)
                    ];
                }
            }

            // Sort by relevance, then by date (newer first)
            usort($relatedNews, function($a, $b) {
                if ($a['relevance'] === $b['relevance']) {
                    return $b['article']->date <=> $a['article']->date;
                }
                return $b['relevance'] <=> $a['relevance'];
            });

            // Cache the results
            $cacheItem->set($relatedNews);
            $cacheItem->expiresAfter($cacheDuration);
            $cacheItem->tag(['contao.db.tl_news', 'contao.db.tl_newspull_keywords']);
            $this->cache->save($cacheItem);
        }

        $template->setData([
            'related_news' => $relatedNews,
            'current_news' => $currentNews,
            'module' => $model,
            'headline' => $model->headline ?: 'Ähnliche Artikel'
        ]);

        return $template->getResponse();
    }

    private function getCurrentNewsArticle(Request $request): ?NewsModel
    {
        // Get news from auto_item or items parameter
        $alias = $request->query->get('auto_item') ?: $request->query->get('items');
        
        if (!$alias) {
            return null;
        }

        return NewsModel::findByIdOrAlias($alias);
    }
}