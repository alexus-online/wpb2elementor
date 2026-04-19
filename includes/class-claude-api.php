<?php

class WPB2EL_Claude_API {

    private string $api_key;
    private string $model    = 'claude-haiku-4-5-20251001';
    private string $endpoint = 'https://api.anthropic.com/v1/messages';

    public function __construct( string $api_key ) {
        $this->api_key = $api_key;
    }

    public function convert_node( array $node ): ?array {
        $shortcode = $this->rebuild_shortcode( $node );

        $prompt = "You are a WordPress developer. Convert this WPBakery shortcode to a single Elementor widget JSON object.\n\n"
            . "Shortcode:\n```\n{$shortcode}\n```\n\n"
            . "Return ONLY valid JSON for a single Elementor element:\n"
            . "{\n  \"elType\": \"widget\",\n  \"widgetType\": \"<type>\",\n  \"settings\": {},\n  \"elements\": []\n}\n\n"
            . "If it cannot be meaningfully converted, use widgetType 'html' with the original shortcode in settings.html.\n"
            . "Return only the JSON object, no explanation.";

        $response = wp_remote_post( $this->endpoint, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => json_encode( [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) return null;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $text = $body['content'][0]['text'] ?? '';

        if ( preg_match( '/\{[\s\S]+\}/', $text, $m ) ) {
            $el = json_decode( $m[0], true );
            if ( $el && isset( $el['elType'] ) ) {
                $el['id'] = substr( md5( uniqid( '', true ) ), 0, 8 );
                return $el;
            }
        }

        return null;
    }

    private function rebuild_shortcode( array $node ): string {
        $attrs = '';
        foreach ( $node['attrs'] as $k => $v ) {
            $attrs .= " {$k}=\"" . htmlspecialchars( $v, ENT_QUOTES ) . "\"";
        }
        return "[{$node['tag']}{$attrs}]{$node['content']}[/{$node['tag']}]";
    }
}
