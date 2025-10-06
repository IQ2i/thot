<?php

namespace App\Tests\Service;

use App\Service\MarkdownCleaner;
use PHPUnit\Framework\TestCase;

class MarkdownCleanerTest extends TestCase
{
    public function testCleanEmptyString(): void
    {
        $this->assertSame('', MarkdownCleaner::clean(''));
    }

    public function testCleanWhitespaceOnlyString(): void
    {
        $this->assertSame('', MarkdownCleaner::clean('   '));
        $this->assertSame('', MarkdownCleaner::clean("\n\t  \n"));
    }

    public function testRemoveImages(): void
    {
        $input = 'Text ![alt text](image.jpg) more text';
        $expected = 'Text more text';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveImagesWithReferenceStyle(): void
    {
        $input = 'Text ![alt text][ref] more text';
        $expected = 'Text more text';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testConvertLinksToText(): void
    {
        $input = 'Check out [this link](https://example.com) for more info';
        $expected = 'Check out this link for more info';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testConvertReferenceLinksToText(): void
    {
        $input = 'Check out [this link][ref] for more info';
        $expected = 'Check out this link for more info';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveLinkReferences(): void
    {
        $input = "Some text\n[ref]: https://example.com\nMore text";
        $expected = "Some text\n\nMore text";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveHeaders(): void
    {
        $input = "# Header 1\n## Header 2\n### Header 3\n#### Header 4\n##### Header 5\n###### Header 6";
        $expected = "Header 1\nHeader 2\nHeader 3\nHeader 4\nHeader 5\nHeader 6";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveInlineCode(): void
    {
        $input = 'Use the `code` function here';
        $expected = 'Use the code function here';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveCodeBlocks(): void
    {
        $input = "Text before\n```php\necho 'hello';\n```\nText after";
        $expected = "Text before\n``php\necho 'hello';\n``\nText after";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveTildeCodeBlocks(): void
    {
        $input = "Text before\n~~~\ncode here\n~~~\nText after";
        $expected = "Text before\n\nText after";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveIndentedCodeBlocks(): void
    {
        $input = "Normal text\n    indented code\n        more indented\nNormal again";
        $expected = "Normal text\n\nNormal again";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveTabIndentedCodeBlocks(): void
    {
        $input = "Normal text\n\ttab indented code\nNormal again";
        $expected = "Normal text\n\nNormal again";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveBoldText(): void
    {
        $input = 'This is **bold** and this is __also bold__';
        $expected = 'This is bold and this is also bold';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveItalicText(): void
    {
        $input = 'This is *italic* and this is _also italic_';
        $expected = 'This is italic and this is also italic';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveBoldItalicText(): void
    {
        $input = 'This is ***bold italic*** and this is ___also bold italic___';
        $expected = 'This is bold italic and this is also bold italic';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveBlockquotes(): void
    {
        $input = "> This is a blockquote\n> with multiple lines";
        $expected = "This is a blockquote\nwith multiple lines";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveUnorderedLists(): void
    {
        $input = "* Item 1\n- Item 2\n+ Item 3";
        $expected = "Item 1\nItem 2\nItem 3";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveOrderedLists(): void
    {
        $input = "1. First item\n2. Second item\n10. Tenth item";
        $expected = "First item\nSecond item\nTenth item";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveTablePipes(): void
    {
        $input = "| Column 1 | Column 2 |\n|----------|----------|\n| Value 1  | Value 2  |";
        $expected = "Column 1 Column 2\n\nValue 1 Value 2";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveTableSeparators(): void
    {
        $input = "Header\n|:---:|---:|\nContent";
        $expected = "Header\n\nContent";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveHorizontalRules(): void
    {
        $input = "Text before\n---\nText after\n***\nMore text\n___\nFinal text";
        $expected = "Text before\n\nText after\n\nMore text\n\nFinal text";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveHtmlTags(): void
    {
        $input = 'Text with <strong>HTML</strong> and <em>tags</em>';
        $expected = 'Text with HTML and tags';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveHtmlComments(): void
    {
        $input = 'Text before <!-- this is a comment --> text after';
        $expected = 'Text before text after';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveMultilineHtmlComments(): void
    {
        $input = "Text before\n<!-- this is a\nmultiline comment -->\ntext after";
        $expected = "Text before\n\ntext after";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testNormalizeWhitespace(): void
    {
        $input = 'Text  with   multiple    spaces';
        $expected = 'Text with multiple spaces';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testNormalizeNewlines(): void
    {
        $input = "Line 1\n\n\n\nLine 2\n\n\n\n\nLine 3";
        $expected = "Line 1\n\nLine 2\n\nLine 3";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testTrimLeadingTrailingWhitespace(): void
    {
        $input = "\n  \t  Text content  \t  \n";
        $expected = 'Text content';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testRemoveControlCharacters(): void
    {
        $input = "Text\x00with\x08control\x1Fcharacters";
        $expected = 'Textwithcontrolcharacters';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testComplexMarkdownDocument(): void
    {
        $input = <<<'MD'
# Main Title

This is a paragraph with **bold** and *italic* text, plus a [link](https://example.com).

## Subsection

> This is a blockquote
> with multiple lines

Here's some `inline code` and a code block:

```php
echo "Hello World";
```

### List Example

1. First item
2. Second item with **bold**
   - Nested item
   - Another nested item

| Column 1 | Column 2 |
|----------|----------|
| Data 1   | Data 2   |

![An image](image.jpg)

<!-- This is a comment -->

---

Final paragraph.
MD;

        $expected = <<<'TEXT'
Main Title

This is a paragraph with bold and italic text, plus a link.

Subsection

This is a blockquote
with multiple lines

Here's some inline code and a code block:

``php
echo "Hello World";
``

List Example
First item
Second item with bold
Nested item
Another nested item

Column 1 Column 2

Data 1 Data 2

Final paragraph.
TEXT;

        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testPreservesBasicText(): void
    {
        $input = 'Simple text without any markdown formatting.';
        $expected = 'Simple text without any markdown formatting.';
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }

    public function testPreservesLineBreaks(): void
    {
        $input = "Line 1\nLine 2\nLine 3";
        $expected = "Line 1\nLine 2\nLine 3";
        $this->assertSame($expected, MarkdownCleaner::clean($input));
    }
}
