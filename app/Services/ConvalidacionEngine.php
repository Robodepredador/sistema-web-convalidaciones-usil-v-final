<?php

namespace App\Services;

use App\Models\Carrera;
use App\Models\MallaCurricular;

/**
 * Motor de convalidación (portado del pipeline standalone en Python).
 *
 * Reutiliza el plan de estudios que ya vive en la base de datos
 * (mallas_curriculares → ciclos → cursos_usil) para construir el pool de
 * cursos USIL destino y proponer un mapeo 1‑a‑1 por similitud, sin depender de
 * ningún servicio externo.
 *
 * La sugerencia semántica con IA vive en {@see IAConvalidacionService}.
 */
class ConvalidacionEngine
{
    public const NO_CONVALIDAR = '— No convalidar —';

    /** Normaliza texto: minúsculas, sin acentos, solo alfanumérico con espacios simples. */
    public function normaliza(?string $texto): string
    {
        if (! $texto) {
            return '';
        }
        $t = mb_strtolower($texto, 'UTF-8');
        // Quita acentos/diacríticos. OJO: Normalizer::isNormalized() sin argumento verifica
        // la forma NFC (la habitual en texto tipiado), así que un "é" ya viene compuesto y
        // nunca se descompone si se usa esa condición — hay que normalizar siempre a FORM_D.
        $t = preg_replace('/\p{Mn}/u', '', \Normalizer::normalize($t, \Normalizer::FORM_D));
        // Todo lo no alfanumérico → espacio.
        $t = preg_replace('/[^a-z0-9áéíóúñ]+/iu', ' ', $t);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    /** Siglas conocidas que se mantienen en mayúscula. */
    private const SIGLAS = [
        'USIL', 'ISIL', 'TI', 'TIC', 'TICS', 'POO', 'SQL', 'UML', 'ETL', 'IOT', 'API',
        'UX', 'UI', 'ERP', 'CRM', 'SIG', 'BI', 'IA', 'ML', 'PMI', 'ITIL', 'PMBOK', 'SO',
    ];

    private const NUMEROS_ROMANOS = ['i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x', 'xi', 'xii'];

    /** Correcciones de acentuación frecuentes en nombres de cursos (sin acento → con acento). */
    private const ACENTOS = [
        'matematica' => 'matemática', 'matematicas' => 'matemáticas', 'matematico' => 'matemático',
        'calculo' => 'cálculo', 'algebra' => 'álgebra', 'aritmetica' => 'aritmética',
        'geometria' => 'geometría', 'trigonometria' => 'trigonometría', 'estadistica' => 'estadística',
        'fisica' => 'física', 'fisico' => 'físico', 'quimica' => 'química', 'quimico' => 'químico',
        'etica' => 'ética', 'ingles' => 'inglés', 'economia' => 'economía', 'teoria' => 'teoría',
        'teorias' => 'teorías', 'analisis' => 'análisis', 'sintesis' => 'síntesis',
        'informatica' => 'informática', 'informatico' => 'informático', 'telematica' => 'telemática',
        'practica' => 'práctica', 'practicas' => 'prácticas', 'practico' => 'práctico',
        'tecnica' => 'técnica', 'tecnicas' => 'técnicas', 'tecnico' => 'técnico',
        'teorico' => 'teórico', 'basica' => 'básica', 'basico' => 'básico',
        'publica' => 'pública', 'publico' => 'público', 'logica' => 'lógica',
        'electronica' => 'electrónica', 'electronico' => 'electrónico', 'mecanica' => 'mecánica',
        'dinamica' => 'dinámica', 'estatica' => 'estática', 'grafica' => 'gráfica', 'grafico' => 'gráfico',
        'graficos' => 'gráficos', 'numerico' => 'numérico', 'numerica' => 'numérica',
        'semantica' => 'semántica', 'automatica' => 'automática', 'robotica' => 'robótica',
        'cientifica' => 'científica', 'cientifico' => 'científico', 'energia' => 'energía',
        'ecologia' => 'ecología', 'auditoria' => 'auditoría', 'categoria' => 'categoría',
        'tecnologico' => 'tecnológico', 'tecnologicos' => 'tecnológicos', 'tecnologica' => 'tecnológica',
    ];

    /**
     * Formatea el nombre de un curso a estilo "oración" en español:
     * primera palabra en mayúscula, conectores en minúscula, siglas y números
     * romanos en mayúscula, con corrección de acentos frecuentes.
     * Ej.: "LENGUAJE Y COMUNICACION II" → "Lenguaje y comunicación II".
     */
    public function titulo(?string $texto): string
    {
        if (! $texto) {
            return '';
        }

        $palabras = preg_split('/\s+/', trim($texto));
        $salida = [];

        foreach ($palabras as $i => $palabra) {
            $lower = mb_strtolower($palabra, 'UTF-8');
            $base = $this->quitarAcentos($lower);   // solo para comparar/buscar

            // Números romanos → mayúscula.
            if (in_array($base, self::NUMEROS_ROMANOS, true)) {
                $salida[] = mb_strtoupper($base, 'UTF-8');

                continue;
            }
            // Siglas conocidas → mayúscula.
            if (in_array(mb_strtoupper($base, 'UTF-8'), self::SIGLAS, true)) {
                $salida[] = mb_strtoupper($base, 'UTF-8');

                continue;
            }

            // Corrección de acentos; si no aplica, se conserva el original (preserva la ñ y tildes ya presentes).
            $corr = $this->restaurarAcentos($base);
            $palabraFinal = ($corr === $base) ? $lower : $corr;

            // Estilo oración: solo la primera palabra en mayúscula; el resto en minúscula.
            if ($i === 0) {
                $palabraFinal = mb_strtoupper(mb_substr($palabraFinal, 0, 1, 'UTF-8'), 'UTF-8')
                    .mb_substr($palabraFinal, 1, null, 'UTF-8');
            }

            $salida[] = $palabraFinal;
        }

        return implode(' ', $salida);
    }

    private function quitarAcentos(string $t): string
    {
        $d = \Normalizer::normalize($t, \Normalizer::FORM_D);

        return preg_replace('/\p{Mn}/u', '', $d);
    }

    /** Restaura acentos frecuentes (mapa curado + sufijos comunes). */
    private function restaurarAcentos(string $w): string
    {
        if (isset(self::ACENTOS[$w])) {
            return self::ACENTOS[$w];
        }
        // Sufijos regulares del español académico.
        if (str_ends_with($w, 'cion')) {
            return substr($w, 0, -4).'ción';
        }
        if (str_ends_with($w, 'siones')) {
            return substr($w, 0, -6).'siones';
        }
        if (str_ends_with($w, 'sion')) {
            return substr($w, 0, -4).'sión';
        }
        if (str_ends_with($w, 'logia')) {
            return substr($w, 0, -5).'logía';
        }
        if (str_ends_with($w, 'grafia')) {
            return substr($w, 0, -6).'grafía';
        }
        if (str_ends_with($w, 'metria')) {
            return substr($w, 0, -6).'metría';
        }

        return $w;
    }

    /**
     * Malla activa (con plan de estudios) de una carrera USIL.
     */
    /**
     * Parecido entre dos nombres de curso, de 0 a 1.
     *
     * NO es IA: es `similar_text()` de PHP sobre los nombres normalizados, todo
     * dentro del proceso y sin salir a ningún servicio. Se retiró por error al
     * desmontar el motor de IA y hubo que devolverla: la usa el histórico de
     * equivalencias (HistorialEquivalenciasService) para agrupar las variantes
     * de escritura de un mismo curso —«Matemática I» y «Matematica 1»— cuando
     * cuenta con qué frecuencia se convalidó cada cosa.
     */
    public function similitud(string $a, string $b): float
    {
        $a = $this->normaliza($a);
        $b = $this->normaliza($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        similar_text($a, $b, $pct);

        return $pct / 100;
    }

    public function mallaDeCarrera(int $carreraId): ?MallaCurricular
    {
        return MallaCurricular::where('carrera_id', $carreraId)
            ->orderByDesc('activa')
            ->orderByDesc('anio')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Pool de cursos USIL destino, a partir del plan de estudios en BD.
     *
     * @return array<int,array{id:int,codigo:string,label:string,curso:string,ciclo:int|null,creditos:float,es_electivo:bool,silabo:string}>
     */
    public function poolCursosUsil(int $carreraId): array
    {
        $malla = $this->mallaDeCarrera($carreraId);
        if (! $malla) {
            return [];
        }

        $malla->load(['ciclos.cursos']);
        $items = [];
        $vistos = [];

        foreach ($malla->ciclos as $ciclo) {
            foreach ($ciclo->cursos as $curso) {
                $nombre = $curso->nombre;
                // Se omiten los marcadores "Electivo N" (placeholders sin contenido).
                if (preg_match('/^electivo/i', trim($nombre))) {
                    continue;
                }
                $label = $nombre;
                if (isset($vistos[$label])) {
                    $label = "{$nombre} (c{$ciclo->numero})";
                }
                if (isset($vistos[$label])) {
                    continue;
                }
                $vistos[$label] = true;
                $items[] = [
                    'id' => $curso->id,
                    'codigo' => $curso->codigo,
                    'label' => $label,
                    'curso' => $nombre,
                    'ciclo' => $ciclo->numero,
                    'creditos' => (float) $curso->creditos,
                    'es_electivo' => (bool) $curso->es_electivo,
                    'silabo' => $curso->silabo_texto ?: $nombre,
                ];
            }
        }

        return $items;
    }
}
