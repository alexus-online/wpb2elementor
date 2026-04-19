<?php

class WPB2EL_Mapper {

    private static array $map = [
        'vc_row'             => [ 'elType' => 'section' ],
        'vc_row_inner'       => [ 'elType' => 'section' ],
        'vc_column'          => [ 'elType' => 'column' ],
        'vc_column_inner'    => [ 'elType' => 'column' ],
        'vc_column_text'     => [ 'elType' => 'widget', 'widgetType' => 'text-editor' ],
        'vc_custom_heading'  => [ 'elType' => 'widget', 'widgetType' => 'heading' ],
        'vc_single_image'    => [ 'elType' => 'widget', 'widgetType' => 'image' ],
        'vc_btn'             => [ 'elType' => 'widget', 'widgetType' => 'button' ],
        'vc_separator'       => [ 'elType' => 'widget', 'widgetType' => 'divider' ],
        'vc_empty_space'     => [ 'elType' => 'widget', 'widgetType' => 'spacer' ],
        'vc_video'           => [ 'elType' => 'widget', 'widgetType' => 'video' ],
        'vc_gallery'         => [ 'elType' => 'widget', 'widgetType' => 'image-gallery' ],
        'vc_icon'            => [ 'elType' => 'widget', 'widgetType' => 'icon' ],
        'vc_raw_html'        => [ 'elType' => 'widget', 'widgetType' => 'html' ],
        'vc_accordion'       => [ 'elType' => 'widget', 'widgetType' => 'accordion' ],
        'vc_accordion_tab'   => [ 'elType' => 'widget', 'widgetType' => 'accordion' ],
        'vc_tabs'            => [ 'elType' => 'widget', 'widgetType' => 'tabs' ],
        'vc_tab'             => [ 'elType' => 'widget', 'widgetType' => 'tabs' ],
        'vc_toggle'          => [ 'elType' => 'widget', 'widgetType' => 'toggle' ],
        'vc_progress_bar'    => [ 'elType' => 'widget', 'widgetType' => 'progress' ],
        'vc_cta'             => [ 'elType' => 'widget', 'widgetType' => 'button' ],
        'vc_text_separator'  => [ 'elType' => 'widget', 'widgetType' => 'heading' ],
    ];

    public function map( string $tag ): array {
        if ( isset( self::$map[ $tag ] ) ) {
            return array_merge( self::$map[ $tag ], [ 'known' => true ] );
        }
        return [ 'elType' => 'widget', 'widgetType' => 'html', 'known' => false ];
    }

    public function is_container( string $tag ): bool {
        return in_array( $tag, [ 'vc_row', 'vc_row_inner', 'vc_column', 'vc_column_inner' ], true );
    }
}
