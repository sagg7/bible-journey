<?php

namespace Database\Seeders;

use App\Models\BiblicalBook;
use App\Models\Character;
use App\Models\HistoricalEvent;
use App\Models\Location;
use App\Models\Passage;
use App\Models\PsalmConnection;
use App\Models\Route;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ruta semilla "Vida de David" — los 10 eventos de la sección 24 del PRD,
 * con contenido bilingüe, personajes, pasajes y conexiones de Salmos con nivel de certeza.
 *
 * Este contenido es un punto de partida demostrable; la versión final se edita y revisa
 * teológicamente desde el panel admin (Filament).
 */
class DavidRouteSeeder extends Seeder
{
    private array $books = [];

    private array $locations = [];

    public function run(): void
    {
        foreach (BiblicalBook::all() as $book) {
            $this->books[$book->slug] = $book;
        }

        $this->locations = $this->seedLocations();
        $characters = $this->seedCharacters();

        $route = Route::updateOrCreate(
            ['slug' => 'vida-de-david'],
            ['is_premium' => false, 'sort_order' => 1]
        );
        $route->translations()->updateOrCreate(['locale' => 'es'], [
            'title' => 'La vida de David',
            'description' => 'Sigue la historia de David en orden cronológico: del pastor ungido al rey, con los Salmos ubicados en su momento probable.',
            'review_status' => 'approved',
        ]);
        $route->translations()->updateOrCreate(['locale' => 'en'], [
            'title' => 'The Life of David',
            'description' => "Follow David's story in chronological order: from anointed shepherd to king, with the Psalms placed in their likely moment.",
            'review_status' => 'approved',
        ]);

        foreach ($this->events() as $i => $data) {
            $this->seedEvent($route, $characters, $data, $i + 1);
        }
    }

    private function seedLocations(): array
    {
        $rows = [
            ['belen', 'Belén / Bethlehem', 31.7054, 35.2024, 'alta', 'Belén', 'Bethlehem'],
            ['valle-de-ela', 'Valle de Elá', 31.6900, 34.9600, 'probable', 'Valle de Elá', 'Valley of Elah'],
            ['gabaa', 'Guibeá (Tell el-Ful)', 31.8230, 35.2300, 'probable', 'Guibeá de Saúl', 'Gibeah of Saul'],
            ['nob', 'Nob (cerca de Jerusalén)', 31.7900, 35.2300, 'debatida', 'Nob', 'Nob'],
            ['gat', 'Gat (Tell es-Safi)', 31.6997, 34.8470, 'probable', 'Gat', 'Gath'],
            ['adulam', 'Cueva de Adulam', 31.6500, 34.9600, 'probable', 'Cueva de Adulam', 'Cave of Adullam'],
            ['en-gadi', 'En-gadi', 31.4619, 35.3925, 'alta', 'En-gadi', 'En Gedi'],
            ['siclag', 'Siclag', 31.3000, 34.6000, 'debatida', 'Siclag', 'Ziklag'],
        ];

        $out = [];
        foreach ($rows as [$slug, $modern, $lat, $lng, $cert, $es, $en]) {
            $loc = Location::updateOrCreate(['slug' => $slug], [
                'modern_equivalent' => $modern,
                'latitude' => $lat,
                'longitude' => $lng,
                'certainty_level' => $cert,
            ]);
            $loc->translations()->updateOrCreate(['locale' => 'es'], ['name' => $es, 'review_status' => 'approved']);
            $loc->translations()->updateOrCreate(['locale' => 'en'], ['name' => $en, 'review_status' => 'approved']);
            $out[$slug] = $loc;
        }

        return $out;
    }

    private function seedCharacters(): array
    {
        $rows = [
            ['david', 'David', 'Pastor, músico, rey de Israel', 'David', 'Shepherd, musician, king of Israel'],
            ['saul', 'Saúl', 'Primer rey de Israel', 'Saul', 'First king of Israel'],
            ['samuel', 'Samuel', 'Profeta y juez', 'Samuel', 'Prophet and judge'],
            ['jonatan', 'Jonatán', 'Hijo de Saúl, amigo de David', 'Jonathan', "Saul's son, David's friend"],
            ['goliat', 'Goliat', 'Campeón filisteo de Gat', 'Goliath', 'Philistine champion of Gath'],
            ['mical', 'Mical', 'Hija de Saúl, esposa de David', 'Michal', "Saul's daughter, David's wife"],
            ['doeg', 'Doeg edomita', 'Siervo de Saúl', 'Doeg the Edomite', "Saul's servant"],
            ['aquis', 'Aquis', 'Rey filisteo de Gat', 'Achish', 'Philistine king of Gath'],
        ];

        $out = [];
        foreach ($rows as [$slug, $es, $roleEs, $en, $roleEn]) {
            $c = Character::updateOrCreate(['slug' => $slug], []);
            $c->translations()->updateOrCreate(['locale' => 'es'], ['name' => $es, 'role' => $roleEs, 'review_status' => 'approved']);
            $c->translations()->updateOrCreate(['locale' => 'en'], ['name' => $en, 'role' => $roleEn, 'review_status' => 'approved']);
            $out[$slug] = $c;
        }

        return $out;
    }

    private function events(): array
    {
        return [
            [
                'slug' => 'samuel-unge-a-david', 'location' => 'belen',
                'date' => 'c. 1025 a.C.', 'certainty' => 'probable',
                'es' => ['Samuel unge a David', 'Dios envía a Samuel a Belén para ungir al menor de los hijos de Isaí. Mientras los demás esperan al hijo de mejor apariencia, Dios elige a David, el pastor.', 'Samuel arriesga su vida ungiendo a un rey mientras Saúl aún reina. La elección de David muestra el principio de que Dios mira el corazón, no la apariencia.'],
                'en' => ['Samuel anoints David', 'God sends Samuel to Bethlehem to anoint the youngest son of Jesse. While the others expect the most impressive son, God chooses David, the shepherd.', 'Samuel risks his life anointing a king while Saul still reigns. David\'s election shows the principle that God looks at the heart, not appearance.'],
                'passages' => [['1-samuel', '1 Samuel 16:1-13', 16, 1, 16, 13, 'primary', 'alta']],
                'characters' => [['samuel', 'activo'], ['david', 'vivo'], ['saul', 'fuera_de_escena']],
                'psalms' => [],
            ],
            [
                'slug' => 'david-y-goliat', 'location' => 'valle-de-ela',
                'date' => 'c. 1020 a.C.', 'certainty' => 'alta',
                'es' => ['David y Goliat', 'En el valle de Elá, el joven David enfrenta al gigante filisteo Goliat confiando en el Dios de Israel, y lo derriba con una honda.', 'El relato contrasta el miedo de un ejército con la fe de un joven. Es la entrada de David a la escena pública.'],
                'en' => ['David and Goliath', 'In the Valley of Elah, young David faces the Philistine giant Goliath trusting in the God of Israel, and brings him down with a sling.', "The account contrasts an army's fear with a young man's faith. It is David's entrance onto the public stage."],
                'passages' => [['1-samuel', '1 Samuel 17', 17, 1, 17, 58, 'primary', 'alta']],
                'characters' => [['david', 'activo'], ['goliat', 'muerto'], ['saul', 'vivo']],
                'psalms' => [],
            ],
            [
                'slug' => 'david-al-servicio-de-saul', 'location' => 'gabaa',
                'date' => 'c. 1020 a.C.', 'certainty' => 'probable',
                'es' => ['David entra al servicio de Saúl', 'David toca el arpa para calmar a Saúl, atormentado por un espíritu, y se gana un lugar en la corte. Comienza una relación tensa que marcará años de su vida.', 'El orden de los eventos entre 1 Samuel 16 y 17 es discutido; aquí se sigue el arreglo cronológico tradicional.'],
                'en' => ['David enters Saul\'s service', "David plays the harp to soothe Saul, tormented by a spirit, and earns a place at court. A tense relationship begins that will shape years of his life.", 'The order of events between 1 Samuel 16 and 17 is debated; here the traditional chronological arrangement is followed.'],
                'passages' => [['1-samuel', '1 Samuel 16:14-23', 16, 14, 16, 23, 'primary', 'alta'], ['1-samuel', '1 Samuel 18:1-5', 18, 1, 18, 5, 'background', 'alta']],
                'characters' => [['david', 'activo'], ['saul', 'vivo'], ['jonatan', 'vivo']],
                'psalms' => [],
            ],
            [
                'slug' => 'david-y-jonatan', 'location' => 'gabaa',
                'date' => 'c. 1018 a.C.', 'certainty' => 'probable',
                'es' => ['David y Jonatán', 'Jonatán, heredero al trono, hace un pacto de amistad y lealtad con David, incluso sabiendo que David podría reinar en su lugar.', 'La amistad de Jonatán es un contrapeso a los celos de Saúl y un ejemplo de lealtad sacrificial.'],
                'en' => ['David and Jonathan', "Jonathan, heir to the throne, makes a covenant of friendship and loyalty with David, even knowing David may reign in his place.", "Jonathan's friendship is a counterweight to Saul's jealousy and an example of sacrificial loyalty."],
                'passages' => [['1-samuel', '1 Samuel 18:1-4', 18, 1, 18, 4, 'primary', 'alta'], ['1-samuel', '1 Samuel 20', 20, 1, 20, 42, 'parallel', 'alta']],
                'characters' => [['jonatan', 'activo'], ['david', 'activo'], ['saul', 'vivo']],
                'psalms' => [],
            ],
            [
                'slug' => 'david-huye', 'location' => 'gabaa',
                'date' => 'c. 1016 a.C.', 'certainty' => 'probable',
                'es' => ['David huye de Saúl', 'Saúl envía hombres a vigilar la casa de David para matarlo; Mical lo ayuda a escapar por una ventana. Comienza la etapa de fugitivo.', 'Aquí inicia el periodo de huida que conecta con varios Salmos de lamento y confianza.'],
                'en' => ['David flees from Saul', 'Saul sends men to watch David\'s house to kill him; Michal helps him escape through a window. The fugitive stage begins.', 'This begins the flight period that connects with several psalms of lament and trust.'],
                'passages' => [['1-samuel', '1 Samuel 19:11-17', 19, 11, 19, 17, 'primary', 'alta']],
                'characters' => [['david', 'activo'], ['mical', 'vivo'], ['saul', 'vivo']],
                'psalms' => [
                    ['Salmo 59', 'salmos', 'Salmo 59', 59, 'probable',
                        ['El encabezado del Salmo 59 lo asocia a cuando Saúl envió a vigilar la casa de David para matarlo, lo que coincide con este episodio.', 'El encabezado es antiguo pero algunos eruditos lo consideran editorial; tómese como probable, no absoluto.'],
                        ['The heading of Psalm 59 ties it to when Saul sent men to watch David\'s house to kill him, matching this episode.', 'The heading is ancient but some scholars consider it editorial; treat as probable, not absolute.']],
                ],
            ],
            [
                'slug' => 'david-en-nob', 'location' => 'nob',
                'date' => 'c. 1016 a.C.', 'certainty' => 'probable',
                'es' => ['David en Nob', 'Huyendo, David llega a Nob, donde el sacerdote Ajimelec le da el pan sagrado y la espada de Goliat. Doeg el edomita lo observa, con consecuencias trágicas después.', 'Jesús cita este episodio (Mateo 12) al hablar del sábado y la misericordia.'],
                'en' => ['David at Nob', "Fleeing, David reaches Nob, where the priest Ahimelech gives him the holy bread and Goliath's sword. Doeg the Edomite watches, with tragic consequences later.", 'Jesus cites this episode (Matthew 12) when speaking of the Sabbath and mercy.'],
                'passages' => [['1-samuel', '1 Samuel 21:1-9', 21, 1, 21, 9, 'primary', 'alta']],
                'characters' => [['david', 'activo'], ['doeg', 'activo'], ['saul', 'vivo']],
                'psalms' => [
                    ['Salmo 52', 'salmos', 'Salmo 52', 52, 'alta',
                        ['El encabezado del Salmo 52 nombra explícitamente a Doeg el edomita y su delación a Saúl, vinculándolo directamente a los hechos de Nob.', null],
                        ['The heading of Psalm 52 explicitly names Doeg the Edomite and his report to Saul, tying it directly to the events at Nob.', null]],
                ],
            ],
            [
                'slug' => 'david-en-gat', 'location' => 'gat',
                'date' => 'c. 1016 a.C.', 'certainty' => 'probable',
                'es' => ['David en Gat', 'David busca refugio en Gat, ciudad filistea, pero al ser reconocido finge estar loco ante el rey Aquis para salvar su vida.', 'Es uno de los momentos más humanos de David: el héroe de Goliat ahora teme y disimula.'],
                'en' => ['David at Gath', 'David seeks refuge in Gath, a Philistine city, but when recognized he feigns madness before king Achish to save his life.', "It is one of David's most human moments: Goliath's hero now fears and pretends."],
                'passages' => [['1-samuel', '1 Samuel 21:10-15', 21, 10, 21, 15, 'primary', 'alta']],
                'characters' => [['david', 'activo'], ['aquis', 'activo']],
                'psalms' => [
                    ['Salmo 34', 'salmos', 'Salmo 34', 34, 'alta',
                        ['El encabezado del Salmo 34 dice que David lo compuso "cuando mudó su semblante delante de Abimelec", el episodio de su locura fingida en Gat.', 'El encabezado usa "Abimelec" (posible título dinástico filisteo) en vez de "Aquis"; la asociación es fuerte pero el nombre genera discusión.'],
                        ['The heading of Psalm 34 says David composed it "when he changed his behavior before Abimelech," the episode of his feigned madness at Gath.', 'The heading uses "Abimelech" (a possible Philistine dynastic title) instead of "Achish"; the link is strong but the name is discussed.']],
                    ['Salmo 56', 'salmos', 'Salmo 56', 56, 'probable',
                        ['El encabezado del Salmo 56 lo sitúa "cuando los filisteos lo prendieron en Gat", coincidiendo con este momento de peligro.', 'Probable por el encabezado, aunque algunos lo ven como una composición posterior reflexiva.'],
                        ['The heading of Psalm 56 places it "when the Philistines seized him in Gath," matching this moment of danger.', 'Probable from the heading, though some see it as a later reflective composition.']],
                ],
            ],
            [
                'slug' => 'cueva-de-adulam', 'location' => 'adulam',
                'date' => 'c. 1015 a.C.', 'certainty' => 'probable',
                'es' => ['La cueva de Adulam', 'David se refugia en la cueva de Adulam, y a él se unen su familia y unos cuatrocientos hombres en apuros, descontentos o endeudados. Nace su banda.', 'De fugitivo solitario, David pasa a líder de un grupo marginal que luego será el núcleo de su ejército.'],
                'en' => ['The cave of Adullam', 'David takes refuge in the cave of Adullam, and his family and about four hundred distressed, discontented or indebted men join him. His band is born.', 'From lone fugitive, David becomes leader of a marginal group that will later be the core of his army.'],
                'passages' => [['1-samuel', '1 Samuel 22:1-2', 22, 1, 22, 2, 'primary', 'alta']],
                'characters' => [['david', 'activo']],
                'psalms' => [
                    ['Salmo 57', 'salmos', 'Salmo 57', 57, 'probable',
                        ['El encabezado del Salmo 57 lo asocia a cuando David huía de Saúl "en la cueva", lo que la tradición vincula con Adulam o En-gadi.', 'El encabezado dice "en la cueva" sin nombrarla; podría ser Adulam o En-gadi. Asociación probable.'],
                        ['The heading of Psalm 57 ties it to when David fled from Saul "in the cave," which tradition links with Adullam or En Gedi.', 'The heading says "in the cave" without naming it; it could be Adullam or En Gedi. Probable association.']],
                    ['Salmo 142', 'salmos', 'Salmo 142', 142, 'debatida',
                        ['El encabezado del Salmo 142 también lo ubica "en la cueva", por lo que suele conectarse con este periodo de Adulam.', 'No se especifica la cueva ni el momento exacto; varias posturas son razonables.'],
                        ['The heading of Psalm 142 also places it "in the cave," so it is often connected with this Adullam period.', 'Neither the cave nor the exact moment is specified; several positions are reasonable.']],
                ],
            ],
            [
                'slug' => 'david-perdona-a-saul', 'location' => 'en-gadi',
                'date' => 'c. 1014 a.C.', 'certainty' => 'probable',
                'es' => ['David perdona a Saúl', 'En En-gadi, David tiene a Saúl a su merced en una cueva, pero solo corta el borde de su manto y se niega a matar al ungido del Señor.', 'El episodio define el carácter de David: rechaza tomar el trono por la fuerza.'],
                'en' => ['David spares Saul', "At En Gedi, David has Saul at his mercy in a cave, but only cuts the edge of his robe and refuses to kill the Lord's anointed.", "The episode defines David's character: he refuses to seize the throne by force."],
                'passages' => [['1-samuel', '1 Samuel 24', 24, 1, 24, 22, 'primary', 'alta']],
                'characters' => [['david', 'activo'], ['saul', 'vivo']],
                'psalms' => [],
            ],
            [
                'slug' => 'david-en-siclag', 'location' => 'siclag',
                'date' => 'c. 1012 a.C.', 'certainty' => 'debatida',
                'es' => ['David en Siclag', 'Cansado de huir, David se establece en territorio filisteo y recibe la ciudad de Siclag del rey Aquis, desde donde opera durante dieciséis meses.', 'Etapa ambigua: David vive entre filisteos manteniendo una doble vida política. La cronología exacta es discutida.'],
                'en' => ['David at Ziklag', 'Weary of fleeing, David settles in Philistine territory and receives the city of Ziklag from king Achish, operating from there for sixteen months.', 'An ambiguous stage: David lives among the Philistines keeping a political double life. The exact chronology is debated.'],
                'passages' => [['1-samuel', '1 Samuel 27', 27, 1, 27, 12, 'primary', 'alta']],
                'characters' => [['david', 'activo'], ['aquis', 'activo']],
                'psalms' => [],
            ],
        ];
    }

    private function seedEvent(Route $route, array $characters, array $data, int $order): void
    {
        $event = HistoricalEvent::updateOrCreate(['slug' => $data['slug']], [
            'location_id' => ($this->locations[$data['location']] ?? null)?->id,
            'approximate_date_start' => $data['date'],
            'date_confidence' => 'probable',
            'certainty_level' => $data['certainty'],
            'is_premium' => $order > 3, // los 3 primeros gratis; el resto premium (demo del freemium)
        ]);

        $event->translations()->updateOrCreate(['locale' => 'es'], [
            'title' => $data['es'][0], 'summary' => $data['es'][1], 'context' => $data['es'][2], 'review_status' => 'approved',
        ]);
        $event->translations()->updateOrCreate(['locale' => 'en'], [
            'title' => $data['en'][0], 'summary' => $data['en'][1], 'context' => $data['en'][2], 'review_status' => 'approved',
        ]);

        $route->events()->syncWithoutDetaching([$event->id => ['sort_order' => $order]]);

        // Pasajes
        foreach ($data['passages'] as $sort => [$bookSlug, $label, $cs, $vs, $ce, $ve, $type, $cert]) {
            $passage = $this->passage($bookSlug, $label, $cs, $vs, $ce, $ve);
            $event->eventPassages()->updateOrCreate(
                ['passage_id' => $passage->id],
                ['relationship_type' => $type, 'certainty_level' => $cert, 'sort_order' => $sort]
            );
        }

        // Personajes
        foreach ($data['characters'] as $sort => [$slug, $status]) {
            if (! isset($characters[$slug])) {
                continue;
            }
            $event->characters()->syncWithoutDetaching([
                $characters[$slug]->id => ['status_at_event' => $status, 'sort_order' => $sort],
            ]);
        }

        // Conexiones de Salmos
        foreach ($data['psalms'] as $sort => $p) {
            [$ref, $bookSlug, $label, $chapter, $cert, $es, $en] = $p;
            $passage = $this->passage($bookSlug, $label, $chapter, null, null, null);
            $conn = PsalmConnection::updateOrCreate(
                ['historical_event_id' => $event->id, 'psalm_reference' => $ref],
                ['passage_id' => $passage->id, 'certainty_level' => $cert, 'sort_order' => $sort]
            );
            $conn->translations()->updateOrCreate(['locale' => 'es'], [
                'reasoning' => $es[0], 'warning_note' => $es[1], 'review_status' => 'approved',
            ]);
            $conn->translations()->updateOrCreate(['locale' => 'en'], [
                'reasoning' => $en[0], 'warning_note' => $en[1], 'review_status' => 'approved',
            ]);
        }
    }

    private function passage(string $bookSlug, string $label, int $cs, ?int $vs, ?int $ce, ?int $ve): Passage
    {
        $book = $this->books[$bookSlug];

        return Passage::updateOrCreate(
            ['biblical_book_id' => $book->id, 'reference_label' => $label],
            ['chapter_start' => $cs, 'verse_start' => $vs, 'chapter_end' => $ce, 'verse_end' => $ve]
        );
    }
}
