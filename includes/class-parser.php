<?php

class WPB2EL_Parser {

    // Tags that never have a closing [/tag] — add directly to parent
    private static array $self_closing = [
        'vc_single_image', 'vc_text_separator', 'vc_separator',
        'vc_empty_space', 'vc_btn', 'vc_video', 'vc_icon',
        'vc_progress_bar', 'vc_custom_heading',
        // Theme/plugin self-closing shortcodes
        'button', 'button2', 'heading', 'line_solid', 'su_spacer',
        // Third-party self-closing shortcodes
        'rev_slider', 'rev_slider_vc', 'testslide', 'process', 'foliof',
    ];

    public function parse( string $content ): array {
        $content = trim( $content );
        if ( empty( $content ) ) return [];
        $tokens = $this->tokenize( $content );
        return $this->build_tree( $tokens );
    }

    private function tokenize( string $content ): array {
        $pattern = '/(\[\/?\w+(?:[^\]]*)\])/';
        $parts   = preg_split( $pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
        $tokens  = [];
        foreach ( $parts as $part ) {
            $part = trim( $part );
            if ( empty( $part ) ) continue;
            if ( preg_match( '/^\[\/(\w+)\]$/', $part, $m ) ) {
                $tokens[] = [ 'type' => 'close', 'tag' => $m[1] ];
            } elseif ( preg_match( '/^\[(\w+)((?:\s+[^\]]*)?)\]$/', $part, $m ) ) {
                $tokens[] = [ 'type' => 'open', 'tag' => $m[1], 'attrs' => $this->parse_attrs( $m[2] ) ];
            } else {
                $tokens[] = [ 'type' => 'text', 'content' => $part ];
            }
        }
        return $tokens;
    }

    private function build_tree( array $tokens ): array {
        $result = [];
        $stack  = [];

        foreach ( $tokens as $token ) {
            if ( $token['type'] === 'open' ) {
                $node = [
                    'tag'      => $token['tag'],
                    'attrs'    => $token['attrs'],
                    'content'  => '',
                    'children' => [],
                ];
                if ( in_array( $token['tag'], self::$self_closing, true ) ) {
                    // Self-closing: add directly to current parent, never push to stack
                    if ( ! empty( $stack ) ) {
                        $stack[ count( $stack ) - 1 ]['children'][] = $node;
                    } else {
                        $result[] = $node;
                    }
                } else {
                    array_push( $stack, $node );
                }
            } elseif ( $token['type'] === 'close' ) {
                if ( empty( $stack ) ) continue;

                // Find the matching open tag anywhere in the stack
                $found = -1;
                for ( $i = count( $stack ) - 1; $i >= 0; $i-- ) {
                    if ( $stack[ $i ]['tag'] === $token['tag'] ) {
                        $found = $i;
                        break;
                    }
                }
                if ( $found === -1 ) continue; // No matching open tag — ignore

                // Collapse any unclosed tags above the match into their parent
                while ( count( $stack ) - 1 > $found ) {
                    $orphan = array_pop( $stack );
                    $stack[ count( $stack ) - 1 ]['children'][] = $orphan;
                }

                // Pop the matched node
                $node = array_pop( $stack );
                if ( empty( $stack ) ) {
                    $result[] = $node;
                } else {
                    $stack[ count( $stack ) - 1 ]['children'][] = $node;
                }
            } elseif ( $token['type'] === 'text' ) {
                if ( ! empty( $stack ) ) {
                    $stack[ count( $stack ) - 1 ]['content'] .= $token['content'];
                } else {
                    $result[] = [
                        'tag'      => '__text__',
                        'attrs'    => [],
                        'content'  => $token['content'],
                        'children' => [],
                    ];
                }
            }
        }

        // Flush remaining unclosed nodes bottom-up
        while ( ! empty( $stack ) ) {
            $node = array_pop( $stack );
            if ( empty( $stack ) ) {
                $result[] = $node;
            } else {
                $stack[ count( $stack ) - 1 ]['children'][] = $node;
            }
        }

        return $result;
    }

    private function parse_attrs( string $attr_string ): array {
        $attrs = [];
        preg_match_all( '/(\w+)=(?:"([^"]*)"|\'([^\']*)\'|(\S+))/', $attr_string, $matches, PREG_SET_ORDER );
        foreach ( $matches as $m ) {
            $attrs[ $m[1] ] = $m[2] !== '' ? $m[2] : ( $m[3] !== '' ? $m[3] : ( $m[4] ?? '' ) );
        }
        return $attrs;
    }
}
