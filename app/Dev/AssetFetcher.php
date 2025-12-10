<?php

namespace WPFreighter\Dev;

class AssetFetcher {

    public static function fetch() {
        $base_dir = dirname( dirname( __DIR__ ) ) . '/assets';
    
        $versions = [
            'vue'     => '3.5.22',
            'vuetify' => '3.10.5',
            'axios'   => '1.13.2',
            'mdi'     => '7.4.47',
        ];

        $files = [
            // JS
            'js/vue.min.js'     => "https://cdn.jsdelivr.net/npm/vue@{$versions['vue']}/dist/vue.global.prod.js",
            'js/vuetify.min.js' => "https://cdn.jsdelivr.net/npm/vuetify@{$versions['vuetify']}/dist/vuetify.min.js",
            'js/axios.min.js'   => "https://cdn.jsdelivr.net/npm/axios@{$versions['axios']}/dist/axios.min.js",
            
            // CSS
            'css/vuetify.min.css' => "https://cdn.jsdelivr.net/npm/vuetify@{$versions['vuetify']}/dist/vuetify.min.css",
            'css/materialdesignicons.min.css' => "https://cdn.jsdelivr.net/npm/@mdi/font@{$versions['mdi']}/css/materialdesignicons.min.css",
        ];

        // MDI Fonts (Must match the filenames referenced in the CSS)
        $fonts = [
            'materialdesignicons-webfont.eot',
            'materialdesignicons-webfont.ttf',
            'materialdesignicons-webfont.woff',
            'materialdesignicons-webfont.woff2',
        ];

        foreach ( $fonts as $font ) {
            $files["fonts/{$font}"] = "https://cdn.jsdelivr.net/npm/@mdi/font@{$versions['mdi']}/fonts/{$font}";
        }

        echo "📦 Fetching assets...\n";

        foreach ( $files as $local_path => $url ) {
            $dest = $base_dir . '/' . $local_path;
            $dir  = dirname( $dest );

            if ( ! is_dir( $dir ) ) {
                mkdir( $dir, 0755, true );
            }

            echo "   Downloading: $local_path ... ";
            
            $content = @file_get_contents( $url );
            
            if ( $content === false ) {
                echo "\033[31mFAILED\033[0m (Check URL or version)\n";
                continue;
            }

            file_put_contents( $dest, $content );
            echo "\033[32mOK\033[0m\n";
        }

        echo "✨ Assets updated successfully.\n";
    }
}