<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'opensheetmusicdisplay' => [
        'version' => '2.0.0',
    ],
    'osmd-audio-player' => [
        'version' => '0.7.0',
    ],
    'soundfont-player' => [
        // interop shim (see assets/shims/soundfont-player.js); re-exports the
        // vendored default export (assets/vendor/soundfont-player/…) as named
        // exports so osmd-audio-player's namespace import works.
        'path' => './assets/shims/soundfont-player.js',
    ],
    'standardized-audio-context' => [
        'version' => '24.1.26',
    ],
    'audio-loader' => [
        'version' => '0.5.0',
    ],
    'sample-player' => [
        'version' => '0.5.5',
    ],
    'note-parser' => [
        'version' => '1.1.0',
    ],
    'automation-events' => [
        'version' => '2.0.19',
    ],
    'adsr' => [
        'version' => '1.0.1',
    ],
    'midimessage' => [
        'version' => '1.0.5',
    ],
];
