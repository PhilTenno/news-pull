<?php

declare(strict_types=1);

namespace PhilTenno\NewsPull\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\NewsModel;
use Contao\StringUtil;
use PhilTenno\NewsPull\Model\NewspullKeywordsModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule('newspull_related',category: 'news', template:'newspull_related')]
class RelatedNewsController extends AbstractFrontendModuleController
{
    public function __construct(
        private ContaoFramework $framework,       
    ) { }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Get current news article from URL
        $currentNews = $this->getCurrentNewsArticle($request);

        $templateName = $model->news_template ?: 'newspull_related';
        $template->setName($model->customTpl ?: 'frontend_module/newspull_related.html.twig');

        if (!$currentNews) {
            return new Response('');
        }

        // Get keywords for current article
        $keywordsModel = NewspullKeywordsModel::findByPid($currentNews->id);
        $keywords = $keywordsModel ? $keywordsModel->keywords : '';

        // Find related articles
        $archives = \Contao\StringUtil::deserialize($model->news_archives, true);
        $relatedResults = [];
        if ($keywords) {
            $relatedResults = NewspullKeywordsModel::findRelatedNews(
                $keywords,
                $currentNews->id,
                (int) ($model->newspull_min_relevance ?: 1),
                (int) ($model->newspull_max_results ?: 5),
                $archives
            );
        }

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

        // Fallback: Wenn keine verwandten News gefunden wurden, zeige die letzten 3 News aus dem gleichen Archiv (außer die aktuelle)
        if (empty($relatedNews)) {
            $fallbackNews = [];
            $archiveId = $currentNews->pid;
            $fallbackCollection = NewsModel::findBy(
                ['pid=?', 'published=1', 'id!=?'],
                [$archiveId, $currentNews->id],
                ['order' => 'date DESC', 'limit' => 3]
            );
            if ($fallbackCollection !== null) {
                foreach ($fallbackCollection as $newsArticle) {
                    $fallbackNews[] = [
                        'article' => $newsArticle,
                        'relevance' => 0,
                        'date' => date('Y-m-d', $newsArticle->date),
                        'datetime' => date('c', $newsArticle->date)
                    ];
                }
            }
            $template->set('related_news', $fallbackNews);
            $template->set('is_fallback', true);
        } else {
            $template->set('related_news', $relatedNews);
            $template->set('is_fallback', false);
        }

        $template->set('current_news', $currentNews);
        $template->set('module', $model);
        $template->set('headline', $model->headline ?: 'Ähnliche Artikel');

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