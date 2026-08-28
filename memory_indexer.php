<?php
/**
 * Episodic Memory Indexer — AI Token Optimizer
 * Implements Hermes Agent Pattern #4: Episodic memory search & trajectory compression.
 * Works natively on all Linux environments (SQLite FTS5 + JSON fallback engine).
 */

class MemoryIndexer {
    private ?PDO $db = null;
    private string $jsonPath;

    public function __construct(?string $customPath = null) {
        $this->jsonPath = __DIR__ . '/data/memory_fts.json';
        $dir = dirname($this->jsonPath);
        if (!file_exists($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Try initializing SQLite if pdo_sqlite driver exists
        if (extension_loaded('pdo_sqlite')) {
            try {
                $dbPath = $customPath ?: __DIR__ . '/data/memory.fts.db';
                $this->db = new PDO("sqlite:" . $dbPath);
                $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->db->exec("
                    CREATE VIRTUAL TABLE IF NOT EXISTS session_fts USING fts5(
                        session_id, role, content, timestamp UNINDEXED
                    );
                ");
            } catch (Exception $e) {
                $this->db = null;
            }
        }

        $this->seedInitialData();
    }

    private function seedInitialData(): void {
        if (!file_exists($this->jsonPath)) {
            $initial = [
                ['session_id' => 'sess_001', 'role' => 'user', 'content' => 'How to optimize prompt tokens and reduce reasoning tax in Linux IDE agents?', 'timestamp' => time() - 3600],
                ['session_id' => 'sess_001', 'role' => 'assistant', 'content' => 'Apply Guide 2026 rules: stable header prompt caching, noise exclusions, and lazy tool schemas.', 'timestamp' => time() - 3500],
                ['session_id' => 'sess_002', 'role' => 'user', 'content' => 'What is the Hermes Agent pattern for memory retention?', 'timestamp' => time() - 1800],
                ['session_id' => 'sess_002', 'role' => 'assistant', 'content' => 'Hermes Agent uses SQLite FTS5 episodic search with curated MEMORY.md and procedural skill documents.', 'timestamp' => time() - 1700],
            ];
            file_put_contents($this->jsonPath, json_encode($initial, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Index a single message turn into Memory Index
     */
    public function indexTurn(string $sessionId, string $role, string $content): bool {
        $entry = [
            'session_id' => $sessionId,
            'role' => $role,
            'content' => $content,
            'timestamp' => time()
        ];

        // SQLite indexing if available
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("INSERT INTO session_fts(session_id, role, content, timestamp) VALUES (?, ?, ?, ?)");
                $stmt->execute([$sessionId, $role, $content, time()]);
            } catch (Exception $e) {}
        }

        // Native JSON indexing
        $data = file_exists($this->jsonPath) ? json_decode(file_get_contents($this->jsonPath), true) : [];
        if (!is_array($data)) $data = [];
        $data[] = $entry;
        return (bool)file_put_contents($this->jsonPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Search episodic memory matching query keywords
     */
    public function searchMemory(string $query, int $limit = 5): array {
        $cleanQuery = trim(strtolower(preg_replace('/[^a-zA-Z0-9_\s]/', '', $query)));
        if (empty($cleanQuery)) return [];

        // SQLite FTS5 attempt
        if ($this->db) {
            try {
                $words = array_filter(explode(' ', $cleanQuery));
                $ftsQuery = implode(' ', array_map(fn($w) => "$w*", $words));
                $stmt = $this->db->prepare("
                    SELECT session_id, role, content, timestamp, rank
                    FROM session_fts
                    WHERE session_fts MATCH ?
                    ORDER BY rank LIMIT ?
                ");
                $stmt->execute([$ftsQuery, $limit]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($results)) return $results;
            } catch (Exception $e) {}
        }

        // Native High-Speed Search Engine
        if (!file_exists($this->jsonPath)) return [];
        $data = json_decode(file_get_contents($this->jsonPath), true);
        if (!is_array($data)) return [];

        $keywords = array_filter(explode(' ', $cleanQuery));
        $matches = [];

        foreach ($data as $item) {
            $contentLower = strtolower($item['content']);
            $score = 0;
            foreach ($keywords as $kw) {
                if (str_contains($contentLower, $kw)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $item['score'] = $score;
                $matches[] = $item;
            }
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($matches, 0, $limit);
    }

    /**
     * Compress multi-turn execution trajectory into concise bullet points
     */
    public function compressTrajectory(array $turns): array {
        $compressed = [];
        foreach ($turns as $turn) {
            $role = $turn['role'] ?? 'user';
            $content = $turn['content'] ?? '';
            $clean = preg_replace('/\s+/', ' ', $content);
            if (strlen($clean) > 150) {
                $clean = substr($clean, 0, 147) . '...';
            }
            $compressed[] = "[" . strtoupper($role) . "] " . $clean;
        }
        return $compressed;
    }
}
