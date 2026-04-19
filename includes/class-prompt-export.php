<?php

class WPB2EL_Prompt_Export {

    private array $items = [];

    public function add( string $page_title, array $node ): void {
        $attrs = '';
        foreach ( $node['attrs'] as $k => $v ) {
            $attrs .= " {$k}=\"{$v}\"";
        }
        $shortcode = "[{$node['tag']}{$attrs}]{$node['content']}[/{$node['tag']}]";

        $this->items[] = [
            'page'      => $page_title,
            'tag'       => $node['tag'],
            'shortcode' => $shortcode,
            'prompt'    => "Convert this WPBakery shortcode to an Elementor widget JSON object:\n\n```\n{$shortcode}\n```\n\nReturn JSON with elType, widgetType, settings, elements.",
        ];
    }

    public function has_items(): bool {
        return ! empty( $this->items );
    }

    public function write_file(): string {
        $path    = WP_CONTENT_DIR . '/wpb2elementor-prompts.txt';
        $content = "WPB2Elementor — Unknown Widgets\n";
        $content .= "Generated: " . date( 'Y-m-d H:i:s' ) . "\n";
        $content .= str_repeat( '=', 60 ) . "\n\n";

        foreach ( $this->items as $i => $item ) {
            $n        = $i + 1;
            $content .= "--- #{$n}: {$item['tag']} (Page: {$item['page']}) ---\n\n";
            $content .= "Paste this into claude.ai:\n\n";
            $content .= $item['prompt'] . "\n\n";
            $content .= str_repeat( '-', 40 ) . "\n\n";
        }

        file_put_contents( $path, $content );
        return $path;
    }

    public function get_items(): array {
        return $this->items;
    }
}
