<?php

declare(strict_types=1);

namespace App\Service;

final class DocumentSplitter
{
    private const DEFAULT_CHUNK_SIZE = 500;
    private const DEFAULT_OVERLAP = 50;
    private const SENTENCE_ENDING_PATTERN = '/[.!?]+\s*/';
    private const WHITESPACE_PATTERN = '/\s+/';

    /**
     * @return array<string>
     */
    public static function split(string $text, int $chunkSize = self::DEFAULT_CHUNK_SIZE, int $overlap = self::DEFAULT_OVERLAP): array
    {
        if (empty(mb_trim($text))) {
            return [];
        }

        self::validateParameters($chunkSize, $overlap);

        $paragraphs = self::splitIntoParagraphs($text);
        if (empty($paragraphs)) {
            return [];
        }

        return self::processParagraphs($paragraphs, $chunkSize, $overlap);
    }

    private static function validateParameters(int $chunkSize, int $overlap): void
    {
        if ($chunkSize <= 0) {
            throw new \InvalidArgumentException('Chunk size must be greater than 0');
        }

        if ($overlap < 0) {
            throw new \InvalidArgumentException('Overlap cannot be negative');
        }

        if ($overlap >= $chunkSize) {
            throw new \InvalidArgumentException('Overlap must be less than chunk size');
        }
    }

    /**
     * @return array<string>
     */
    private static function splitIntoParagraphs(string $text): array
    {
        $paragraphs = [];
        foreach (explode("\n", $text) as $paragraph) {
            $trimmed = mb_trim($paragraph);
            if ('' !== $trimmed) {
                $paragraphs[] = $trimmed;
            }
        }

        return $paragraphs;
    }

    /**
     * @param array<string> $paragraphs
     *
     * @return array<string>
     */
    private static function processParagraphs(array $paragraphs, int $chunkSize, int $overlap): array
    {
        $chunks = [];
        $currentChunk = [];
        $currentLength = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraphLength = mb_strlen($paragraph);

            if ($paragraphLength > $chunkSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = implode("\n", $currentChunk);
                    $currentChunk = [];
                    $currentLength = 0;
                }

                $subChunks = self::splitLargeParagraph($paragraph, $chunkSize, $overlap);
                $chunks = array_merge($chunks, $subChunks);
                continue;
            }

            $newLength = $currentLength + ($currentLength > 0 ? 1 : 0) + $paragraphLength;

            if ($newLength > $chunkSize && !empty($currentChunk)) {
                $chunks[] = implode("\n", $currentChunk);
                $currentChunk = [$paragraph];
                $currentLength = $paragraphLength;
            } else {
                $currentChunk[] = $paragraph;
                $currentLength = $newLength;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = implode("\n", $currentChunk);
        }

        return array_filter($chunks, static fn (string $chunk): bool => '' !== mb_trim($chunk));
    }

    /**
     * @return array<string>
     */
    private static function splitLargeParagraph(string $paragraph, int $chunkSize, int $overlap): array
    {
        $normalizedText = preg_replace(self::WHITESPACE_PATTERN, ' ', mb_trim($paragraph));

        if (null === $normalizedText) {
            return [];
        }

        $sentences = self::extractSentences($normalizedText);

        if (empty($sentences)) {
            return self::splitByWords($normalizedText, $chunkSize, $overlap);
        }

        return self::processSentences($sentences, $chunkSize, $overlap);
    }

    /**
     * @return array<string>
     */
    private static function extractSentences(string $text): array
    {
        $sentences = preg_split(self::SENTENCE_ENDING_PATTERN, $text, -1, \PREG_SPLIT_NO_EMPTY);

        if (false === $sentences) {
            return [];
        }

        $result = [];
        $parts = preg_split(self::SENTENCE_ENDING_PATTERN, $text, -1, \PREG_SPLIT_DELIM_CAPTURE);

        if (false === $parts) {
            return array_map('trim', $sentences);
        }

        for ($i = 0; $i < \count($parts) - 1; $i += 2) {
            if (isset($parts[$i + 1])) {
                $sentence = mb_trim($parts[$i].mb_trim($parts[$i + 1]));
                if ('' !== $sentence) {
                    $result[] = $sentence;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string> $sentences
     *
     * @return array<string>
     */
    private static function processSentences(array $sentences, int $chunkSize, int $overlap): array
    {
        $chunks = [];
        $currentChunk = [];
        $currentLength = 0;
        $overlapBuffer = [];

        foreach ($sentences as $sentence) {
            $sentenceLength = mb_strlen($sentence);

            if ($sentenceLength > $chunkSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = implode(' ', $currentChunk);
                    $overlapBuffer = self::createOverlapBuffer($currentChunk, $overlap);
                    $currentChunk = [];
                    $currentLength = 0;
                }

                $wordChunks = self::splitByWords($sentence, $chunkSize, $overlap);
                $chunks = array_merge($chunks, $wordChunks);
                continue;
            }

            $newLength = $currentLength + ($currentLength > 0 ? 1 : 0) + $sentenceLength;

            if ($newLength > $chunkSize && !empty($currentChunk)) {
                $chunks[] = implode(' ', $currentChunk);
                $overlapBuffer = self::createOverlapBuffer($currentChunk, $overlap);

                $currentChunk = array_merge($overlapBuffer, [$sentence]);
                $currentLength = array_sum(array_map('strlen', $currentChunk)) + \count($currentChunk) - 1;
            } else {
                $currentChunk[] = $sentence;
                $currentLength = $newLength;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = implode(' ', $currentChunk);
        }

        return $chunks;
    }

    /**
     * @param array<string> $items
     *
     * @return array<string>
     */
    private static function createOverlapBuffer(array $items, int $overlap): array
    {
        if ($overlap <= 0 || empty($items)) {
            return [];
        }

        $buffer = [];
        $bufferLength = 0;

        for ($i = \count($items) - 1; $i >= 0 && $bufferLength < $overlap; --$i) {
            $itemLength = mb_strlen($items[$i]);
            if ($bufferLength + $itemLength <= $overlap) {
                array_unshift($buffer, $items[$i]);
                $bufferLength += $itemLength;
            } else {
                break;
            }
        }

        return $buffer;
    }

    /**
     * @return array<string>
     */
    private static function splitByWords(string $text, int $chunkSize, int $overlap): array
    {
        $words = array_filter(explode(' ', mb_trim($text)), static fn (string $word): bool => '' !== $word);

        $chunks = [];
        $currentWords = [];
        $currentLength = 0;

        foreach ($words as $word) {
            $wordLength = mb_strlen($word);
            $newLength = $currentLength + ($currentLength > 0 ? 1 : 0) + $wordLength;

            if ($newLength > $chunkSize && !empty($currentWords)) {
                $chunks[] = implode(' ', $currentWords);

                $overlapWords = self::createOverlapBuffer($currentWords, $overlap);
                $currentWords = array_merge($overlapWords, [$word]);
                $currentLength = array_sum(array_map('strlen', $currentWords)) + \count($currentWords) - 1;
            } else {
                $currentWords[] = $word;
                $currentLength = $newLength;
            }
        }

        if (!empty($currentWords)) {
            $chunks[] = implode(' ', $currentWords);
        }

        return $chunks;
    }
}
