<?php

namespace App\Services\Coach\Knowledge;

/**
 * Phase 11F — deterministic document chunking with stable hashes/ordering.
 */
class KnowledgeChunker
{
    public function __construct(
        private readonly int $maxChars = 900,
        private readonly int $minChars = 120,
    ) {}

    /**
     * Split text into ordered chunks on paragraph boundaries when possible.
     *
     * @return list<array{chunk_index: int, content: string, content_hash: string, token_estimate: int}>
     */
    public function chunk(string $text): array
    {
        $text = trim(preg_replace("/\r\n|\r/", "\n", $text) ?? $text);
        if ($text === '') {
            return [];
        }

        $paragraphs = preg_split("/\n{2,}/", $text) ?: [$text];
        $buffers = [];
        $current = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }
            $candidate = $current === '' ? $para : $current."\n\n".$para;
            if (mb_strlen($candidate) <= $this->maxChars) {
                $current = $candidate;

                continue;
            }
            if ($current !== '') {
                $buffers[] = $current;
            }
            if (mb_strlen($para) <= $this->maxChars) {
                $current = $para;
            } else {
                foreach ($this->hardSplit($para) as $piece) {
                    $buffers[] = $piece;
                }
                $current = '';
            }
        }
        if ($current !== '') {
            $buffers[] = $current;
        }

        // Merge tiny trailing fragments into previous when possible.
        $merged = [];
        foreach ($buffers as $buf) {
            if ($merged !== [] && mb_strlen($buf) < $this->minChars) {
                $last = array_pop($merged);
                $combined = $last."\n\n".$buf;
                if (mb_strlen($combined) <= $this->maxChars + 100) {
                    $merged[] = $combined;

                    continue;
                }
                $merged[] = $last;
            }
            $merged[] = $buf;
        }

        $out = [];
        foreach ($merged as $i => $content) {
            $out[] = [
                'chunk_index' => $i,
                'content' => $content,
                'content_hash' => hash('sha256', $content),
                'token_estimate' => $this->estimateTokens($content),
            ];
        }

        return $out;
    }

    public function hash(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * @return list<string>
     */
    private function hardSplit(string $text): array
    {
        $parts = [];
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i += $this->maxChars) {
            $parts[] = trim(mb_substr($text, $i, $this->maxChars));
        }

        return array_values(array_filter($parts));
    }

    private function estimateTokens(string $content): int
    {
        // Rough English estimate (~4 chars/token); deterministic only.
        return max(1, (int) ceil(mb_strlen($content) / 4));
    }
}
