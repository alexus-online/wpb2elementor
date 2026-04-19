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

        if ( $mapped['elType'] === 'container' ) {
            $el['isInner'] = $mapped['isInner'] ?? false;
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

        if ( $mapped['elType'] === 'container' ) {
            $is_inner = $mapped['isInner'] ?? false;
            if ( ! $is_inner ) {
                $settings['flex_direction'] = 'row';
                $settings['content_width']  = 'full';
                $settings['flex_wrap']      = 'nowrap';
                $settings['align_items']    = 'stretch';
            } else {
                $settings['flex_direction'] = 'column';
                if ( isset( $attrs['width'] ) ) {
                    $pct = $this->vc_width_to_percent( $attrs['width'] );
                    $settings['width'] = [ 'unit' => '%', 'size' => $pct, 'sizes' => [] ];
                }
            }
            if ( isset( $attrs['css'] ) && preg_match( '/background(?:-color)?:\s*(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))/', $attrs['css'], $m ) ) {
                $settings['background_color'] = $m[1];
            }
        }

        $widget_type = $mapped['widgetType'] ?? '';

        // Heading: title from 'text' or 'title' attr
        if ( $widget_type === 'heading' ) {
            $settings['title'] = $attrs['text'] ?? $attrs['title'] ?? '';
        }

        // Image: pass attachment ID and optional link
        if ( $widget_type === 'image' && isset( $attrs['image'] ) ) {
            $settings['image'] = [ 'id' => (int) $attrs['image'], 'url' => '' ];
            if ( ! empty( $attrs['link'] ) && ( $attrs['onclick'] ?? '' ) === 'custom_link' ) {
                $settings['link_to'] = 'custom';
                $settings['link']    = [
                    'url'         => $attrs['link'],
                    'is_external' => ( $attrs['img_link_target'] ?? '' ) === '_blank',
                    'nofollow'    => false,
                ];
            }
        }

        if ( isset( $attrs['align'] ) ) {
            $settings['align'] = $attrs['align'];
        }

        // Theme button shortcode: [button btntext="..." btnlink="..."]
        if ( $node['tag'] === 'button' ) {
            if ( isset( $attrs['btntext'] ) ) $settings['text'] = $attrs['btntext'];
            if ( isset( $attrs['btnlink'] ) ) {
                $settings['link'] = [ 'url' => $attrs['btnlink'], 'is_external' => false, 'nofollow' => false ];
            }
        }

        // [button2 linkbox="url:URL-encoded|title:URL-encoded||"]
        if ( $node['tag'] === 'button2' && isset( $attrs['linkbox'] ) ) {
            $parts = explode( '|', $attrs['linkbox'] );
            $lb    = [];
            foreach ( $parts as $part ) {
                if ( strpos( $part, ':' ) !== false ) {
                    [ $k, $v ] = explode( ':', $part, 2 );
                    $lb[ $k ] = urldecode( $v );
                }
            }
            if ( ! empty( $lb['title'] ) ) $settings['text'] = $lb['title'];
            if ( ! empty( $lb['url'] ) )   $settings['link'] = [ 'url' => $lb['url'], 'is_external' => false, 'nofollow' => false ];
        }

        // [su_button url="..." background="#..."]content[/su_button]
        if ( $node['tag'] === 'su_button' ) {
            if ( isset( $attrs['url'] ) ) $settings['link'] = [ 'url' => $attrs['url'], 'is_external' => false, 'nofollow' => false ];
            if ( isset( $attrs['background'] ) ) $settings['button_background_color'] = $attrs['background'];
        }

        // [heading text="..." tag="h2" align="center"]
        if ( $node['tag'] === 'heading' ) {
            $settings['title'] = $attrs['text'] ?? '';
            if ( isset( $attrs['tag'] ) ) $settings['header_size'] = $attrs['tag'];
            if ( isset( $attrs['align'] ) ) $settings['align'] = $attrs['align'];
        }

        // [su_spacer size="40"]
        if ( $node['tag'] === 'su_spacer' && isset( $attrs['size'] ) ) {
            $settings['space'] = [ 'unit' => 'px', 'size' => (int) $attrs['size'], 'sizes' => [] ];
        }

        // [audio mp3="URL"][/audio] → HTML widget
        if ( $node['tag'] === 'audio' && isset( $attrs['mp3'] ) ) {
            $src = esc_url( $attrs['mp3'] );
            $settings['html'] = "<audio src=\"{$src}\" controls></audio>";
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
            $escaped = function_exists( 'esc_attr' ) ? esc_attr( $v ) : htmlspecialchars( $v, ENT_QUOTES );
            $attrs .= " {$k}=\"{$escaped}\"";
        }
        $content = function_exists( 'esc_html' ) ? esc_html( $node['content'] ) : htmlspecialchars( $node['content'], ENT_QUOTES );
        return "[{$node['tag']}{$attrs}]{$content}[/{$node['tag']}]";
    }

    private function vc_width_to_percent( string $vc_width ): int {
        $map = [ '1/1' => 100, '1/2' => 50, '1/3' => 33, '2/3' => 67, '1/4' => 25, '3/4' => 75, '1/6' => 17, '5/6' => 83 ];
        return $map[ $vc_width ] ?? 100;
    }

    private function generate_id(): string {
        return substr( md5( uniqid( '', true ) ), 0, 8 );
    }
}
