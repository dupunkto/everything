<?php
// league/html-to-markdown 5.1.1 <https://github.com/thephpleague/html-to-markdown>
// Copyright Colin O'Dell, originally created by Nick Cernis. MIT licensed.

// Vendored because I hate PHP package management.
// Sorry!

namespace League\HTMLToMarkdown;

interface ConfigurationAwareInterface
{
    public function setConfig(Configuration $config): void;
}

namespace League\HTMLToMarkdown;

/**
 * Interface for an HTML-to-Markdown converter.
 *
 * @author Colin O'Dell <colinodell@gmail.com>
 *
 * @link https://github.com/thephpleague/html-to-markdown/ Latest version on GitHub.
 *
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 */
interface HtmlConverterInterface
{
    /**
     * Convert the given $html to Markdown
     *
     * @return string The Markdown version of the html
     *
     * @throws \InvalidArgumentException
     */
    public function convert(string $html): string;
}

namespace League\HTMLToMarkdown;

interface PreConverterInterface
{
    public function preConvert(ElementInterface $element): void;
}

namespace League\HTMLToMarkdown;

interface ElementInterface
{
    public function isBlock(): bool;

    public function isText(): bool;

    public function isWhitespace(): bool;

    public function getTagName(): string;

    public function getValue(): string;

    public function hasParent(): bool;

    public function getParent(): ?ElementInterface;

    public function getNextSibling(): ?ElementInterface;

    public function getPreviousSibling(): ?ElementInterface;

    /**
     * @param string|string[] $tagNames
     */
    public function isDescendantOf($tagNames): bool;

    public function hasChildren(): bool;

    /**
     * @return ElementInterface[]
     */
    public function getChildren(): array;

    public function getNext(): ?ElementInterface;

    public function getSiblingPosition(): int;

    public function getChildrenAsString(): string;

    public function setFinalMarkdown(string $markdown): void;

    public function getListItemLevel(): int;

    public function getAttribute(string $name): string;
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\ElementInterface;

interface ConverterInterface
{
    public function convert(ElementInterface $element): string;

    /**
     * @return string[]
     */
    public function getSupportedTags(): array;
}

namespace League\HTMLToMarkdown;

/**
 * @internal
 */
final class Coerce
{
    private function __construct()
    {
    }

    /**
     * @param mixed $val
     */
    public static function toString($val): string
    {
        switch (true) {
            case \is_string($val):
                return $val;
            case \is_bool($val):
            case \is_float($val):
            case \is_int($val):
            case $val === null:
                return \strval($val);
            case \is_object($val) && \method_exists($val, '__toString'):
                return $val->__toString();
            default:
                throw new \InvalidArgumentException('Cannot coerce this value to string');
        }
    }
}

namespace League\HTMLToMarkdown;

class Configuration
{
    /** @var array<string, mixed> */
    protected $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        $this->checkForDeprecatedOptions($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function merge(array $config = []): void
    {
        $this->checkForDeprecatedOptions($config);
        $this->config = \array_replace_recursive($this->config, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function replace(array $config = []): void
    {
        $this->checkForDeprecatedOptions($config);
        $this->config = $config;
    }

    /**
     * @param mixed $value
     */
    public function setOption(string $key, $value): void
    {
        $this->checkForDeprecatedOptions([$key => $value]);
        $this->config[$key] = $value;
    }

    /**
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getOption(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }

        if (! isset($this->config[$key])) {
            return $default;
        }

        return $this->config[$key];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function checkForDeprecatedOptions(array $config): void
    {
        foreach ($config as $key => $value) {
            if ($key === 'bold_style' && $value !== '**') {
                @\trigger_error('Customizing the bold_style option is deprecated and may be removed in the next major version', E_USER_DEPRECATED);
            } elseif ($key === 'italic_style' && $value !== '*') {
                @\trigger_error('Customizing the italic_style option is deprecated and may be removed in the next major version', E_USER_DEPRECATED);
            }
        }
    }
}

namespace League\HTMLToMarkdown;

class Element implements ElementInterface
{
    /** @var \DOMNode */
    protected $node;

    /** @var ElementInterface|null */
    private $nextCached;

    /** @var \DOMNode|null */
    private $previousSiblingCached;

    public function __construct(\DOMNode $node)
    {
        $this->node = $node;

        $this->previousSiblingCached = $this->node->previousSibling;
    }

    public function isBlock(): bool
    {
        switch ($this->getTagName()) {
            case 'blockquote':
            case 'body':
            case 'div':
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
            case 'hr':
            case 'html':
            case 'li':
            case 'p':
            case 'ol':
            case 'ul':
                return true;
            default:
                return false;
        }
    }

    public function isText(): bool
    {
        return $this->getTagName() === '#text';
    }

    public function isWhitespace(): bool
    {
        return $this->getTagName() === '#text' && \trim($this->getValue()) === '';
    }

    public function getTagName(): string
    {
        return $this->node->nodeName;
    }

    public function getValue(): string
    {
        return $this->node->nodeValue ?? '';
    }

    public function hasParent(): bool
    {
        return $this->node->parentNode !== null;
    }

    public function getParent(): ?ElementInterface
    {
        return $this->node->parentNode ? new self($this->node->parentNode) : null;
    }

    public function getNextSibling(): ?ElementInterface
    {
        return $this->node->nextSibling !== null ? new self($this->node->nextSibling) : null;
    }

    public function getPreviousSibling(): ?ElementInterface
    {
        return $this->previousSiblingCached !== null ? new self($this->previousSiblingCached) : null;
    }

    public function hasChildren(): bool
    {
        return $this->node->hasChildNodes();
    }

    /**
     * @return ElementInterface[]
     */
    public function getChildren(): array
    {
        $ret = [];
        foreach ($this->node->childNodes as $node) {
            /** @psalm-suppress RedundantCondition */
            \assert($node instanceof \DOMNode);
            $ret[] = new self($node);
        }

        return $ret;
    }

    public function getNext(): ?ElementInterface
    {
        if ($this->nextCached === null) {
            $nextNode = $this->getNextNode($this->node);
            if ($nextNode !== null) {
                $this->nextCached = new self($nextNode);
            }
        }

        return $this->nextCached;
    }

    private function getNextNode(\DOMNode $node, bool $checkChildren = true): ?\DOMNode
    {
        if ($checkChildren && $node->firstChild) {
            return $node->firstChild;
        }

        if ($node->nextSibling) {
            return $node->nextSibling;
        }

        if ($node->parentNode) {
            return $this->getNextNode($node->parentNode, false);
        }

        return null;
    }

    /**
     * @param string[]|string $tagNames
     */
    public function isDescendantOf($tagNames): bool
    {
        if (! \is_array($tagNames)) {
            $tagNames = [$tagNames];
        }

        for ($p = $this->node->parentNode; $p !== null; $p = $p->parentNode) {
            if (\in_array($p->nodeName, $tagNames, true)) {
                return true;
            }
        }

        return false;
    }

    public function setFinalMarkdown(string $markdown): void
    {
        if ($this->node->ownerDocument === null) {
            throw new \RuntimeException('Unowned node');
        }

        if ($this->node->parentNode === null) {
            throw new \RuntimeException('Cannot setFinalMarkdown() on a node without a parent');
        }

        $markdownNode = $this->node->ownerDocument->createTextNode($markdown);
        $this->node->parentNode->replaceChild($markdownNode, $this->node);
    }

    public function getChildrenAsString(): string
    {
        return $this->node->C14N();
    }

    public function getSiblingPosition(): int
    {
        $position = 0;

        $parent = $this->getParent();
        if ($parent === null) {
            return $position;
        }

        // Loop through all nodes and find the given $node
        foreach ($parent->getChildren() as $currentNode) {
            if (! $currentNode->isWhitespace()) {
                $position++;
            }

            // TODO: Need a less-buggy way of comparing these
            // Perhaps we can somehow ensure that we always have the exact same object and use === instead?
            if ($this->equals($currentNode)) {
                break;
            }
        }

        return $position;
    }

    public function getListItemLevel(): int
    {
        $level  = 0;
        $parent = $this->getParent();

        while ($parent !== null && $parent->hasParent()) {
            if ($parent->getTagName() === 'li') {
                $level++;
            }

            $parent = $parent->getParent();
        }

        return $level;
    }

    public function getAttribute(string $name): string
    {
        if ($this->node instanceof \DOMElement) {
            return $this->node->getAttribute($name);
        }

        return '';
    }

    public function equals(ElementInterface $element): bool
    {
        if ($element instanceof self) {
            return $element->node === $this->node;
        }

        return false;
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\ElementInterface;

class BlockquoteConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        // Contents should have already been converted to Markdown by this point,
        // so we just need to add '>' symbols to each line.

        $markdown = '';

        $quoteContent = \trim($element->getValue());

        $lines = \preg_split('/\r\n|\r|\n/', $quoteContent);
        \assert(\is_array($lines));

        $totalLines = \count($lines);

        foreach ($lines as $i => $line) {
            $markdown .= '> ' . $line . "\n";
            if ($i + 1 === $totalLines) {
                $markdown .= "\n";
            }
        }

        return $markdown;
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['blockquote'];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\ElementInterface;

class CodeConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        $language = '';

        // Checking for language class on the code block
        $classes = $element->getAttribute('class');

        if ($classes) {
            // Since tags can have more than one class, we need to find the one that starts with 'language-'
            $classes = \explode(' ', $classes);
            foreach ($classes as $class) {
                if (\strpos($class, 'language-') !== false) {
                    // Found one, save it as the selected language and stop looping over the classes.
                    $language = \str_replace('language-', '', $class);
                    break;
                }
            }
        }

        $markdown = '';
        $code     = \html_entity_decode($element->getChildrenAsString());

        // In order to remove the code tags we need to search for them and, in the case of the opening tag
        // use a regular expression to find the tag and the other attributes it might have
        $code = \preg_replace('/<code\b[^>]*>/', '', $code);
        \assert($code !== null);
        $code = \str_replace('</code>', '', $code);

        // Checking if it's a code block or span
        if ($this->shouldBeBlock($element, $code)) {
            // Code block detected, newlines will be added in parent
            $markdown .= '```' . $language . "\n" . $code . "\n" . '```';
        } else {
            // One line of code, wrapping it on one backtick, removing new lines
            $markdown .= '`' . \preg_replace('/\r\n|\r|\n/', '', $code) . '`';
        }

        return $markdown;
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['code'];
    }

    private function shouldBeBlock(ElementInterface $element, string $code): bool
    {
        $parent = $element->getParent();
        if ($parent !== null && $parent->getTagName() === 'pre') {
            return true;
        }

        return \preg_match('/[^\s]` `/', $code) === 1;
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\ElementInterface;

class CommentConverter implements ConverterInterface, ConfigurationAwareInterface
{
    /** @var Configuration */
    protected $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        if ($this->shouldPreserve($element)) {
            return '<!--' . $element->getValue() . '-->';
        }

        return '';
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['#comment'];
    }

    private function shouldPreserve(ElementInterface $element): bool
    {
        $preserve = $this->config->getOption('preserve_comments');
        if ($preserve === true) {
            return true;
        }

        if (\is_array($preserve)) {
            $value = \trim($element->getValue());

            return \in_array($value, $preserve, true);
        }

        return false;
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\ElementInterface;

class DefaultConverter implements ConverterInterface, ConfigurationAwareInterface
{
    public const DEFAULT_CONVERTER = '_default';

    /** @var Configuration */
    protected $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        // If strip_tags is false (the default), preserve tags that don't have Markdown equivalents,
        // such as <span> nodes on their own. C14N() canonicalizes the node to a string.
        // See: http://www.php.net/manual/en/domnode.c14n.php
        if ($this->config->getOption('strip_tags', false)) {
            return $element->getValue();
        }

        $markdown = \html_entity_decode($element->getChildrenAsString());

        // Tables are only handled here if TableConverter is not used
        if ($element->getTagName() === 'table') {
            $markdown .= "\n\n";
        }

        return $markdown;
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return [self::DEFAULT_CONVERTER];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\ElementInterface;

class DivConverter implements ConverterInterface, ConfigurationAwareInterface
{
    /** @var Configuration */
    protected $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        if ($this->config->getOption('strip_tags', false)) {
            return $element->getValue() . "\n\n";
        }

        return \html_entity_decode($element->getChildrenAsString());
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['div'];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\ElementInterface;

class EmphasisConverter implements ConverterInterface, ConfigurationAwareInterface
{
    /** @var Configuration */
    protected $config;

    protected function getNormTag(?ElementInterface $element): string
    {
        if ($element !== null && ! $element->isText()) {
            $tag = $element->getTagName();
            if ($tag === 'i' || $tag === 'em') {
                return 'em';
            }

            if ($tag === 'b' || $tag === 'strong') {
                return 'strong';
            }
        }

        return '';
    }

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        $tag   = $this->getNormTag($element);
        $value = $element->getValue();

        if (! \trim($value)) {
            return $value;
        }

        if ($tag === 'em') {
            $style = $this->config->getOption('italic_style');
        } else {
            $style = $this->config->getOption('bold_style');
        }

        $prefix = \ltrim($value) !== $value ? ' ' : '';
        $suffix = \rtrim($value) !== $value ? ' ' : '';

        /* If this node is immediately preceded or followed by one of the same type don't emit
         * the start or end $style, respectively. This prevents <em>foo</em><em>bar</em> from
         * being converted to *foo**bar* which is incorrect. We want *foobar* instead.
         */
        $preStyle  = $this->getNormTag($element->getPreviousSibling()) === $tag ? '' : $style;
        $postStyle = $this->getNormTag($element->getNextSibling()) === $tag ? '' : $style;

        return $prefix . $preStyle . \trim($value) . $postStyle . $suffix;
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['em', 'i', 'strong', 'b'];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\ElementInterface;

class HardBreakConverter implements ConverterInterface, ConfigurationAwareInterface
{
    /** @var Configuration */
    protected $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        $return = $this->config->getOption('hard_break') ? "\n" : "  \n";

        $next = $element->getNext();
        if ($next) {
            $nextValue = $next->getValue();
            if ($nextValue) {
                if (\in_array(\substr($nextValue, 0, 2), ['- ', '* ', '+ '], true)) {
                    $parent = $element->getParent();
                    if ($parent && $parent->getTagName() === 'li') {
                        $return .= '\\';
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['br'];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\ElementInterface;

class HeaderConverter implements ConverterInterface, ConfigurationAwareInterface
{
    public const STYLE_ATX    = 'atx';
    public const STYLE_SETEXT = 'setext';

    /** @var Configuration */
    protected $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        $level = (int) \substr($element->getTagName(), 1, 1);
        $style = $this->config->getOption('header_style', self::STYLE_SETEXT);

        if (\strlen($element->getValue()) === 0) {
            return "\n";
        }

        if (($level === 1 || $level === 2) && ! $element->isDescendantOf('blockquote') && $style === self::STYLE_SETEXT) {
            return $this->createSetextHeader($level, $element->getValue());
        }

        return $this->createAtxHeader($level, $element->getValue());
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    }

    private function createSetextHeader(int $level, string $content): string
    {
        $length    = \function_exists('mb_strlen') ? \mb_strlen($content, 'utf-8') : \strlen($content);
        $underline = $level === 1 ? '=' : '-';

        return $content . "\n" . \str_repeat($underline, $length) . "\n\n";
    }

    private function createAtxHeader(int $level, string $content): string
    {
        $prefix = \str_repeat('#', $level) . ' ';

        return $prefix . $content . "\n\n";
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\ElementInterface;

class HorizontalRuleConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        return "---\n\n";
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['hr'];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\ElementInterface;

class ImageConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        $src   = $element->getAttribute('src');
        $alt   = $element->getAttribute('alt');
        $title = $element->getAttribute('title');

        if ($title !== '') {
            // No newlines added. <img> should be in a block-level element.
            return '![' . $alt . '](' . $src . ' "' . $title . '")';
        }

        return '![' . $alt . '](' . $src . ')';
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['img'];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\ElementInterface;

class LinkConverter implements ConverterInterface, ConfigurationAwareInterface
{
    /** @var Configuration */
    protected $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        $href  = $element->getAttribute('href');
        $title = $element->getAttribute('title');
        $text  = \trim($element->getValue(), "\t\n\r\0\x0B");

        if ($title !== '') {
            $markdown = '[' . $text . '](' . $href . ' "' . $title . '")';
        } elseif ($href === $text && $this->isValidAutolink($href)) {
            $markdown = '<' . $href . '>';
        } elseif ($href === 'mailto:' . $text && $this->isValidEmail($text)) {
            $markdown = '<' . $text . '>';
        } else {
            if (\stristr($href, ' ')) {
                $href = '<' . $href . '>';
            }

            $markdown = '[' . $text . '](' . $href . ')';
        }

        if (! $href) {
            if ($this->shouldStrip()) {
                $markdown = $text;
            } else {
                $markdown = \html_entity_decode($element->getChildrenAsString());
            }
        }

        return $markdown;
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['a'];
    }

    private function isValidAutolink(string $href): bool
    {
        $useAutolinks = $this->config->getOption('use_autolinks');

        return $useAutolinks && (\preg_match('/^[A-Za-z][A-Za-z0-9.+-]{1,31}:[^<>\x00-\x20]*/i', $href) === 1);
    }

    private function isValidEmail(string $email): bool
    {
        // Email validation is messy business, but this should cover most cases
        return \filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function shouldStrip(): bool
    {
        return \boolval($this->config->getOption('strip_placeholder_links') ?? false);
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\ElementInterface;

class ListBlockConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        return $element->getValue() . "\n";
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['ol', 'ul'];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\Coerce;
use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\ElementInterface;

class ListItemConverter implements ConverterInterface, ConfigurationAwareInterface
{
    /** @var Configuration */
    protected $config;

    /** @var string|null */
    protected $listItemStyle;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        // If parent is an ol, use numbers, otherwise, use dashes
        $listType = ($parent = $element->getParent()) ? $parent->getTagName() : 'ul';

        // Add spaces to start for nested list items
        $level = $element->getListItemLevel();

        $value = \trim(\implode("\n" . '    ', \explode("\n", \trim($element->getValue()))));

        // If list item is the first in a nested list, add a newline before it
        $prefix = '';
        if ($level > 0 && $element->getSiblingPosition() === 1) {
            $prefix = "\n";
        }

        if ($listType === 'ul') {
            $listItemStyle          = Coerce::toString($this->config->getOption('list_item_style', '-'));
            $listItemStyleAlternate = Coerce::toString($this->config->getOption('list_item_style_alternate', ''));
            if (! isset($this->listItemStyle)) {
                $this->listItemStyle = $listItemStyleAlternate ?: $listItemStyle;
            }

            if ($listItemStyleAlternate && $level === 0 && $element->getSiblingPosition() === 1) {
                $this->listItemStyle = $this->listItemStyle === $listItemStyle ? $listItemStyleAlternate : $listItemStyle;
            }

            return $prefix . $this->listItemStyle . ' ' . $value . "\n";
        }

        if ($listType === 'ol' && ($parent = $element->getParent()) && ($start = \intval($parent->getAttribute('start')))) {
            $number = $start + $element->getSiblingPosition() - 1;
        } else {
            $number = $element->getSiblingPosition();
        }

        return $prefix . $number . '. ' . $value . "\n";
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['li'];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\ElementInterface;

class ParagraphConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        $value = $element->getValue();

        $markdown = '';

        $lines = \preg_split('/\r\n|\r|\n/', $value);
        \assert($lines !== false);

        foreach ($lines as $line) {
            /*
             * Some special characters need to be escaped based on the position that they appear
             * The following function will deal with those special cases.
             */
            $markdown .= $this->escapeSpecialCharacters($line);
            $markdown .= "\n";
        }

        return \trim($markdown) !== '' ? \rtrim($markdown) . "\n\n" : '';
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['p'];
    }

    private function escapeSpecialCharacters(string $line): string
    {
        $line = $this->escapeFirstCharacters($line);
        $line = $this->escapeOtherCharacters($line);
        $line = $this->escapeOtherCharactersRegex($line);

        return $line;
    }

    private function escapeFirstCharacters(string $line): string
    {
        $escapable = [
            '>',
            '- ',
            '+ ',
            '--',
            '~~~',
            '---',
            '- - -',
        ];

        foreach ($escapable as $i) {
            if (\strpos(\ltrim($line), $i) === 0) {
                // Found a character that must be escaped, adding a backslash before
                return '\\' . \ltrim($line);
            }
        }

        return $line;
    }

    private function escapeOtherCharacters(string $line): string
    {
        $escapable = [
            '<!--',
        ];

        foreach ($escapable as $i) {
            if (($pos = \strpos($line, $i)) === false) {
                continue;
            }

            // Found an escapable character, escaping it
            $line = \substr_replace($line, '\\', $pos, 0);
        }

        return $line;
    }

    private function escapeOtherCharactersRegex(string $line): string
    {
        $regExs = [
            // Match numbers ending on ')' or '.' that are at the beginning of the line.
            // They will be escaped if immediately followed by a space or newline.
            '/^[0-9]+(?=(\)|\.)( |$))/',
        ];

        foreach ($regExs as $i) {
            if (! \preg_match($i, $line, $match)) {
                continue;
            }

            // Matched an escapable character, adding a backslash on the string before the offending character
            $line = \substr_replace($line, '\\', \strlen($match[0]), 0);
        }

        return $line;
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\ElementInterface;

class PreformattedConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        $preContent = \html_entity_decode($element->getChildrenAsString());
        $preContent = \preg_replace('/<pre\b[^>]*>/', '', $preContent);
        \assert($preContent !== null);
        $preContent = \str_replace('</pre>', '', $preContent);

        /*
         * Checking for the code tag.
         * Usually pre tags are used along with code tags. This conditional will check for already converted code tags,
         * which use backticks, and if those backticks are at the beginning and at the end of the string it means
         * there's no more information to convert.
         */

        $firstBacktick = \strpos(\trim($preContent), '`');
        $lastBacktick  = \strrpos(\trim($preContent), '`');
        if ($firstBacktick === 0 && $lastBacktick === \strlen(\trim($preContent)) - 1) {
            return $preContent . "\n\n";
        }

        // If the execution reaches this point it means it's just a pre tag, with no code tag nested

        // Empty lines are a special case
        if ($preContent === '') {
            return "```\n```\n\n";
        }

        // Normalizing new lines
        $preContent = \preg_replace('/\r\n|\r|\n/', "\n", $preContent);
        \assert(\is_string($preContent));

        // Ensure there's a newline at the end
        if (\strrpos($preContent, "\n") !== \strlen($preContent) - \strlen("\n")) {
            $preContent .= "\n";
        }

        // Use three backticks
        return "```\n" . $preContent . "```\n\n";
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['pre'];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\Coerce;
use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\ElementInterface;
use League\HTMLToMarkdown\PreConverterInterface;

class TableConverter implements ConverterInterface, PreConverterInterface, ConfigurationAwareInterface
{
    /** @var Configuration */
    protected $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    /** @var array<string, string> */
    private static $alignments = [
        'left' => ':--',
        'right' => '--:',
        'center' => ':-:',
    ];

    /** @var array<int, string>|null */
    private $columnAlignments = [];

    /** @var string|null */
    private $caption = null;

    public function preConvert(ElementInterface $element): void
    {
        $tag = $element->getTagName();
        // Only table cells and caption are allowed to contain content.
        // Remove all text between other table elements.
        if ($tag === 'th' || $tag === 'td' || $tag === 'caption') {
            return;
        }

        foreach ($element->getChildren() as $child) {
            if ($child->isText()) {
                $child->setFinalMarkdown('');
            }
        }
    }

    public function convert(ElementInterface $element): string
    {
        $value = $element->getValue();

        switch ($element->getTagName()) {
            case 'table':
                $this->columnAlignments = [];
                if ($this->caption) {
                    $side = $this->config->getOption('table_caption_side');
                    if ($side === 'top') {
                        $value = $this->caption . "\n" . $value;
                    } elseif ($side === 'bottom') {
                        $value .= $this->caption;
                    }

                    $this->caption = null;
                }

                return $value . "\n";
            case 'caption':
                $this->caption = \trim($value);

                return '';
            case 'tr':
                $value .= "|\n";
                if ($this->columnAlignments !== null) {
                    $value .= '|' . \implode('|', $this->columnAlignments) . "|\n";

                    $this->columnAlignments = null;
                }

                return $value;
            case 'th':
            case 'td':
                if ($this->columnAlignments !== null) {
                    $align = $element->getAttribute('align');

                    $this->columnAlignments[] = self::$alignments[$align] ?? '---';
                }

                $value = \str_replace("\n", ' ', $value);
                $value = \str_replace('|', Coerce::toString($this->config->getOption('table_pipe_escape') ?? '\|'), $value);

                return '| ' . \trim($value) . ' ';
            case 'thead':
            case 'tbody':
            case 'tfoot':
            case 'colgroup':
            case 'col':
                return $value;
            default:
                return '';
        }
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['table', 'tr', 'th', 'td', 'thead', 'tbody', 'tfoot', 'colgroup', 'col', 'caption'];
    }
}

namespace League\HTMLToMarkdown\Converter;

use League\HTMLToMarkdown\ElementInterface;

class TextConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        $markdown = $element->getValue();

        // Remove leftover \n at the beginning of the line
        $markdown = \ltrim($markdown, "\n");

        // Replace sequences of invisible characters with spaces
        $markdown = \preg_replace('~\s+~u', ' ', $markdown);
        \assert(\is_string($markdown));

        // Escape the following characters: '*', '_', '[', ']' and '\'
        if (($parent = $element->getParent()) && $parent->getTagName() !== 'div') {
            $markdown = \preg_replace('~([*_\\[\\]\\\\])~u', '\\\\$1', $markdown);
            \assert(\is_string($markdown));
        }

        $markdown = \preg_replace('~^#~u', '\\\\#', $markdown);
        \assert(\is_string($markdown));

        if ($markdown === ' ') {
            $next = $element->getNext();
            if (! $next || $next->isBlock()) {
                $markdown = '';
            }
        }

        return \htmlspecialchars($markdown, ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['#text'];
    }
}

namespace League\HTMLToMarkdown;

use League\HTMLToMarkdown\Converter\BlockquoteConverter;
use League\HTMLToMarkdown\Converter\CodeConverter;
use League\HTMLToMarkdown\Converter\CommentConverter;
use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\Converter\DefaultConverter;
use League\HTMLToMarkdown\Converter\DivConverter;
use League\HTMLToMarkdown\Converter\EmphasisConverter;
use League\HTMLToMarkdown\Converter\HardBreakConverter;
use League\HTMLToMarkdown\Converter\HeaderConverter;
use League\HTMLToMarkdown\Converter\HorizontalRuleConverter;
use League\HTMLToMarkdown\Converter\ImageConverter;
use League\HTMLToMarkdown\Converter\LinkConverter;
use League\HTMLToMarkdown\Converter\ListBlockConverter;
use League\HTMLToMarkdown\Converter\ListItemConverter;
use League\HTMLToMarkdown\Converter\ParagraphConverter;
use League\HTMLToMarkdown\Converter\PreformattedConverter;
use League\HTMLToMarkdown\Converter\TextConverter;

final class Environment
{
    /** @var Configuration */
    protected $config;

    /** @var ConverterInterface[] */
    protected $converters = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = new Configuration($config);
        $this->addConverter(new DefaultConverter());
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    public function addConverter(ConverterInterface $converter): void
    {
        if ($converter instanceof ConfigurationAwareInterface) {
            $converter->setConfig($this->config);
        }

        foreach ($converter->getSupportedTags() as $tag) {
            $this->converters[$tag] = $converter;
        }
    }

    public function getConverterByTag(string $tag): ConverterInterface
    {
        if (isset($this->converters[$tag])) {
            return $this->converters[$tag];
        }

        return $this->converters[DefaultConverter::DEFAULT_CONVERTER];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function createDefaultEnvironment(array $config = []): Environment
    {
        $environment = new static($config);

        $environment->addConverter(new BlockquoteConverter());
        $environment->addConverter(new CodeConverter());
        $environment->addConverter(new CommentConverter());
        $environment->addConverter(new DivConverter());
        $environment->addConverter(new EmphasisConverter());
        $environment->addConverter(new HardBreakConverter());
        $environment->addConverter(new HeaderConverter());
        $environment->addConverter(new HorizontalRuleConverter());
        $environment->addConverter(new ImageConverter());
        $environment->addConverter(new LinkConverter());
        $environment->addConverter(new ListBlockConverter());
        $environment->addConverter(new ListItemConverter());
        $environment->addConverter(new ParagraphConverter());
        $environment->addConverter(new PreformattedConverter());
        $environment->addConverter(new TextConverter());

        return $environment;
    }
}

namespace League\HTMLToMarkdown;

/**
 * A helper class to convert HTML to Markdown.
 *
 * @author Colin O'Dell <colinodell@gmail.com>
 * @author Nick Cernis <nick@cern.is>
 *
 * @link https://github.com/thephpleague/html-to-markdown/ Latest version on GitHub.
 *
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 */
class HtmlConverter implements HtmlConverterInterface
{
    /** @var Environment */
    protected $environment;

    /**
     * Constructor
     *
     * @param Environment|array<string, mixed> $options Environment object or configuration options
     */
    public function __construct($options = [])
    {
        if ($options instanceof Environment) {
            $this->environment = $options;
        } elseif (\is_array($options)) {
            $defaults = [
                'header_style' => 'setext', // Set to 'atx' to output H1 and H2 headers as # Header1 and ## Header2
                'suppress_errors' => true, // Set to false to show warnings when loading malformed HTML
                'strip_tags' => false, // Set to true to strip tags that don't have markdown equivalents. N.B. Strips tags, not their content. Useful to clean MS Word HTML output.
                'strip_placeholder_links' => false, // Set to true to remove <a> that doesn't have href.
                'bold_style' => '**', // DEPRECATED: Set to '__' if you prefer the underlined style
                'italic_style' => '*', // DEPRECATED: Set to '_' if you prefer the underlined style
                'remove_nodes' => '', // space-separated list of dom nodes that should be removed. example: 'meta style script'
                'hard_break' => false, // Set to true to turn <br> into `\n` instead of `  \n`
                'list_item_style' => '-', // Set the default character for each <li> in a <ul>. Can be '-', '*', or '+'
                'preserve_comments' => false, // Set to true to preserve comments, or set to an array of strings to preserve specific comments
                'use_autolinks' => true, // Set to true to use simple link syntax if possible. Will always use []() if set to false
                'table_pipe_escape' => '\|', // Replacement string for pipe characters inside markdown table cells
                'table_caption_side' => 'top', // Set to 'top' or 'bottom' to show <caption> content before or after table, null to suppress
            ];

            $this->environment = Environment::createDefaultEnvironment($defaults);

            $this->environment->getConfig()->merge($options);
        }
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function getConfig(): Configuration
    {
        return $this->environment->getConfig();
    }

    /**
     * Convert
     *
     * @see HtmlConverter::convert
     *
     * @return string The Markdown version of the html
     */
    public function __invoke(string $html): string
    {
        return $this->convert($html);
    }

    /**
     * Convert
     *
     * Loads HTML and passes to getMarkdown()
     *
     * @return string The Markdown version of the html
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function convert(string $html): string
    {
        if (\trim($html) === '') {
            return '';
        }

        $document = $this->createDOMDocument($html);

        // Work on the entire DOM tree (including head and body)
        if (! ($root = $document->getElementsByTagName('html')->item(0))) {
            throw new \InvalidArgumentException('Invalid HTML was provided');
        }

        $rootElement = new Element($root);
        $this->convertChildren($rootElement);

        // Store the now-modified DOMDocument as a string
        $markdown = $document->saveHTML();

        if ($markdown === false) {
            throw new \RuntimeException('Unknown error occurred during HTML to Markdown conversion');
        }

        return $this->sanitize($markdown);
    }

    private function createDOMDocument(string $html): \DOMDocument
    {
        $document = new \DOMDocument();

        if ($this->getConfig()->getOption('suppress_errors')) {
            // Suppress conversion errors (from http://bit.ly/pCCRSX)
            \libxml_use_internal_errors(true);
        }

        // Hack to load utf-8 HTML (from http://bit.ly/pVDyCt)
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $document->encoding = 'UTF-8';

        $this->replaceMisplacedComments($document);

        if ($this->getConfig()->getOption('suppress_errors')) {
            \libxml_clear_errors();
        }

        return $document;
    }

    /**
     * Finds any comment nodes outside <html> element and moves them into <body>.
     *
     * @see https://github.com/thephpleague/html-to-markdown/issues/212
     * @see https://3v4l.org/7bC33
     */
    private function replaceMisplacedComments(\DOMDocument $document): void
    {
        // Find ny comment nodes at the root of the document.
        $misplacedComments = (new \DOMXPath($document))->query('/comment()');
        if ($misplacedComments === false) {
            return;
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return;
        }

        // Loop over comment nodes in reverse so we put them inside <body> in
        // their original order.
        for ($index = $misplacedComments->length - 1; $index >= 0; $index--) {
            if ($body->firstChild === null) {
                $body->insertBefore($misplacedComments[$index]);
            } else {
                $body->insertBefore($misplacedComments[$index], $body->firstChild);
            }
        }
    }

    /**
     * Convert Children
     *
     * Recursive function to drill into the DOM and convert each node into Markdown from the inside out.
     *
     * Finds children of each node and convert those to #text nodes containing their Markdown equivalent,
     * starting with the innermost element and working up to the outermost element.
     */
    private function convertChildren(ElementInterface $element): void
    {
        // Don't convert HTML code inside <code> and <pre> blocks to Markdown - that should stay as HTML
        // except if the current node is a code tag, which needs to be converted by the CodeConverter.
        if ($element->isDescendantOf(['pre', 'code']) && $element->getTagName() !== 'code') {
            return;
        }

        // Give converter a chance to inspect/modify the DOM before children are converted
        $converter = $this->environment->getConverterByTag($element->getTagName());
        if ($converter instanceof PreConverterInterface) {
            $converter->preConvert($element);
        }

        // If the node has children, convert those to Markdown first
        if ($element->hasChildren()) {
            foreach ($element->getChildren() as $child) {
                $this->convertChildren($child);
            }
        }

        // Now that child nodes have been converted, convert the original node
        $markdown = $this->convertToMarkdown($element);

        // Create a DOM text node containing the Markdown equivalent of the original node

        // Replace the old $node e.g. '<h3>Title</h3>' with the new $markdown_node e.g. '### Title'
        $element->setFinalMarkdown($markdown);
    }

    /**
     * Convert to Markdown
     *
     * Converts an individual node into a #text node containing a string of its Markdown equivalent.
     *
     * Example: An <h3> node with text content of 'Title' becomes a text node with content of '### Title'
     *
     * @return string The converted HTML as Markdown
     */
    protected function convertToMarkdown(ElementInterface $element): string
    {
        $tag = $element->getTagName();

        // Strip nodes named in remove_nodes
        $tagsToRemove = \explode(' ', Coerce::toString($this->getConfig()->getOption('remove_nodes') ?? ''));
        if (\in_array($tag, $tagsToRemove, true)) {
            return '';
        }

        $converter = $this->environment->getConverterByTag($tag);

        return $converter->convert($element);
    }

    protected function sanitize(string $markdown): string
    {
        $markdown = \html_entity_decode($markdown, ENT_QUOTES, 'UTF-8');
        $markdown = \preg_replace('/<!DOCTYPE [^>]+>/', '', $markdown); // Strip doctype declaration
        \assert($markdown !== null);
        $markdown = \trim($markdown); // Remove blank spaces at the beggining of the html

        /*
         * Removing unwanted tags. Tags should be added to the array in the order they are expected.
         * XML, html and body opening tags should be in that order. Same case with closing tags
         */
        $unwanted = ['<?xml encoding="UTF-8">', '<html>', '</html>', '<body>', '</body>', '<head>', '</head>', '&#xD;'];

        foreach ($unwanted as $tag) {
            if (\strpos($tag, '/') === false) {
                // Opening tags
                if (\strpos($markdown, $tag) === 0) {
                    $markdown = \substr($markdown, \strlen($tag));
                }
            } else {
                // Closing tags
                if (\strpos($markdown, $tag) === \strlen($markdown) - \strlen($tag)) {
                    $markdown = \substr($markdown, 0, -\strlen($tag));
                }
            }
        }

        return \trim($markdown, "\n\r\0\x0B");
    }

    /**
     * Pass a series of key-value pairs in an array; these will be passed
     * through the config and set.
     * The advantage of this is that it can allow for static use (IE in Laravel).
     * An example being:
     *
     * HtmlConverter::setOptions(['strip_tags' => true])->convert('<h1>test</h1>');
     *
     * @param array<string, mixed> $options
     *
     * @return $this
     */
    public function setOptions(array $options)
    {
        $config = $this->getConfig();

        foreach ($options as $key => $option) {
            $config->setOption($key, $option);
        }

        return $this;
    }
}
