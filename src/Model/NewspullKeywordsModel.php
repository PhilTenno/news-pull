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

        $keywordArray = array_map('trim', explode(',', strtolower($keywords)));
        $results = [];

        // Get all keyword records
        $objKeywords = static::findAll();
        
        if (!$objKeywords) {
            return [];
        }

        foreach ($objKeywords as $keywordRecord) {
            if ($keywordRecord->pid == $excludeId) {
                continue;
            }

            $recordKeywords = array_map('trim', explode(',', strtolower($keywordRecord->keywords)));
            $relevance = 0;

            // Calculate relevance based on keyword matches
            foreach ($keywordArray as $searchKeyword) {
                foreach ($recordKeywords as $recordKeyword) {
                    if (strpos($recordKeyword, $searchKeyword) !== false || 
                        strpos($searchKeyword, $recordKeyword) !== false) {
                        $relevance++;
                    }
                }
            }

            if ($relevance >= $minRelevance) {
                $results[] = [
                    'pid' => $keywordRecord->pid,
                    'relevance' => $relevance,
                    'keywords' => $keywordRecord->keywords
                ];
            }
        }

        // Sort by relevance (desc) and limit results
        usort($results, function($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });

        return array_slice($results, 0, $limit);
    }
}