<?php

declare(strict_types=1);

namespace PhilTenno\NewsPull\Model;

use Contao\Model;

class NewspullKeywordsModel extends Model
{
    protected static $strTable = 'tl_newspull_keywords';

    /**
     * Find keywords by news article ID
     */
    public static function findByPid(int $pid): ?self
    {
        return static::findOneBy('pid', $pid);
    }

    /**
     * Find related news articles by keywords with relevance scoring
     */
    public static function findRelatedNews(
        string $keywords, 
        int $excludeId = 0, 
        int $minRelevance = 1, 
        int $limit = 5,
        array $archives = []
    ): array {
        if (empty($keywords)) {
            return [];
        }

        $keywordArray = array_filter(array_map('trim', preg_split('/[,;\\s]+/', strtolower($keywords))));
        $results = [];

        $objKeywords = static::findAll();
        if (!$objKeywords) {
            return [];
        }

        foreach ($objKeywords as $keywordRecord) {
            if ($keywordRecord->pid == $excludeId) {
                continue;
            }

            // KORREKT: Archiv-Filter auf Basis der News-Archiv-ID
            if (!empty($archives)) {
                $news = \Contao\NewsModel::findByPk($keywordRecord->pid);
                if (!$news || !in_array($news->pid, $archives)) {
                    continue;
                }
            }

            $recordKeywords = array_filter(array_map('trim', preg_split('/[,;\\s]+/', strtolower($keywordRecord->keywords))));
            $common = array_intersect($keywordArray, $recordKeywords);
            $relevance = count($common);

            if ($relevance >= $minRelevance) {
                $results[] = [
                    'pid' => $keywordRecord->pid,
                    'relevance' => $relevance,
                    'keywords' => $keywordRecord->keywords,
                    'common_keywords' => implode(', ', $common)
                ];
            }
        }

        usort($results, function($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });

        return array_slice($results, 0, $limit);
    }
}