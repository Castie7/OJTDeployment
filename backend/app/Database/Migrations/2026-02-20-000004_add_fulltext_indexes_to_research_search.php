<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFulltextIndexesToResearchSearch extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $builder = $this->db->table('INFORMATION_SCHEMA.STATISTICS');
        $row = $builder
            ->select('INDEX_NAME')
            ->where('TABLE_SCHEMA', $this->db->getDatabase())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->get()
            ->getRowArray();

        return !empty($row);
    }

    public function up()
    {
        if ($this->db->tableExists('researches')) {
            if (!$this->indexExists('researches', 'ft_researches_title_author')) {
                $this->db->query('ALTER TABLE researches ADD FULLTEXT ft_researches_title_author (title, author)');
            }
        }

        if ($this->db->tableExists('research_details')) {
            if (!$this->indexExists('research_details', 'ft_research_details_search')) {
                $columns = ['subjects', 'physical_description', 'publisher', 'knowledge_type', 'isbn_issn'];
                if ($this->db->fieldExists('search_text', 'research_details')) {
                    $columns[] = 'search_text';
                }
                $columnList = implode(', ', $columns);
                $this->db->query("ALTER TABLE research_details ADD FULLTEXT ft_research_details_search ({$columnList})");
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('researches') && $this->indexExists('researches', 'ft_researches_title_author')) {
            $this->db->query('ALTER TABLE researches DROP INDEX ft_researches_title_author');
        }

        if ($this->db->tableExists('research_details') && $this->indexExists('research_details', 'ft_research_details_search')) {
            $this->db->query('ALTER TABLE research_details DROP INDEX ft_research_details_search');
        }
    }
}

