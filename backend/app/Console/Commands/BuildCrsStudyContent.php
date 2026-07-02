<?php

namespace App\Console\Commands;

use App\Models\ChronologicalReadingSet;
use App\Models\CrsStudyContent;
use App\Models\ParallelLink;
use Illuminate\Console\Command;

class BuildCrsStudyContent extends Command
{
    protected $signature = 'crs:build-study-content {--force : Replace existing generated content}';

    protected $description = 'Build starter study content for CRS reader nodes';

    private const PEOPLE = [
        'Adan', 'Eva', 'Noe', 'Abraham', 'Sara', 'Isaac', 'Rebeca', 'Jacob', 'Raquel', 'Lea',
        'Jose', 'Moises', 'Aaron', 'Josue', 'Rahab', 'Debora', 'Gedeon', 'Sanson', 'Samuel',
        'Saul', 'David', 'Jonatan', 'Salomon', 'Elias', 'Eliseo', 'Isaias', 'Jeremias',
        'Jonas', 'Amos', 'Oseas', 'Miqueas', 'Nahum', 'Sofonias', 'Habacuc', 'Abdias',
        'Hageo', 'Zacarias', 'Malaquias', 'Ezequiel', 'Daniel', 'Esdras', 'Nehemias', 'Ester',
        'Ciro', 'Dario', 'Artajerjes', 'Job', 'Maria', 'Juan el Bautista',
        'Jesus', 'Pedro', 'Juan', 'Jacobo', 'Pablo', 'Bernabe', 'Silas', 'Timoteo', 'Tito',
        'Rut', 'Noemi', 'Booz',
    ];

    private const PLACES = [
        'Eden', 'Ararat', 'Babel', 'Haran', 'Canaan', 'Egipto', 'Sinai', 'Jerico',
        'Jerusalen', 'Belen', 'Hebron', 'Siquem', 'Silo', 'Gat', 'Nob', 'Adulam', 'Zif',
        'Gabaon', 'Carmelo', 'Horeb', 'Samaria', 'Asiria', 'Ninive', 'Moab', 'Edom', 'Tiro',
        'Babilonia', 'Susa', 'Quebar', 'Nazaret', 'Galilea', 'Judea', 'Capernaum',
        'Betania', 'Emaus', 'Damasco', 'Antioquia', 'Chipre', 'Iconio', 'Listra', 'Derbe',
        'Filipos', 'Macedonia', 'Tesalonica', 'Berea', 'Atenas', 'Corinto', 'Efeso',
        'Cesarea', 'Troas', 'Mileto', 'Malta', 'Roma', 'Patmos', 'Moab', 'Belen', 'Galacia',
        'Colosas', 'Creta', 'Grecia',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $created = 0;
        $updated = 0;
        $skipped = 0;

        ChronologicalReadingSet::with(['blocks', 'compareGroups', 'studyContent'])
            ->chunkById(100, function ($crsRows) use ($force, &$created, &$updated, &$skipped) {
                foreach ($crsRows as $crs) {
                    if ($crs->studyContent && ! $force) {
                        $skipped++;
                        continue;
                    }

                    $content = CrsStudyContent::updateOrCreate(
                        ['crs_id' => $crs->id],
                        $this->buildPayload($crs)
                    );

                    $content->wasRecentlyCreated ? $created++ : $updated++;
                }
            });

        $this->info("Study content ready. Created={$created}, updated={$updated}, skipped={$skipped}.");

        return self::SUCCESS;
    }

    private function buildPayload(ChronologicalReadingSet $crs): array
    {
        $references = $crs->blocks
            ->pluck('display_reference')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $referenceText = empty($references) ? 'sin bloque de lectura directo' : implode('; ', $references);
        $era = $crs->user_facing_era ?: $crs->era;
        $summary = $crs->editorial_note ?: "{$crs->title_es} reune {$referenceText} dentro de la secuencia cronologica.";
        $context = $crs->narrative_flow_message_es
            ?: "Este bloque pertenece a {$era}. Su lugar editorial se basa en la referencia {$crs->source_map} y en una confianza de ubicacion marcada como {$crs->placement_confidence}.";

        return [
            'summary_es' => $summary,
            'context_es' => $context,
            'people' => $this->detectPeople($crs),
            'places' => $this->detectPlaces($crs),
            'connections' => $this->connectionsFor($crs),
            'sources' => array_map(fn ($ref) => ['label' => $ref], $references),
            'content_version' => 'auto-v1',
        ];
    }

    private function detectPeople(ChronologicalReadingSet $crs): array
    {
        $text = $this->searchText($crs);
        $people = [];

        foreach (self::PEOPLE as $name) {
            if ($this->containsTerm($text, $name)) {
                $people[] = $this->person($name, 'Mencionado en el titulo o referencia del bloque');
            }
        }

        foreach ($this->peopleBySourceMap($crs) as $person) {
            $people[] = $person;
        }

        return $this->uniqueByName($people);
    }

    private function detectPlaces(ChronologicalReadingSet $crs): array
    {
        $text = $this->searchText($crs);
        $places = [];

        foreach (self::PLACES as $name) {
            if ($this->containsTerm($text, $name)) {
                $places[] = $this->place($name, 'Detectado desde el titulo editorial del bloque');
            }
        }

        foreach ($this->placesBySourceMap($crs) as $place) {
            $places[] = $place;
        }

        return $this->uniqueByName($places);
    }

    private function peopleBySourceMap(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $people = [];

        if (str_starts_with($map, 'CRS-EXO-')) {
            $people[] = $this->person('Moises', 'Lider central del exodo y mediador del pacto');
            $people[] = $this->person('Aaron', 'Sacerdote y companero de Moises');
            if (preg_match('/CRS-EXO-00[3-5]/', $map)) {
                $people[] = $this->person('Faraon', 'Rey de Egipto confrontado por Dios');
            }
        } elseif (str_starts_with($map, 'CRS-LEV-')) {
            $people[] = $this->person('Aaron', 'Figura sacerdotal central del culto');
            $people[] = $this->person('Moises', 'Recibe y transmite la instruccion del pacto');
        } elseif (str_starts_with($map, 'CRS-NUM-')) {
            $people[] = $this->person('Moises', 'Lider de Israel en el desierto');
            $people[] = $this->person('Aaron', 'Sacerdote junto a Moises');
            if ($map === 'CRS-NUM-010') {
                $people[] = $this->person('Josue', 'Sucesor designado para guiar a Israel');
            }
            if ($map === 'CRS-NUM-008') {
                $people[] = $this->person('Balaam', 'Profeta contratado por Moab');
            }
        } elseif (str_starts_with($map, 'CRS-DEU-')) {
            $people[] = $this->person('Moises', 'Da sus ultimos discursos a Israel');
            if (in_array($map, ['CRS-DEU-007', 'CRS-DEU-008'], true)) {
                $people[] = $this->person('Josue', 'Continuador del liderazgo de Moises');
            }
        } elseif (str_starts_with($map, 'CRS-JOS-')) {
            $people[] = $this->person('Josue', 'Lider de Israel en la conquista y reparto de la tierra');
            if ($map === 'CRS-JOS-001') {
                $people[] = $this->person('Rahab', 'Ayuda a los espias en Jerico');
            }
        } elseif (str_starts_with($map, 'CRS-JDG-')) {
            $people = array_merge($people, $this->judgesPeople($crs));
        } elseif (str_starts_with($map, 'CRS-RUT-')) {
            $people[] = $this->person('Rut', 'Figura central del relato de lealtad y linaje davidico');
            $people[] = $this->person('Noemi', 'Suegra de Rut y figura clave del retorno familiar');
            $people[] = $this->person('Booz', 'Redentor familiar en la linea davidica');
        } elseif (str_starts_with($map, 'CRS-GEN-') && $crs->stream_role === 'main_historical_event') {
            $people = array_merge($people, $this->genesisPeople($crs));
        } elseif (str_starts_with($map, 'CRS-1SA-')) {
            $people = array_merge($people, $this->samuelPeople($crs));
        } elseif (str_starts_with($map, 'CRS-2SA-') || str_starts_with($map, 'CRS-1CH-')) {
            $people[] = $this->person('David', 'Rey de Israel y figura central de este tramo');
        } elseif (str_starts_with($map, 'CRS-PSA-')) {
            $people = array_merge($people, $this->psalmPeople($crs));
        } elseif (str_starts_with($map, 'CRS-WIS-')) {
            $people = array_merge($people, $this->wisdomPeople($crs));
        } elseif (str_starts_with($map, 'CRS-1KG-') || str_starts_with($map, 'CRS-2K-') || str_starts_with($map, 'CRS-2CH-') || str_starts_with($map, 'CRS-BR-2CH-')) {
            $people = array_merge($people, $this->kingsPeople($crs));
        } elseif (str_starts_with($map, 'CRS-EZK-GRP-')) {
            $people[] = $this->person('Ezequiel', 'Profeta asociado con las visiones de restauracion');
        } elseif (str_starts_with($map, 'CRS-06-')) {
            $people[] = $this->person('Ester', 'Reina judia en la corte persa y figura central del relato');
        } elseif (str_starts_with($map, 'CRS-04-') || str_starts_with($map, 'CRS-05-')) {
            $people = array_merge($people, $this->propheticPeople($crs));
        } elseif (str_starts_with($map, 'CRS-07-') || str_starts_with($map, 'CRS-GAP-MAT-')) {
            $people = array_merge($people, $this->gospelPeople($crs));
        } elseif (str_starts_with($map, 'CRS-ACT-') || str_starts_with($map, 'CRS-NT-')) {
            $people = array_merge($people, $this->actsPeople($crs));
        } elseif (str_starts_with($map, 'CRS-PAUL-') || str_contains($map, 'ROM') || str_contains($map, 'COR') || str_contains($map, 'GAL') || str_contains($map, 'EPH') || str_contains($map, 'PHI') || str_contains($map, 'COL') || str_contains($map, 'TH') || str_contains($map, 'TIM') || str_contains($map, 'TIT')) {
            $people[] = $this->person('Pablo', 'Autor apostolico o figura principal de la correspondencia paulina');
        } elseif (str_starts_with($map, 'CRS-GLET-') || str_starts_with($map, 'CRS-REV-')) {
            $people = array_merge($people, $this->generalLettersPeople($crs));
        }

        return $people;
    }

    private function placesBySourceMap(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $places = [];

        if (str_starts_with($map, 'CRS-EXO-')) {
            if (preg_match('/CRS-EXO-00[1-5]/', $map)) {
                $places[] = $this->place('Egipto', 'Escenario principal de la opresion y salida');
            }
            if (preg_match('/CRS-EXO-00[7-9]|CRS-EXO-010|CRS-EXO-011/', $map)) {
                $places[] = $this->place('Sinai', 'Monte y region asociados con el pacto');
            }
        } elseif (str_starts_with($map, 'CRS-LEV-')) {
            $places[] = $this->place('Sinai', 'Contexto del tabernaculo y la instruccion sacerdotal');
        } elseif (str_starts_with($map, 'CRS-NUM-') || str_starts_with($map, 'CRS-DEU-')) {
            $places[] = $this->place('Desierto', 'Marco del peregrinaje de Israel antes de entrar a la tierra');
        } elseif (str_starts_with($map, 'CRS-JOS-')) {
            $places[] = $this->place('Canaan', 'Tierra prometida y escenario de conquista/reparto');
            if (in_array($map, ['CRS-JOS-002', 'CRS-JOS-003'], true)) {
                $places[] = $this->place('Jerico', 'Ciudad clave al inicio de la entrada a la tierra');
            }
        } elseif (str_starts_with($map, 'CRS-JDG-')) {
            $places = array_merge($places, $this->judgesPlaces($crs));
        } elseif (str_starts_with($map, 'CRS-RUT-')) {
            $places[] = $this->place('Moab', 'Lugar de origen de Rut antes de unirse a Noemi');
            $places[] = $this->place('Belen', 'Ciudad donde Rut y Booz entran en la linea davidica');
        } elseif (str_starts_with($map, 'CRS-1SA-')) {
            $places = array_merge($places, $this->samuelPlaces($crs));
        } elseif (str_starts_with($map, 'CRS-2SA-') || str_starts_with($map, 'CRS-1CH-')) {
            $places = array_merge($places, $this->davidPlaces($crs));
        } elseif (str_starts_with($map, 'CRS-07-') || str_starts_with($map, 'CRS-GAP-MAT-')) {
            $places = array_merge($places, $this->gospelPlaces($crs));
        } elseif (str_starts_with($map, 'CRS-GEN-') && $crs->stream_role === 'main_historical_event') {
            $places = array_merge($places, $this->genesisPlaces($crs));
        } elseif (str_starts_with($map, 'CRS-PSA-')) {
            $places = array_merge($places, $this->psalmPlaces($crs));
        } elseif (str_starts_with($map, 'CRS-WIS-')) {
            $places[] = $this->place('Jerusalen', 'Contexto tradicional de sabiduria real y ensenanza de Israel');
        } elseif (str_starts_with($map, 'CRS-1KG-') || str_starts_with($map, 'CRS-2K-') || str_starts_with($map, 'CRS-2CH-') || str_starts_with($map, 'CRS-BR-2CH-')) {
            $places = array_merge($places, $this->kingsPlaces($crs));
        } elseif (str_starts_with($map, 'CRS-EZK-GRP-')) {
            $places[] = $this->place('Babilonia', 'Marco exilico de las visiones de Ezequiel');
            $places[] = $this->place('Jerusalen', 'Destino simbolico de la restauracion anunciada');
        } elseif (str_starts_with($map, 'CRS-06-')) {
            $places[] = $this->place('Susa', 'Corte persa donde se desarrolla la historia de Ester');
        } elseif (str_starts_with($map, 'CRS-BRIDGE-')) {
            $places[] = $this->place('Judea', 'Marco historico del pueblo judio entre los Testamentos');
        } elseif (str_starts_with($map, 'CRS-04-') || str_starts_with($map, 'CRS-05-')) {
            $places = array_merge($places, $this->propheticPlaces($crs));
        } elseif (str_starts_with($map, 'CRS-ACT-') || str_starts_with($map, 'CRS-NT-') || str_starts_with($map, 'CRS-PAUL-')) {
            $places = array_merge($places, $this->ntMissionPlaces($crs));
        } elseif (str_starts_with($map, 'CRS-GLET-') || str_starts_with($map, 'CRS-REV-')) {
            $places = array_merge($places, $this->generalLettersPlaces($crs));
        } elseif (str_starts_with($map, 'CRS-ACT-')) {
            $places[] = $this->place('Jerusalen', 'Punto de partida de la iglesia primitiva');
        }

        return $places;
    }

    private function judgesPeople(ChronologicalReadingSet $crs): array
    {
        $title = $this->normalize($crs->title_es);
        $people = [];

        $byMap = [
            'CRS-JDG-002' => ['Debora', 'Barac'],
            'CRS-JDG-003' => ['Gedeon'],
            'CRS-JDG-004' => ['Abimelec'],
            'CRS-JDG-005' => ['Jefte'],
            'CRS-JDG-006' => ['Sanson'],
        ];

        foreach ($byMap[$crs->source_map] ?? [] as $name) {
            $people[] = $this->person($name, 'Juez o figura principal del relato');
        }

        foreach (['Debora', 'Barac', 'Gedeon', 'Abimelec', 'Jefte', 'Sanson'] as $name) {
            if ($this->containsTerm($title, $name)) {
                $people[] = $this->person($name, 'Juez o figura principal del relato');
            }
        }

        return $people;
    }

    private function genesisPeople(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $people = [];

        if (preg_match('/CRS-GEN-019|CRS-GEN-020/', $map)) {
            $people[] = $this->person('Adan', 'Primer ser humano en el relato de los comienzos');
            $people[] = $this->person('Eva', 'Primera mujer en el relato de los comienzos');
        }
        if ($map === 'CRS-GEN-021') {
            $people[] = $this->person('Noe', 'Figura central del diluvio y nuevo comienzo');
        }
        if (preg_match('/CRS-GEN-023|CRS-GEN-024/', $map)) {
            $people[] = $this->person('Abraham', 'Patriarca llamado por Dios');
            $people[] = $this->person('Sara', 'Matriarca asociada con la promesa');
        }
        if ($map === 'CRS-GEN-025') {
            $people[] = $this->person('Isaac', 'Hijo de la promesa');
            $people[] = $this->person('Rebeca', 'Matriarca en la linea de la promesa');
            $people[] = $this->person('Jacob', 'Patriarca tambien llamado Israel');
        }
        if (preg_match('/CRS-GEN-016|CRS-GEN-017|CRS-GEN-018|CRS-GEN-026/', $map)) {
            $people[] = $this->person('Jacob', 'Patriarca tambien llamado Israel');
            $people[] = $this->person('Jose', 'Hijo de Jacob llevado a Egipto');
        }

        return $people;
    }

    private function genesisPlaces(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $places = [];

        if (preg_match('/CRS-GEN-019|CRS-GEN-020/', $map)) {
            $places[] = $this->place('Eden', 'Escenario del relato de los comienzos');
        }
        if (preg_match('/CRS-GEN-023|CRS-GEN-024|CRS-GEN-025/', $map)) {
            $places[] = $this->place('Canaan', 'Tierra asociada con la promesa patriarcal');
        }
        if (preg_match('/CRS-GEN-016|CRS-GEN-017|CRS-GEN-018|CRS-GEN-026/', $map)) {
            $places[] = $this->place('Egipto', 'Escenario de la historia de Jose y su familia');
        }

        return $places;
    }

    private function psalmPeople(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $title = $this->normalize($crs->title_es);
        $people = [];

        if (preg_match('/CRS-PSA-00[2-9]|CRS-PSA-01[0-9]|CRS-PSA-02[0-7]|CRS-PSA-03[4-8]|CRS-PSA-04[0-2]/', $map) || $this->containsTerm($title, 'david')) {
            $people[] = $this->person('David', 'Autor o figura tradicionalmente asociada con este salmo');
        }
        if ($map === 'CRS-PSA-027') {
            $people[] = $this->person('Moises', 'Figura tradicionalmente asociada con el Salmo 90');
        }
        if (in_array($map, ['CRS-PSA-025', 'CRS-PSA-036'], true)) {
            $people[] = $this->person('Salomon', 'Figura real asociada con este salmo');
        }
        if (in_array($map, ['CRS-PSA-011', 'CRS-PSA-026'], true)) {
            $people[] = $this->person('Asaf', 'Nombre asociado con una coleccion liturgica de salmos');
        }

        return $people;
    }

    private function psalmPlaces(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $title = $this->normalize($crs->title_es);
        $places = [];

        if (preg_match('/CRS-PSA-029|CRS-PSA-031|CRS-PSA-032|CRS-PSA-034|CRS-PSA-035|CRS-PSA-037|CRS-PSA-039|CRS-PSA-041|CRS-PSA-043/', $map) || $this->containsTerm($title, 'sion')) {
            $places[] = $this->place('Jerusalen', 'Lugar liturgico asociado con Sion, templo o peregrinacion');
        }
        if ($map === 'CRS-PSA-023') {
            $places[] = $this->place('Judea', 'Desierto de Juda mencionado en el titulo editorial');
        }
        if (preg_match('/CRS-PSA-014|CRS-PSA-015/', $map)) {
            $places[] = $this->place('Zif', 'Region vinculada con la persecucion de David');
        }
        if (preg_match('/CRS-PSA-018|CRS-PSA-020/', $map)) {
            $places[] = $this->place('Adulam', 'Lugar probable de refugio asociado con David');
        }

        return $places;
    }

    private function wisdomPeople(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $people = [];

        if (in_array($map, ['CRS-WIS-001', 'CRS-WIS-002', 'CRS-WIS-003', 'CRS-WIS-004'], true)) {
            $people[] = $this->person('Salomon', 'Figura tradicionalmente asociada con sabiduria y literatura sapiencial');
        }
        if ($map === 'CRS-WIS-005') {
            $people[] = $this->person('Ezequias', 'Rey asociado con la coleccion de proverbios preservada por sus escribas');
        }
        if ($map === 'CRS-WIS-006') {
            $people[] = $this->person('Agur', 'Sabio mencionado en Proverbios 30');
        }
        if ($map === 'CRS-WIS-007') {
            $people[] = $this->person('Lemuel', 'Rey asociado con Proverbios 31');
        }

        return $people;
    }

    private function samuelPeople(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $people = [];

        if (preg_match('/CRS-1SA-00[1-2]/', $map)) {
            $people[] = $this->person('Samuel', 'Profeta y juez de Israel');
        }
        if (preg_match('/CRS-1SA-00[3-9]|CRS-1SA-01[0-9]|CRS-1SA-020|CRS-1SA-021/', $map)) {
            $people[] = $this->person('Saul', 'Primer rey de Israel');
        }
        if (preg_match('/CRS-1SA-00[7-9]|CRS-1SA-01[0-9]|CRS-1SA-020|CRS-1SA-021/', $map)) {
            $people[] = $this->person('David', 'Ungido como futuro rey y perseguido por Saul');
        }
        if (in_array($map, ['CRS-1SA-010', 'CRS-1SA-011'], true)) {
            $people[] = $this->person('Jonatan', 'Aliado y amigo de David');
        }

        return $people;
    }

    private function samuelPlaces(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $title = $this->normalize($crs->title_es);
        $places = [];

        if (preg_match('/CRS-1SA-00[1-5]/', $map)) {
            $places[] = $this->place('Silo', 'Centro religioso temprano en los relatos de Samuel');
        }
        if (preg_match('/CRS-1SA-00[6-9]|CRS-1SA-01[0-9]|CRS-1SA-020|CRS-1SA-021/', $map)) {
            $places[] = $this->place('Judea', 'Region de la vida temprana de David y la persecucion de Saul');
        }
        foreach (['Gat', 'Nob', 'Adulam', 'Zif'] as $name) {
            if ($this->containsTerm($title, $name)) {
                $places[] = $this->place($name, 'Lugar nombrado en el titulo editorial');
            }
        }
        if ($this->containsTerm($title, 'Siclag')) {
            $places[] = $this->place('Siclag', 'Ciudad relacionada con David entre los filisteos');
        }
        if ($this->containsTerm($title, 'Gilboa')) {
            $places[] = $this->place('Gilboa', 'Monte donde cae Saul');
        }

        return $places;
    }

    private function davidPlaces(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $title = $this->normalize($crs->title_es);
        $places = [];

        if (preg_match('/CRS-2SA-001|CRS-2SA-002/', $map)) {
            $places[] = $this->place('Hebron', 'Primera sede real de David en Juda');
        }
        if (preg_match('/CRS-2SA-00[3-9]|CRS-2SA-01[0-7]|CRS-2SA-02[0-1]|CRS-1CH-00[8-9]|CRS-1CH-01[0-9]|CRS-1CH-020/', $map)) {
            $places[] = $this->place('Jerusalen', 'Capital davidica y centro del arca, el templo y el reino');
        }
        if ($this->containsTerm($title, 'Amon')) {
            $places[] = $this->place('Amon', 'Region vecina involucrada en conflicto con David');
        }
        if ($this->containsTerm($title, 'filisteos')) {
            $places[] = $this->place('Gat', 'Ciudad filistea representativa de los conflictos con David');
        }

        return $places;
    }

    private function judgesPlaces(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $places = [$this->place('Canaan', 'Marco territorial del periodo de los jueces')];

        if ($map === 'CRS-JDG-002') {
            $places[] = $this->place('Carmelo', 'Region del norte asociada con el conflicto contra Canaan');
        }
        if ($map === 'CRS-JDG-008') {
            $places[] = $this->place('Guibea', 'Lugar de la crisis que desemboca en guerra civil');
        }

        return $places;
    }

    private function kingsPeople(ChronologicalReadingSet $crs): array
    {
        $title = $this->normalize($crs->title_es);
        $map = $crs->source_map;
        $people = [];

        if ($this->containsTerm($title, 'Salomon') || preg_match('/CRS-1KG-00[1-9]|CRS-1KG-010|CRS-BR-2CH-00[1-5]/', $map)) {
            $people[] = $this->person('Salomon', 'Rey asociado con sabiduria, templo y reino unido');
        }
        if (preg_match('/CRS-1KG-011|CRS-1KG-013|CRS-BR-2CH-006|CRS-BR-2CH-007/', $map)) {
            $people[] = $this->person('Roboam', 'Rey de Juda durante la division del reino');
            $people[] = $this->person('Jeroboam', 'Rey del norte durante la division del reino');
        }
        if (preg_match('/CRS-1KG-014|CRS-BR-2CH-009|CRS-BR-2CH-010|CRS-BR-2CH-011/', $map)) {
            $people[] = $this->person('Asa', 'Rey de Juda asociado con reformas y conflictos politicos');
        }
        if (preg_match('/CRS-1KG-021|CRS-BR-2CH-012|CRS-BR-2CH-013|CRS-BR-2CH-014/', $map)) {
            $people[] = $this->person('Josafat', 'Rey de Juda vinculado con reformas y Ramot de Galaad');
        }
        if (preg_match('/CRS-1KG-015|CRS-1KG-019|CRS-1KG-020|CRS-1KG-021/', $map)) {
            $people[] = $this->person('Acab', 'Rey de Israel en los relatos de Elias y los conflictos con Aram');
        }
        if (preg_match('/CRS-1KG-016|CRS-1KG-017|CRS-1KG-018|CRS-1KG-020|CRS-2K-001|CRS-2K-002/', $map)) {
            $people[] = $this->person('Elias', 'Profeta que confronta la idolatria y anuncia el juicio');
        }
        if (preg_match('/CRS-1KG-018|CRS-2K-002|CRS-2K-004|CRS-2K-005|CRS-2K-006|CRS-2K-008A|CRS-2K-013/', $map)) {
            $people[] = $this->person('Eliseo', 'Profeta sucesor de Elias y figura central de varios milagros');
        }
        if (preg_match('/CRS-2K-009|CRS-2K-010/', $map)) {
            $people[] = $this->person('Jehu', 'Rey ungido para ejecutar juicio contra la casa de Acab');
        }
        if (preg_match('/CRS-2K-011|CRS-2K-012/', $map)) {
            $people[] = $this->person('Joas', 'Rey relacionado con la restauracion del templo');
        }
        if ($map === 'CRS-2K-014') {
            $people[] = $this->person('Amasias', 'Rey de Juda mencionado en la narrativa de los reinos');
            $people[] = $this->person('Jeroboam II', 'Rey del norte durante la prosperidad previa al juicio');
        }
        if ($map === 'CRS-2K-015') {
            $people[] = $this->person('Uzias', 'Rey de Juda en la transicion profetica de Isaias');
            $people[] = $this->person('Jotam', 'Rey de Juda mencionado junto a Uzias y Acaz');
        }
        if ($map === 'CRS-2K-016') {
            $people[] = $this->person('Acaz', 'Rey de Juda durante la presion asiria');
        }
        if (preg_match('/CRS-2K-018|CRS-2K-019|CRS-2K-020/', $map)) {
            $people[] = $this->person('Ezequias', 'Rey de Juda durante la reforma y la crisis asiria');
            $people[] = $this->person('Isaias', 'Profeta que interpreta la crisis asiria y babilonica');
        }
        if ($map === 'CRS-2K-021') {
            $people[] = $this->person('Manases', 'Rey de Juda asociado con infidelidad y juicio');
            $people[] = $this->person('Amon', 'Rey de Juda posterior a Manases');
        }
        if (preg_match('/CRS-2K-022|CRS-2K-023/', $map)) {
            $people[] = $this->person('Josias', 'Rey de Juda asociado con reforma, pacto y Pascua');
        }
        if (preg_match('/CRS-2K-024|CRS-2K-025/', $map)) {
            $people[] = $this->person('Joacaz', 'Rey de Juda en la etapa final antes del exilio');
            $people[] = $this->person('Joacim', 'Rey de Juda durante la presion babilonica');
            $people[] = $this->person('Joaquin', 'Rey llevado al exilio babilonico');
        }

        foreach (['Roboam', 'Jeroboam', 'Asa', 'Josafat', 'Elias', 'Eliseo', 'Acab', 'Nabot', 'Ocozias', 'Jehu', 'Joas', 'Amasias', 'Uzias', 'Jotam', 'Acaz', 'Ezequias', 'Manases', 'Amon', 'Josias', 'Joacaz', 'Joacim', 'Joaquin', 'Sedequias'] as $name) {
            if ($this->containsTerm($title, $name)) {
                $people[] = $this->person($name, 'Figura principal de este relato monarquico');
            }
        }

        return $people;
    }

    private function kingsPlaces(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $title = $this->normalize($crs->title_es);
        $places = [];

        if (preg_match('/CRS-1KG-00[1-9]|CRS-1KG-010|CRS-BR-2CH-00[1-5]/', $map)) {
            $places[] = $this->place('Jerusalen', 'Centro del reino unido y del templo');
        }
        if (preg_match('/CRS-1KG-011|CRS-1KG-013|CRS-BR-2CH-006|CRS-BR-2CH-007|CRS-BR-2CH-008|CRS-2K-018|CRS-2K-019|CRS-2K-022|CRS-2K-023|CRS-2K-024|CRS-2K-025/', $map)) {
            $places[] = $this->place('Jerusalen', 'Capital de Juda y foco de la narrativa final del reino');
        }
        if (preg_match('/CRS-1KG-016|CRS-1KG-017|CRS-1KG-018|CRS-2K-001|CRS-2K-002|CRS-2K-004|CRS-2K-005|CRS-2K-006|CRS-2K-007|CRS-2K-008A|CRS-2K-013|CRS-2K-017/', $map)) {
            $places[] = $this->place('Samaria', 'Region y capital del reino del norte');
        }
        if ($this->containsTerm($title, 'Carmelo')) {
            $places[] = $this->place('Carmelo', 'Lugar del enfrentamiento profetico de Elias');
        }
        if ($this->containsTerm($title, 'Horeb')) {
            $places[] = $this->place('Horeb', 'Monte asociado con la renovacion del llamado profetico');
        }
        if ($this->containsTerm($title, 'Gabaon')) {
            $places[] = $this->place('Gabaon', 'Lugar donde Salomon pide sabiduria');
        }
        if ($this->containsTerm($title, 'Moab')) {
            $places[] = $this->place('Moab', 'Region vecina vinculada con conflicto militar');
        }
        if (preg_match('/CRS-2K-017|CRS-2K-019/', $map)) {
            $places[] = $this->place('Asiria', 'Potencia imperial que presiona y derrota a Israel');
        }
        if (preg_match('/CRS-2K-020|CRS-2K-025/', $map)) {
            $places[] = $this->place('Babilonia', 'Potencia imperial conectada con el exilio');
        }

        return $places;
    }

    private function propheticPeople(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $people = [];

        $ranges = [
            'Jonas' => ['/CRS-04-001/'],
            'Amos' => ['/CRS-04-00[2-4]/'],
            'Oseas' => ['/CRS-04-00[5-7]/'],
            'Isaias' => ['/CRS-04-00[8-9]|CRS-04-01[3-8]|CRS-04-04[6-9]|CRS-04-05[0-2]/'],
            'Miqueas' => ['/CRS-04-01[0-2]/'],
            'Nahum' => ['/CRS-04-019/'],
            'Sofonias' => ['/CRS-04-020/'],
            'Jeremias' => ['/CRS-04-02[1-9]|CRS-04-03[0-9]|CRS-04-04[0-5]/'],
            'Habacuc' => ['/CRS-05-001/'],
            'Daniel' => ['/CRS-05-00[2-3]|CRS-05-01[8-9]|CRS-05-02[0-1]|CRS-05-025/'],
            'Ezequiel' => ['/CRS-05-00[4-9]|CRS-05-01[0-6]/'],
            'Abdias' => ['/CRS-05-017/'],
            'Hageo' => ['/CRS-05-02[6-8]/'],
            'Zacarias' => ['/CRS-05-02[9]|CRS-05-03[0]|CRS-05-033/'],
            'Esdras' => ['/CRS-05-03[6-7]/'],
            'Nehemias' => ['/CRS-05-03[8-9]|CRS-05-04[0-5]/'],
            'Malaquias' => ['/CRS-05-046/'],
        ];

        foreach ($ranges as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $map)) {
                    $people[] = $this->person($name, 'Profeta o lider asociado con este bloque');
                    break;
                }
            }
        }

        if (preg_match('/CRS-04-013|CRS-04-018/', $map)) {
            $people[] = $this->person('Acaz', 'Rey de Juda durante la crisis narrada por Isaias');
        }
        if (preg_match('/CRS-04-017|CRS-04-018/', $map)) {
            $people[] = $this->person('Ezequias', 'Rey de Juda durante la crisis asiria y babilonica');
        }
        if (preg_match('/CRS-04-030|CRS-04-037|CRS-04-038|CRS-04-039/', $map)) {
            $people[] = $this->person('Sedequias', 'Rey de Juda durante el sitio final de Jerusalen');
        }
        if ($map === 'CRS-04-040') {
            $people[] = $this->person('Gedalias', 'Gobernador de Juda despues de la caida de Jerusalen');
        }
        if (preg_match('/CRS-04-046|CRS-05-022|CRS-05-025/', $map)) {
            $people[] = $this->person('Ciro', 'Rey persa conectado con el retorno del exilio');
        }
        if (preg_match('/CRS-05-020|CRS-05-031|CRS-05-032/', $map)) {
            $people[] = $this->person('Dario', 'Rey persa asociado con decretos y administracion imperial');
        }
        if (preg_match('/CRS-05-035|CRS-05-036/', $map)) {
            $people[] = $this->person('Artajerjes', 'Rey persa relacionado con Esdras y la oposicion al retorno');
        }

        return $people;
    }

    private function propheticPlaces(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $title = $this->normalize($crs->title_es);
        $places = [];

        if (preg_match('/CRS-04-001|CRS-04-019/', $map)) {
            $places[] = $this->place('Ninive', 'Ciudad asiria relacionada con Jonas y Nahum');
        }
        if (preg_match('/CRS-04-00[2-7]|CRS-2K-017/', $map)) {
            $places[] = $this->place('Samaria', 'Centro del reino del norte antes de su caida');
        }
        if (preg_match('/CRS-04-00[8-9]|CRS-04-01[0-8]|CRS-04-02[0-9]|CRS-04-03[0-9]|CRS-04-04[0-5]|CRS-04-04[8-9]|CRS-04-05[0-1]|CRS-05-014|CRS-05-022|CRS-05-02[3-4]|CRS-05-02[6-9]|CRS-05-03[0-9]|CRS-05-04[0-6]/', $map)) {
            $places[] = $this->place('Jerusalen', 'Centro de Juda, del templo y de la restauracion');
        }
        if (preg_match('/CRS-04-035|CRS-05-00[2-3]|CRS-05-01[8-9]|CRS-05-02[0-5]/', $map)) {
            $places[] = $this->place('Babilonia', 'Escenario imperial del exilio');
        }
        if (preg_match('/CRS-04-04[6-7]/', $map)) {
            $places[] = $this->place('Babilonia', 'Trasfondo del consuelo dirigido a los exiliados');
            $places[] = $this->place('Jerusalen', 'Destino de la restauracion anunciada por Isaias');
        }
        if (preg_match('/CRS-05-00[4-9]|CRS-05-01[0-6]/', $map)) {
            $places[] = $this->place('Quebar', 'Canal babilonico asociado con el llamado de Ezequiel');
        }
        if (preg_match('/CRS-05-017/', $map)) {
            $places[] = $this->place('Edom', 'Nacion vecina denunciada por Abdias');
        }
        if (preg_match('/CRS-05-016/', $map)) {
            $places[] = $this->place('Tiro', 'Ciudad costera mencionada en los oraculos de Ezequiel');
        }
        if ($this->containsTerm($title, 'Egipto')) {
            $places[] = $this->place('Egipto', 'Nacion mencionada en el bloque profetico');
        }
        if ($this->containsTerm($title, 'Susa')) {
            $places[] = $this->place('Susa', 'Centro persa asociado con el periodo del retorno');
        }

        return $places;
    }

    private function gospelPeople(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $title = $this->normalize($crs->title_es);
        $people = [$this->person('Jesus', 'Figura central de los Evangelios')];

        foreach (['Maria', 'Jose', 'Juan el Bautista', 'Pedro', 'Juan', 'Jacobo'] as $name) {
            if ($this->containsTerm($title, $name)) {
                $people[] = $this->person($name, 'Participa en este episodio evangelico');
            }
        }
        if (preg_match('/CRS-07-00[4-8]/', $map)) {
            $people[] = $this->person('Maria', 'Madre de Jesus en los relatos de nacimiento e infancia');
            $people[] = $this->person('Jose', 'Padre legal de Jesus en los relatos de infancia');
        }
        if (preg_match('/CRS-07-00[9-9]|CRS-07-010|CRS-07-033|CRS-07-042/', $map)) {
            $people[] = $this->person('Juan el Bautista', 'Precursor y testigo del ministerio de Jesus');
        }

        return $people;
    }

    private function gospelPlaces(ChronologicalReadingSet $crs): array
    {
        $title = $this->normalize($crs->title_es);
        $places = [];

        foreach (['Belen', 'Nazaret', 'Galilea', 'Judea', 'Samaria', 'Capernaum', 'Jerico', 'Betania', 'Jerusalen', 'Emaus'] as $name) {
            if ($this->containsTerm($title, $name)) {
                $places[] = $this->place($name, 'Escenario nombrado en el titulo editorial');
            }
        }
        if (str_contains($crs->source_map, 'CRS-07-075') || str_contains($crs->source_map, 'CRS-07-076') || str_contains($crs->source_map, 'CRS-07-077') || str_contains($crs->source_map, 'CRS-07-079') || str_contains($crs->source_map, 'CRS-07-080') || str_contains($crs->source_map, 'CRS-07-081') || str_contains($crs->source_map, 'CRS-07-082') || str_contains($crs->source_map, 'CRS-07-086')) {
            $places[] = $this->place('Jerusalen', 'Escenario de la ultima semana, muerte y resurreccion');
        }
        if (preg_match('/CRS-07-00[4-8]/', $crs->source_map)) {
            $places[] = $this->place('Belen', 'Escenario de los relatos de nacimiento');
            $places[] = $this->place('Nazaret', 'Ciudad asociada con la infancia de Jesus');
        }
        if (preg_match('/CRS-07-00[9]|CRS-07-01[0-9]|CRS-07-02[0-9]|CRS-07-03[0-9]|CRS-07-04[0-9]|CRS-07-05[0-4]/', $crs->source_map)) {
            $places[] = $this->place('Galilea', 'Region principal del ministerio publico de Jesus');
        }
        if (preg_match('/CRS-07-05[5-9]|CRS-07-06[0-9]|CRS-07-07[0-4]/', $crs->source_map)) {
            $places[] = $this->place('Judea', 'Region de transicion hacia los ultimos conflictos del ministerio');
        }
        if (preg_match('/CRS-07-07[5-9]|CRS-07-08[0-9]/', $crs->source_map)) {
            $places[] = $this->place('Jerusalen', 'Escenario de la pasion y resurreccion');
        }
        if ($crs->source_map === 'CRS-07-090') {
            $places[] = $this->place('Galilea', 'Marco del epilogo junto al mar');
        }

        return $places;
    }

    private function actsPeople(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $people = [];

        if (preg_match('/CRS-ACT-00[1-9]|CRS-ACT-01[0-2]|CRS-NT-00[1-6]|CRS-NT-008|CRS-NT-010/', $map)) {
            $people[] = $this->person('Pedro', 'Lider apostolico en Jerusalen y Judea');
        }
        if (! preg_match('/CRS-ACT-00[1-8]|CRS-NT-00[1-6]/', $map)) {
            $people[] = $this->person('Pablo', 'Figura central de la mision a los gentiles');
        }
        if (preg_match('/CRS-NT-011|CRS-NT-012|CRS-NT-013|CRS-NT-014/', $map)) {
            $people[] = $this->person('Bernabe', 'Companero de Pablo en la primera mision y el concilio');
        }
        if (preg_match('/CRS-NT-015|CRS-NT-016|CRS-NT-017|CRS-NT-020|CRS-NT-021/', $map)) {
            $people[] = $this->person('Silas', 'Companero de Pablo en la segunda mision');
        }
        if (preg_match('/CRS-NT-015|CRS-NT-038|CRS-NT-040/', $map)) {
            $people[] = $this->person('Timoteo', 'Colaborador pastoral de Pablo');
        }
        if ($map === 'CRS-NT-039') {
            $people[] = $this->person('Tito', 'Colaborador pastoral de Pablo');
        }
        if ($map === 'CRS-NT-034') {
            $people[] = $this->person('Filemon', 'Destinatario de una carta personal de Pablo');
        }

        return $people;
    }

    private function ntMissionPlaces(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $title = $this->normalize($crs->title_es);
        $places = [];

        if (preg_match('/CRS-ACT-00[1-9]|CRS-ACT-01[0-2]|CRS-NT-00[1-5]|CRS-NT-010|CRS-NT-014|CRS-NT-030/', $map)) {
            $places[] = $this->place('Jerusalen', 'Punto de partida y centro de decision de la iglesia primitiva');
        }

        $placeTerms = [
            'Samaria', 'Damasco', 'Antioquia', 'Chipre', 'Iconio', 'Listra', 'Derbe', 'Filipos',
            'Macedonia', 'Tesalonica', 'Berea', 'Atenas', 'Corinto', 'Efeso', 'Cesarea',
            'Troas', 'Mileto', 'Malta', 'Roma',
        ];

        foreach ($placeTerms as $name) {
            if ($this->containsTerm($title, $name)) {
                $places[] = $this->place($name, 'Escenario nombrado en el titulo editorial');
            }
        }

        $byMap = [
            'CRS-NT-011' => ['Chipre', 'Antioquia'],
            'CRS-NT-012' => ['Iconio', 'Listra', 'Derbe'],
            'CRS-NT-013' => ['Galacia'],
            'CRS-NT-015' => ['Antioquia'],
            'CRS-NT-016' => ['Filipos', 'Macedonia'],
            'CRS-NT-017' => ['Tesalonica', 'Berea'],
            'CRS-NT-018' => ['Atenas'],
            'CRS-NT-019' => ['Corinto'],
            'CRS-NT-020' => ['Tesalonica', 'Corinto'],
            'CRS-NT-021' => ['Tesalonica', 'Corinto'],
            'CRS-NT-022' => ['Efeso', 'Cesarea'],
            'CRS-NT-023' => ['Efeso'],
            'CRS-NT-024' => ['Corinto', 'Efeso'],
            'CRS-NT-025' => ['Efeso', 'Macedonia'],
            'CRS-NT-026' => ['Corinto', 'Macedonia'],
            'CRS-NT-027' => ['Grecia'],
            'CRS-NT-028' => ['Roma', 'Corinto'],
            'CRS-NT-029' => ['Troas', 'Mileto'],
            'CRS-NT-031' => ['Cesarea'],
            'CRS-NT-032' => ['Malta'],
            'CRS-NT-033' => ['Roma'],
            'CRS-NT-034' => ['Roma'],
            'CRS-NT-035' => ['Colosas', 'Roma'],
            'CRS-NT-036' => ['Efeso', 'Roma'],
            'CRS-NT-037' => ['Filipos', 'Roma'],
            'CRS-NT-038' => ['Efeso'],
            'CRS-NT-039' => ['Creta'],
            'CRS-NT-040' => ['Roma'],
            'CRS-PAUL-1COR-A' => ['Corinto', 'Efeso'],
            'CRS-PAUL-1COR-B' => ['Corinto', 'Efeso'],
            'CRS-PAUL-1COR-C' => ['Corinto', 'Efeso'],
            'CRS-PAUL-1TES' => ['Tesalonica', 'Corinto'],
            'CRS-PAUL-2TES' => ['Tesalonica', 'Corinto'],
            'CRS-PAUL-GAL' => ['Galacia'],
        ];

        foreach ($byMap[$map] ?? [] as $name) {
            $places[] = $this->place($name, 'Lugar probable asociado con el viaje o la carta');
        }

        return $places;
    }

    private function generalLettersPeople(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $people = [];

        if (str_starts_with($map, 'CRS-REV-') || str_starts_with($map, 'CRS-GLET-HEB-')) {
            $people[] = $this->person('Jesus', 'Figura central de la revelacion y cumplimiento del pacto');
        }
        if ($map === 'CRS-GLET-STG') {
            $people[] = $this->person('Jacobo', 'Autor tradicionalmente asociado con la carta de Santiago');
        }
        if (in_array($map, ['CRS-GLET-1PE', 'CRS-GLET-2PE'], true)) {
            $people[] = $this->person('Pedro', 'Autor apostolico asociado con la carta');
        }
        if (in_array($map, ['CRS-GLET-1JN', 'CRS-GLET-2JN', 'CRS-GLET-3JN'], true) || str_starts_with($map, 'CRS-REV-')) {
            $people[] = $this->person('Juan', 'Autor apostolico o vidente asociado con este bloque');
        }
        if ($map === 'CRS-GLET-JDS') {
            $people[] = $this->person('Judas', 'Autor de la exhortacion a defender la fe');
        }

        return $people;
    }

    private function generalLettersPlaces(ChronologicalReadingSet $crs): array
    {
        $map = $crs->source_map;
        $places = [];

        if (str_starts_with($map, 'CRS-REV-')) {
            $places[] = $this->place('Patmos', 'Lugar tradicional de la vision de Apocalipsis');
        }
        if ($map === 'CRS-REV-008') {
            $places[] = $this->place('Babilonia', 'Imagen simbolica del poder imperial en Apocalipsis');
        }
        if ($map === 'CRS-GLET-1PE') {
            $places[] = $this->place('Babilonia', 'Lugar mencionado en el cierre de 1 Pedro, probablemente con valor simbolico');
        }

        return $places;
    }

    private function connectionsFor(ChronologicalReadingSet $crs): array
    {
        $connections = [];

        foreach ($crs->compareGroups as $group) {
            $connections[] = [
                'type' => 'compare_group',
                'title' => $group->title_es,
                'subtitle' => $group->relation_level,
                'compare_group_id' => $group->id,
            ];
        }

        $blockIds = $crs->blocks->pluck('id')->all();
        if (empty($blockIds)) {
            return $connections;
        }

        $links = ParallelLink::where('approved', true)
            ->where(function ($q) use ($blockIds) {
                $q->whereIn('source_block_id', $blockIds)
                    ->orWhereIn('target_block_id', $blockIds);
            })
            ->with(['sourceBlock.crs', 'targetBlock.crs'])
            ->get();

        foreach ($links as $link) {
            $isSource = in_array($link->source_block_id, $blockIds, true);
            $other = $isSource ? $link->targetBlock?->crs : $link->sourceBlock?->crs;
            if (! $other) {
                continue;
            }

            $connections[] = [
                'type' => strtolower($link->relation_type),
                'title' => $other->title_es,
                'subtitle' => $link->evidence_note,
                'source_map' => $other->source_map,
                'confidence' => $link->confidence,
            ];
        }

        return $connections;
    }

    private function normalize(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return mb_strtolower($value);
    }

    private function searchText(ChronologicalReadingSet $crs): string
    {
        return $this->normalize($crs->title_es . ' ' . $crs->source_map);
    }

    private function containsTerm(string $normalizedText, string $term): bool
    {
        $needle = preg_quote($this->normalize($term), '/');

        return preg_match('/(^|[^a-z0-9])' . $needle . '([^a-z0-9]|$)/i', $normalizedText) === 1;
    }

    private function hasReferencePrefix(ChronologicalReadingSet $crs, string $book): bool
    {
        $needle = $this->normalize($book);

        foreach ($crs->blocks as $block) {
            if ($this->normalize((string) $block->book) === $needle) {
                return true;
            }

            $reference = $this->normalize((string) $block->display_reference);
            if (str_starts_with($reference, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function person(string $name, string $role): array
    {
        return [
            'name' => $this->restoreSpanishName($name),
            'role' => $role,
        ];
    }

    private function place(string $name, string $note): array
    {
        return [
            'name' => $this->restoreSpanishName($name),
            'certainty_level' => 'probable',
            'note' => $note,
        ];
    }

    private function restoreSpanishName(string $value): string
    {
        return strtr($value, [
            'Adan' => 'Adan',
            'Noe' => 'Noe',
            'Jose' => 'Jose',
            'Moises' => 'Moises',
            'Aaron' => 'Aaron',
            'Josue' => 'Josue',
            'Gedeon' => 'Gedeon',
            'Sanson' => 'Sanson',
            'Jefte' => 'Jefte',
            'Salomon' => 'Salomon',
            'Elias' => 'Elias',
            'Isaias' => 'Isaias',
            'Faraon' => 'Faraon',
            'Jesus' => 'Jesus',
            'Jerusalen' => 'Jerusalen',
            'Belen' => 'Belen',
            'Jerico' => 'Jerico',
            'Efeso' => 'Efeso',
        ]);
    }

    private function uniqueByName(array $items): array
    {
        $seen = [];

        return array_values(array_filter($items, function ($item) use (&$seen) {
            $key = mb_strtolower($item['name'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                return false;
            }

            $seen[$key] = true;
            return true;
        }));
    }
}
