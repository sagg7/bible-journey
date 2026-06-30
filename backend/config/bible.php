<?php

return [

    /*
￼     * Proveedor de texto bíblico de dominio público (wldeh/bible-api vía jsDelivr CDN).
     * Estructura: {base}/{version}/books/{book}/chapters/{chapter}/verses/{verse}.json
     */
    'provider' => [
        'base' => 'https://cdn.jsdelivr.net/gh/wldeh/bible-api/bibles',
    ],

    /*
     * Mapeo: code de nuestra tabla `translations` → versión del proveedor.
     * Solo se listan traducciones de dominio público que podemos importar a texto completo.
     */
    'versions' => [
        'RVA1909' => 'es-rv09',
        'WEB' => 'en-web',
    ],

    /*
     * Mapeo del slug de `biblical_books` al slug del proveedor, por versión
     * (cada idioma nombra los libros en su idioma).
     */
    'book_slugs' => [
        'es-rv09' => [
            '1-samuel' => '1samuel',
            '2-samuel' => '2samuel',
            '1-reyes' => '1reyes',
            '1-cronicas' => '1crónicas',
            'salmos' => 'salmos',
        ],
        'en-web' => [
            '1-samuel' => '1samuel',
            '2-samuel' => '2samuel',
            '1-reyes' => '1kings',
            '1-cronicas' => '1chronicles',
            'salmos' => 'psalms',
        ],
    ],
];
