<?php

namespace App\Tests\Service;

use App\Service\DocumentSplitter;
use PHPUnit\Framework\TestCase;

class DocumentSplitterTest extends TestCase
{
    public function testSplitEmptyText(): void
    {
        $this->assertSame([], DocumentSplitter::split(''));
        $this->assertSame([], DocumentSplitter::split('   '));
        $this->assertSame([], DocumentSplitter::split("\n\t\n"));
    }

    public function testSplitShortText(): void
    {
        $text = 'This is a short text.';
        $result = DocumentSplitter::split($text, 100);

        $this->assertCount(1, $result);
        $this->assertSame($text, $result[0]);
    }

    public function testSplitByParagraphs(): void
    {
        $text = "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.";
        $result = DocumentSplitter::split($text, 50, 10);

        $this->assertGreaterThan(1, \count($result));
        $this->assertStringContainsString('First paragraph.', $result[0]);
    }

    public function testSplitBySentences(): void
    {
        $text = str_repeat('This is a sentence. ', 20);
        $result = DocumentSplitter::split($text, 100, 20);

        $this->assertGreaterThan(1, \count($result));

        foreach ($result as $chunk) {
            $this->assertLessThanOrEqual(120, mb_strlen($chunk)); // allowing for overlap
        }
    }

    public function testSplitByWords(): void
    {
        $text = str_repeat('word ', 100);
        $result = DocumentSplitter::split($text, 50, 10);

        $this->assertGreaterThan(1, \count($result));

        foreach ($result as $chunk) {
            $this->assertLessThanOrEqual(60, mb_strlen($chunk)); // allowing for overlap
        }
    }

    public function testOverlapFunctionality(): void
    {
        $text = 'First sentence. Second sentence. Third sentence. Fourth sentence.';
        $result = DocumentSplitter::split($text, 40, 15);

        $this->assertGreaterThan(1, \count($result));

        // Check that consecutive chunks have some overlap
        for ($i = 1; $i < \count($result); ++$i) {
            $previousWords = explode(' ', $result[$i - 1]);
            $currentWords = explode(' ', $result[$i]);

            // Should have some common words due to overlap
            $commonWords = array_intersect($previousWords, $currentWords);
            $this->assertNotEmpty($commonWords, 'Chunks should have overlapping content');
        }
    }

    public function testPreserveSentenceIntegrity(): void
    {
        $text = 'First sentence! Second sentence? Third sentence.';
        $result = DocumentSplitter::split($text, 30, 5);

        foreach ($result as $chunk) {
            // Each chunk should end with proper punctuation or be a word fragment
            $this->assertTrue(
                preg_match('/[.!?]$/', $chunk) || !preg_match('/[.!?]/', $chunk),
                "Chunk should preserve sentence integrity: {$chunk}"
            );
        }
    }

    public function testInvalidParameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DocumentSplitter::split('test', 0);
    }

    public function testNegativeOverlap(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DocumentSplitter::split('test', 100, -5);
    }

    public function testOverlapGreaterThanChunkSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DocumentSplitter::split('test', 50, 60);
    }

    public function testComplexDocument(): void
    {
        $text = <<<'TEXT'
This is the first paragraph with multiple sentences. It contains various punctuation marks! And questions?

This is the second paragraph. It has different content and structure. Some sentences are longer than others.

Final paragraph here. Short sentences. Very brief content.
TEXT;

        $result = DocumentSplitter::split($text, 150, 30);

        $this->assertNotEmpty($result);

        $totalReconstructed = implode(' ', $result);

        // Verify essential content is preserved (allowing for formatting changes)
        $this->assertStringContainsString('first paragraph', $totalReconstructed);
        $this->assertStringContainsString('second paragraph', $totalReconstructed);
        $this->assertStringContainsString('Final paragraph', $totalReconstructed);
    }

    public function testWhitespaceHandling(): void
    {
        $text = "Text   with    multiple     spaces\n\nand\t\ttabs.";
        $result = DocumentSplitter::split($text, 100);

        $this->assertNotEmpty($result);
        // Text should be preserved even if formatting changes
        $combined = implode(' ', $result);
        $this->assertStringContainsString('Text', $combined);
        $this->assertStringContainsString('with', $combined);
        $this->assertStringContainsString('multiple', $combined);
        $this->assertStringContainsString('spaces', $combined);
    }

    public function testVeryLongWordsHandling(): void
    {
        $longWord = str_repeat('a', 200);
        $text = "Short text {$longWord} more text.";

        $result = DocumentSplitter::split($text, 50, 10);

        $this->assertNotEmpty($result);

        // The long word should be handled appropriately
        $found = false;
        foreach ($result as $chunk) {
            if (str_contains($chunk, $longWord)) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Long word should be preserved in chunks');
    }
}
