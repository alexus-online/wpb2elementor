<?php

class WPB2EL_Converter {

    private WPB2EL_Mapper $mapper;
    private ?object $claude     = null;
    private ?object $exporter   = null;
    private string  $page_title = '';

    public function __construct( WPB2EL_Mapper $mapper ) {
        $this->mapper = $mapper;
    }

    public function convert( array $nodes, $claude = null, $exporter = null, string $page_title = '' ): array {
        $this->claude     = $claude;
        $this->exporter   = $exporter;
        $this->page_title = $page_title;
        $elements = [];
        foreach ( $nodes as $node ) {
            $el = $this->convert_node( $node );
            if ( $el ) $elements[] = $el;
        }
        return $elements;
    }

    private function convert_node( array $node ): ?array {
        if ( $node['tag'] === '__text__' ) return null;

        $mapped = $this->mapper->map( $node['tag'] );
        $id     = $this->generate_id();

        // Unknown widget: try Claude API, then fallback to placeholder
        if ( ! $mapped['known'] ) {
            if ( $this->claude ) {
                $claude_el = $this->claude->convert_node( $node );
                if ( $claude_el ) {
                    $claude_el['id'] = $this->generate_id();
                    return $claude_el;
                }
            }
            if ( $this->exporter ) {
                $this->exporter->add( $this->page_title, $node );
            }
            return [
                'id'         => $id,
                'elType'     => 'widget',
                'widgetType' => 'html',
                'settings'   => [ 'html' => '<!-- WPB2EL unknown: ' . esc_html( $node['tag'] ) . ' -->' . $this->rebuild_shortcode( $node ) ],
                'elements'   => [],
            ];
        }

        $el = [
            'id'       => $id,
            'elType'   => $mapped['elType'],
            'settings' => $this->build_settings( $node, $mapped ),
            'elements' => [],
        ];

        if ( $mapped['elType'] === 'widget' ) {
            $el['widgetType'] = $mapped['widgetType'];
        }

        foreach ( $node['children'] as $child ) {
            $child_el = $this->convert_node( $child );
            if ( $child_el ) $el['elements'][] = $child_el;
        }

        if ( empty( $el['elements'] ) && $node['content'] !== '' ) {
            $el['settings'] = array_merge( $el['settings'], $this->build_content_settings( $node, $mapped ) );
        }

        return $el;
    }

    private function build_settings( array $node, array $mapped ): array {
        $settings = [];
        $attrs    = $node['attrs'] ?? [];

        if ( $mapped['elType'] === 'column' && isset( $attrs['width'] ) ) {
            $settings['_column_size'] = $this->vc_width_to_percent( $attrs['width'] );
        }
        if ( isset( $attrs['css'] ) && preg_match( '/background(?:-color)?:\s*(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))/', $attrs['css'], $m ) ) {
            $settings['background_color'] = $m[1];
        }
        if ( ( $mapped['widgetType'] ?? '' ) === 'heading' && isset( $attrs['text'] ) ) {
            $settings['title'] = $attrs['text'];
        }
        if ( isset( $attrs['align'] ) ) {
            $settings['align'] = $attrs['align'];
        }

        return $settings;
    }

    private function build_content_settings( array $node, array $mapped ): array {
        $type = $mapped['widgetType'] ?? 'html';
        if ( $type === 'text-editor' ) return [ 'editor' => $node['content'] ];
        if ( $type === 'heading' )     return [ 'title'  => $node['content'] ];
        if ( $type === 'button' )      return [ 'text'   => $node['content'] ];
        return [ 'html' => $node['content'] ];
    }

    private function rebuild_shortcode( array $node ): string {
        $attrs = '';
        foreach ( $node['attrs'] as $k => $v ) {
            $escaped = function_exists('esc_attr') ? esc_attr($v) : htmlspecialchars($v, ENT_QUOTES);
            $attrs .= " {$k}=\"{$escaped}\"";
        }
        $content = function_exists('esc_html') ? esc_html($node['content']) : htmlspecialchars($node['content'], ENT_QUOTES);
        return "[{$node['tag']}{$attrs}]{$content}[/{$node['tag']}]";
    }

    private function vc_width_to_percent( string $vc_width ): int {
        $map = [ '1/1'=>100,'1/2'=>50,'1/3'=>33,'2/3'=>67,'1/4'=>25,'3/4'=>75,'1/6'=>17,'5/6'=>83 ];
        return $map[$vc_width] ?? 100;
    }

    private function generate_id(): string {
        return substr( md5( uniqid( '', true ) ), 0, 8 );
    }
}
