<?php

class WPB2EL_Parser {

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
        $stack = [];

        foreach ( $tokens as $token ) {
            if ( $token['type'] === 'open' ) {
                $node = [
                    'tag'      => $token['tag'],
                    'attrs'    => $token['attrs'],
                    'content'  => '',
                    'children' => []
                ];
                array_push( $stack, $node );
            } elseif ( $token['type'] === 'close' ) {
                if ( ! empty( $stack ) ) {
                    $node = array_pop( $stack );
                    if ( empty( $stack ) ) {
                        $result[] = $node;
                    } else {
                        $stack[ count( $stack ) - 1 ]['children'][] = $node;
                    }
                }
            } elseif ( $token['type'] === 'text' ) {
                if ( ! empty( $stack ) ) {
                    $stack[ count( $stack ) - 1 ]['content'] .= $token['content'];
                } else {
                    $result[] = [
                        'tag'      => '__text__',
                        'attrs'    => [],
                        'content'  => $token['content'],
                        'children' => []
                    ];
                }
            }
        }

        // Flush any unclosed frames to result
        while ( count( $stack ) > 1 ) {
            $frame = array_pop( $stack );
            $top   = array_pop( $stack );
            if ( ! is_array( $top ) ) $top = [];
            if ( isset( $frame['node'] ) ) {
                $frame['node']['children'] = [];
                $top[] = $frame['node'];
            } elseif ( is_array( $frame ) ) {
                $top = array_merge( $top, array_values( $frame ) );
            }
            array_push( $stack, $top );
        }

        // Return result, plus any remaining unclosed nodes from the stack
        if ( ! empty( $stack ) && isset( $stack[0]['tag'] ) ) {
            $result[] = $stack[0];
        }
        return $result;
    }

    private function parse_attrs( string $attr_string ): array {
        $attrs = [];
        // Match name="value", name='value', or name=value (unquoted)
        preg_match_all( '/(\w+)=(?:"([^"]*)"|\'([^\']*)\'|(\S+))/', $attr_string, $matches, PREG_SET_ORDER );
        foreach ( $matches as $m ) {
            $attrs[ $m[1] ] = $m[2] !== '' ? $m[2] : ( $m[3] !== '' ? $m[3] : $m[4] );
        }
        return $attrs;
    }
}
