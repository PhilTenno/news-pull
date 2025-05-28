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

#[AsFrontendModule('newspull_related', category: 'news')]
class RelatedNewsController extends AbstractFrontendModuleController
{
    public function __construct(
        private ContaoFramework $framework,       
    ) { 
      file_put_contents(__DIR__ . '/controller_debug.log', "RelatedNewsController instantiated\n", FILE_APPEND);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Get current news article from URL
        $currentNews = $this->getCurrentNewsArticle($request);
        $template->setName('frontend_module/newspull_related');

        if (!$currentNews) {
            return new Response('');
        }

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

        $template->set('related_news', $relatedNews);
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