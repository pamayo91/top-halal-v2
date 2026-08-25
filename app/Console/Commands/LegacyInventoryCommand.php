<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LegacyInventoryCommand extends Command
{
    protected $signature = 'legacy:inventory
        {--connection=legacy_wp : Legacy WordPress database connection}
        {--out=docs/generated/legacy-inventory : Output path without extension}';

    protected $description = 'Inventory the imported legacy WordPress database without exporting PII or content bodies.';

    public function handle(): int
    {
        $connectionName = (string) $this->option('connection');
        $outBase = base_path((string) $this->option('out'));
        $connection = DB::connection($connectionName);
        $database = (string) $connection->getDatabaseName();
        $prefix = (string) config("database.connections.$connectionName.prefix", '');

        $tables = $this->tables($connection, $database);
        $tableNames = array_column($tables, 'name');
        $has = fn (string $table): bool => in_array($prefix.$table, $tableNames, true);
        $table = fn (string $table): string => $prefix.$table;

        $required = ['posts', 'postmeta', 'users', 'comments', 'terms', 'term_taxonomy', 'term_relationships', 'options'];
        $missingRequired = array_values(array_filter($required, fn (string $name): bool => ! $has($name)));

        $inventory = [
            'generated_at' => now()->toIso8601String(),
            'connection' => $connectionName,
            'database' => $database,
            'table_prefix' => $prefix,
            'tables' => $tables,
            'post_types_statuses' => $has('posts') ? $this->groupCount($connection, $table('posts'), ['post_type', 'post_status']) : [],
            'users' => [
                'count' => $has('users') ? $this->count($connection, $table('users')) : null,
            ],
            'comments_by_approval_type' => $has('comments') ? $this->groupCount($connection, $table('comments'), ['comment_approved', 'comment_type']) : [],
            'listingpro_reviews' => [
                'post_type_lp_reviews' => $has('posts') ? $this->countWhere($connection, $table('posts'), 'post_type = ?', ['lp-reviews']) : null,
                'comment_type_reviews' => $has('comments') ? $this->countWhere($connection, $table('comments'), 'comment_type IN (?, ?)', ['review', 'lp_review']) : null,
            ],
            'taxonomies' => $has('term_taxonomy') ? $this->groupCount($connection, $table('term_taxonomy'), ['taxonomy']) : [],
            'media' => [
                'attachments' => $has('posts') ? $this->countWhere($connection, $table('posts'), 'post_type = ?', ['attachment']) : null,
            ],
            'seo_plugin_data_presence' => $this->seoPresence($connection, $table, $has),
            'anomalies' => $this->anomalies($connection, $table, $has, $missingRequired),
        ];

        File::ensureDirectoryExists(dirname($outBase));
        File::put($outBase.'.json', json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($outBase.'.md', $this->markdown($inventory));

        $this->info('Legacy inventory written:');
        $this->line($outBase.'.json');
        $this->line($outBase.'.md');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, rows: int|null}>
     */
    private function tables(ConnectionInterface $connection, string $database): array
    {
        return collect($connection->select(
            'SELECT TABLE_NAME AS name, TABLE_ROWS AS rows
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME',
            [$database],
        ))->map(fn (object $row): array => [
            'name' => (string) $row->name,
            'rows' => $row->rows === null ? null : (int) $row->rows,
        ])->all();
    }

    /**
     * @param array<int, string> $columns
     * @return array<int, array<string, int|string|null>>
     */
    private function groupCount(ConnectionInterface $connection, string $table, array $columns): array
    {
        $select = implode(', ', array_map(fn (string $column): string => "`$column`", $columns));
        $order = $select;

        return collect($connection->select(
            "SELECT $select, COUNT(*) AS total FROM `$table` GROUP BY $select ORDER BY $order",
        ))->map(fn (object $row): array => collect((array) $row)
            ->map(fn ($value) => is_numeric($value) ? (int) $value : $value)
            ->all())->all();
    }

    private function count(ConnectionInterface $connection, string $table): int
    {
        $row = $connection->selectOne("SELECT COUNT(*) AS total FROM `$table`");

        return (int) $row->total;
    }

    /**
     * @param array<int, mixed> $bindings
     */
    private function countWhere(ConnectionInterface $connection, string $table, string $where, array $bindings): int
    {
        $row = $connection->selectOne("SELECT COUNT(*) AS total FROM `$table` WHERE $where", $bindings);

        return (int) $row->total;
    }

    /**
     * @param callable(string): string $table
     * @param callable(string): bool $has
     * @return array<string, mixed>
     */
    private function seoPresence(ConnectionInterface $connection, callable $table, callable $has): array
    {
        return [
            'yoast_postmeta_rows' => $has('postmeta')
                ? $this->countWhere($connection, $table('postmeta'), 'meta_key LIKE ?', ['_yoast_wpseo_%'])
                : null,
            'aioseo_postmeta_rows' => $has('postmeta')
                ? $this->countWhere($connection, $table('postmeta'), 'meta_key LIKE ?', ['_aioseo_%'])
                : null,
            'yoast_indexable_table' => $has('yoast_indexable'),
            'aioseo_posts_table' => $has('aioseo_posts'),
        ];
    }

    /**
     * @param callable(string): string $table
     * @param callable(string): bool $has
     * @param array<int, string> $missingRequired
     * @return array<string, mixed>
     */
    private function anomalies(ConnectionInterface $connection, callable $table, callable $has, array $missingRequired): array
    {
        return [
            'missing_required_tables' => $missingRequired,
            'spam_comments' => $has('comments')
                ? $this->countWhere($connection, $table('comments'), 'comment_approved = ?', ['spam'])
                : null,
            'comments_with_url_like_content' => $has('comments')
                ? $this->countWhere($connection, $table('comments'), 'comment_content REGEXP ?', ['https?://|www\\.'])
                : null,
            'posts_with_visual_composer_shortcodes' => $has('posts')
                ? $this->countWhere($connection, $table('posts'), 'post_content LIKE ?', ['%[vc_%'])
                : null,
            'orphan_postmeta_rows' => ($has('postmeta') && $has('posts'))
                ? $this->countOrphans($connection, $table('postmeta'), 'post_id', $table('posts'), 'ID')
                : null,
            'orphan_term_relationship_rows' => ($has('term_relationships') && $has('posts'))
                ? $this->countOrphans($connection, $table('term_relationships'), 'object_id', $table('posts'), 'ID')
                : null,
        ];
    }

    private function countOrphans(
        ConnectionInterface $connection,
        string $childTable,
        string $childColumn,
        string $parentTable,
        string $parentColumn,
    ): int {
        $row = $connection->selectOne(
            "SELECT COUNT(*) AS total
             FROM `$childTable` child
             LEFT JOIN `$parentTable` parent ON parent.`$parentColumn` = child.`$childColumn`
             WHERE parent.`$parentColumn` IS NULL",
        );

        return (int) $row->total;
    }

    /**
     * @param array<string, mixed> $inventory
     */
    private function markdown(array $inventory): string
    {
        $lines = [
            '# Legacy WordPress Inventory',
            '',
            '- Generated at: `'.$inventory['generated_at'].'`',
            '- Connection: `'.$inventory['connection'].'`',
            '- Database: `'.$inventory['database'].'`',
            '- Table prefix: `'.$inventory['table_prefix'].'`',
            '- Tables: '.count($inventory['tables']),
            '- Users: '.($inventory['users']['count'] ?? 'n/a'),
            '- Attachments: '.($inventory['media']['attachments'] ?? 'n/a'),
            '',
            '## Anomalies',
            '',
        ];

        foreach ($inventory['anomalies'] as $name => $value) {
            $rendered = is_array($value) ? implode(', ', $value) : (string) ($value ?? 'n/a');
            $lines[] = '- '.$name.': '.$rendered;
        }

        $lines[] = '';
        $lines[] = 'Machine-readable details are in the adjacent JSON file. No PII or full content bodies are exported.';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
