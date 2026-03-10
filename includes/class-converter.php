<?php
/**
 * Markdown to WordPress Block Converter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Converter
{

    /**
     * Instance of this class
     */
    private static $instance;

    /**
     * Get instance of this class
     */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
    }

    /**
     * Convert Markdown to WordPress Blocks
     *
     * @param string $markdown Markdown content.
     * @return string Block-style HTML.
     */
    public function markdown_to_blocks($markdown)
    {
        // This is a simplified converter.
        // In a full product, we might use a library or a more robust regex-based approach.

        $lines = explode("\n", $markdown);
        $blocks = array();
        $current_block = '';
        $in_list = false;
        $in_code = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Code blocks
            if (strpos($trimmed, '```') === 0) {
                if ($in_code) {
                    $blocks[] = '<!-- wp:code --><pre class="wp-block-code"><code>' . esc_html($current_block) . '</code></pre><!-- /wp:code -->';
                    $current_block = '';
                    $in_code = false;
                } else {
                    $in_code = true;
                }
                continue;
            }

            if ($in_code) {
                $current_block .= $line . "\n";
                continue;
            }

            // Headings
            if (preg_match('/^(#{2,6})\s+(.*)$/', $trimmed, $matches)) {
                $level = strlen($matches[1]);
                $content = $matches[2];
                $blocks[] = '<!-- wp:heading {"level":' . $level . '} --><h' . $level . '>' . esc_html($content) . '</h' . $level . '><!-- /wp:heading -->';
                continue;
            }

            // Lists (ul)
            if (preg_match('/^[-*+]\s+(.*)$/', $trimmed, $matches)) {
                if (!$in_list) {
                    $in_list = true;
                    $current_block = '<ul>';
                }
                $current_block .= '<li>' . esc_html($matches[1]) . '</li>';
                continue;
            } elseif ($in_list && empty($trimmed)) {
                $current_block .= '</ul>';
                $blocks[] = '<!-- wp:list -->' . $current_block . '<!-- /wp:list -->';
                $current_block = '';
                $in_list = false;
                continue;
            }

            // Paragraphs
            if (!empty($trimmed)) {
                $blocks[] = '<!-- wp:paragraph --><p>' . esc_html($trimmed) . '</p><!-- /wp:paragraph -->';
            }
        }

        // Close any open blocks
        if ($in_list) {
            $blocks[] = '<!-- wp:list -->' . $current_block . '</ul><!-- /wp:list -->';
        }

        return implode("\n", $blocks);
    }
}
