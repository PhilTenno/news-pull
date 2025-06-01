<?php

declare(strict_types=1);

namespace PhilTenno\NewsPull\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\NewsModel;
use PhilTenno\NewsPull\Model\NewspullKeywordsModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule('newspullrelated', category: 'news', template: 'frontend_module/newspullrelated')]
class RelatedNewsController extends AbstractFrontendModuleController
{
    public function __construct(
        private \Contao\CoreBundle\Framework\ContaoFramework $framework
    ) { }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $currentNews = $this->getCurrentNewsArticle($request);

        if (!$currentNews) {
            return new Response('');
        }

        $keywordsModel = NewspullKeywordsModel::findByPid($currentNews->id);
        $keywords = $keywordsModel ? $keywordsModel->keywords : '';

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

        usort($relatedNews, function($a, $b) {
            if ($a['relevance'] === $b['relevance']) {
                return $b['article']->date <=> $a['article']->date;
            }
            return $b['relevance'] <=> $a['relevance'];
        });

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

    private function getCurrentNewsArticle(Request $request): ?\Contao\NewsModel
    {
        $alias = $request->attributes->get('auto_item');
        if (empty($alias)) {
            $alias = $request->query->get('items');
        }
        if (empty($alias) || !is_string($alias)) {
            return null;
        }
        $currentNews = \Contao\NewsModel::findByIdOrAlias($alias);
        if ($currentNews instanceof \Contao\NewsModel) {
            return $currentNews;
        } else {
            return null;
        }
    }
}