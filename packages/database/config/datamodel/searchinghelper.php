<?php
namespace Database;

use Database\Database;

class SearchingHelper
{


    public static function NormalizeAlgorithmSimple(Tableschema $tableSchema, string $column, string $query, int $limit=0, array $whereClauses=[]): array
    {
        $db = $tableSchema->GetDatabase()->connect();
        $count = count($whereClauses);
        $whereClause = $count > 0 ? 'WHERE ' : '';

        foreach ($whereClauses as $key => $value)
        {
            $whereClause .= "$key = $value"; // Risk of SQL Injection

            if ($count > 1)
            {
                $whereClause .= ' AND ';
            }
            $count--;
        }

        $sql = "
            SELECT *
            FROM {$tableSchema->GetTableName()}
            WHERE 
                LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($column, 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'y', 'i'), 'v', 'b'), 'h', '')), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'y', 'i'), 'v', 'b'), 'h', ''), 'x', 'j')) LIKE :query
                OR SOUNDEX(LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($column, 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'y', 'i'), 'v', 'b'), 'h', ''), 'x', 'j'))) = SOUNDEX(:soundex_query)
                OR LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($column, 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'y', 'i'), 'v', 'b'), 'h', ''), 'x', 'j')) LIKE :levenshtein_query
                OR LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($column, 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'y', 'i'), 'v', 'b'), 'h', ''), 'x', 'j')) LIKE :metaphone_query
            " . $whereClause . ($limit > 0 ? " LIMIT $limit" : '');

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':query' => '%' . $query . '%',
            ':soundex_query' => $query,
            ':levenshtein_query' => '%' . $query . '%',
            ':metaphone_query' => '%' . $query . '%'
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);

    }


    public static function ComplexNormalizeAlgorithm(Tableschema $tableSchema, string $column, string $query, int $limit=0, array $whereClauses=[]): array
    {
        $seekers = explode(' ', $query);

        //Delete whitespaces
        $seekers = array_filter($seekers, function($seeker)
        {
            return $seeker !== '';
        });

        // Call NormalizeAlgorithmSimple for each seeker
        $results = [];

        foreach ($seekers as $seeker)
        {
            $results[] = self::NormalizeAlgorithmSimple($tableSchema, $column, $seeker, 0, $whereClauses);
        }

        //Determine wich result repeats the most in all array of results
        $repeated = [];
        $flatDictionary = [];

        foreach ($results as $seekerResult)
        {
            foreach ($seekerResult as $individualResult)
            {
                if (array_key_exists($individualResult[$column], $repeated))
                {
                    $repeated[$individualResult[$column]]++;
                }
                else
                {
                    $repeated[$individualResult[$column]] = 1;
                    $flatDictionary[$individualResult[$column]] = $individualResult;
                }
            }
        }

        //Get max frequency
        $max = max($repeated);

        //Delete all with frequency 1 when there is a frequency greater than 1

        if($max > 1)
        foreach ($repeated as $key => $value)
        {
            if ($value == 1)
            {
                unset($flatDictionary[$key]);
            }
        }
    


        //Order the dictionary by the most repeated
        uasort($flatDictionary, function($a, $b) use ($repeated, $column)
        {
            return $repeated[$b[$column]] <=> $repeated[$a[$column]];
        });

        // Limit the dictionary
        if ($limit > 0)
        {
            $flatDictionary = array_slice($flatDictionary, 0, $limit);
        }
    
        return array_values($flatDictionary);
    }
    
}