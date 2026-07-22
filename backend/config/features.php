<?php

/*
|--------------------------------------------------------------------------
| Feature flags de contenido
|--------------------------------------------------------------------------
|
| Bloqueos por licencia: el contenido cuya licencia no está comprobada NO se
| elimina — se bloquea para producción y queda disponible en desarrollo.
| Ver docs/legal/content-license-registry.md y release-license-blockers.md.
|
*/

return [

    /*
    | Espíritu de Profecía (excerpts EGW vía EGW Writings API).
    | Los originales en inglés (serie Conflicto de los Siglos) son dominio
    | público en EE.UU., pero (a) las traducciones al español son obras
    | derivadas con copyright propio del EGW Estate / casas editoras, y
    | (b) el uso del API está sujeto a sus términos. Hasta tener la licencia
    | documentada, apagado fuera de entornos locales.
    */
    'spirit_of_prophecy' => (bool) env(
        'FEATURE_SPIRIT_OF_PROPHECY',
        env('APP_ENV', 'production') === 'local'
    ),

];
