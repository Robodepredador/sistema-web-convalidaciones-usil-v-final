<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Capa de IA para el pipeline de convalidación (portada del módulo standalone).
 *
 * Dos capacidades:
 *   1. extraerCursos()  — lee un récord/certificado (PDF, imagen, Word, Excel, texto)
 *                         y devuelve estudiante, institución y cursos aprobados/desaprobados.
 *   2. sugerirMapeo()   — mapea semánticamente cada curso de origen a un curso USIL (1‑a‑1).
 *
 * Proveedor por defecto: Google Gemini (gratis). Alternativa: OpenAI.
 * Sin API key configurada, {@see disponible()} devuelve false y la UI recae en el
 * mapeo por similitud de {@see ConvalidacionEngine}.
 */
class IAConvalidacionService
{
    private const IMG_MIME = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp',
    ];

    public function __construct(private ConvalidacionEngine $engine) {}

    /** Proveedor activo. Prioriza la configuración guardada en la UI sobre .env. */
    public function proveedor(): string
    {
        return Configuracion::get('ia_proveedor', config('services.ia.proveedor', 'gemini'));
    }

    /** ¿Hay clave configurada para el proveedor activo? */
    public function disponible(): bool
    {
        return $this->apiKey() !== '';
    }

    private function apiKey(): string
    {
        return $this->proveedor() === 'openai'
            ? (string) Configuracion::get('openai_api_key', (string) config('services.openai.key'))
            : (string) Configuracion::get('gemini_api_key', (string) config('services.gemini.key'));
    }

    private function modelo(): string
    {
        return $this->proveedor() === 'openai'
            ? (string) Configuracion::get('openai_model', config('services.openai.model', 'gpt-4o'))
            : (string) Configuracion::get('gemini_model', config('services.gemini.model', 'gemini-2.5-flash'));
    }

    // ------------------------------------------------------------------
    // 1) Extracción de cursos desde el documento
    // ------------------------------------------------------------------
    /**
     * @return array{estudiante:array,institucion:array,aprobados:array,desaprobados:array}
     */
    public function extraerCursos(string $contenido, string $nombreArchivo): array
    {
        if (! $this->disponible()) {
            throw new \RuntimeException('IA no configurada: define la API key en .env.');
        }

        $sistema = <<<'SYS'
Eres un asistente experto en lectura de récords y certificados académicos de universidades de
Perú e Hispanoamérica. Extrae los datos del estudiante, de la institución y TODOS los cursos del
documento, clasificándolos en APROBADOS o DESAPROBADOS según la nota o estado que aparezca.
Reglas:
- Aprobado: nota >= 11 (escala 0-20), nota >= 3.0 (0-5), letra A/B/C, o estado aprobado/AP/pasado.
- Desaprobado: nota < 11 (0-20), nota < 3.0 (0-5), letra D/F, estado desaprobado/reprobado/jalado/NSP.
- Si no puedes determinar el estado, NO incluyas el curso.
- Captura el nombre EXACTO del curso, la nota, el ciclo/semestre y los créditos. Deja vacío lo que no veas.
Responde SOLO con JSON válido, sin markdown, con la forma:
{"estudiante": {"nombre": "", "codigo": "", "carrera": ""},
 "institucion": {"universidad": "", "facultad": "", "fecha_emision": ""},
 "aprobados": [{"curso": "...", "nota": "...", "ciclo": "...", "creditos": "..."}],
 "desaprobados": [{"curso": "...", "nota": "...", "ciclo": "...", "creditos": "..."}]}
SYS;

        $bloque = $this->bloqueArchivo($nombreArchivo, $contenido);
        $texto = $this->generar($sistema, $bloque, 'Extrae todos los cursos del documento y devuelve el JSON pedido.');

        $data = $this->extraerJson($texto);

        return [
            'estudiante' => $data['estudiante'] ?? [],
            'institucion' => $data['institucion'] ?? [],
            'aprobados' => $data['aprobados'] ?? [],
            'desaprobados' => $data['desaprobados'] ?? [],
        ];
    }

    // ------------------------------------------------------------------
    // 1b) Extracción de Malla Oficial
    // ------------------------------------------------------------------
    /**
     * @return array{institucion:array,carrera:array,cursos:array}
     */
    public function extraerMallaOficial(string $contenido, string $nombreArchivo): array
    {
        if (! $this->disponible()) {
            throw new \RuntimeException('IA no configurada: define la API key en .env.');
        }

        $sistema = <<<'SYS'
Eres un asistente experto en lectura de Mallas Curriculares Oficiales y Planes de Estudio de universidades e institutos.
Extrae los datos de la institución, la carrera y TODOS los cursos del plan de estudios.
IMPORTANTE: Esto es un plan de estudios maestro, NO un récord de notas de un alumno. Por lo tanto, NO busques notas ni estado (aprobado/desaprobado).
Reglas:
- Captura el nombre EXACTO del curso, el código (si existe), el ciclo/semestre al que pertenece, y los créditos. Deja vacío lo que no veas.
- Ignora cursos extracurriculares o electivos genéricos si no tienen nombre o código claro, pero prefiere incluir todo lo que sea una materia con créditos.
Responde SOLO con JSON válido, sin markdown, con la forma:
{"institucion": {"nombre": "", "pais": ""},
 "carrera": {"nombre": "", "facultad": "", "grado": ""},
 "cursos": [{"codigo": "...", "nombre": "...", "ciclo": "...", "creditos": "..."}]}
SYS;

        $bloque = $this->bloqueArchivo($nombreArchivo, $contenido);
        $texto = $this->generar($sistema, $bloque, 'Extrae todos los cursos de esta malla oficial y devuelve el JSON pedido.');

        $data = $this->extraerJson($texto);

        return [
            'institucion' => $data['institucion'] ?? [],
            'carrera' => $data['carrera'] ?? [],
            'cursos' => $data['cursos'] ?? [],
        ];
    }

    // ------------------------------------------------------------------
    // 2) Mapeo semántico origen → USIL (1‑a‑1)
    // ------------------------------------------------------------------
    /**
     * @param  array<int,string>  $cursosOrigen
     * @param  array  $pool  salida de ConvalidacionEngine::poolCursosUsil()
     * @return array<string,string> nombreOrigen → label USIL (o NO_CONVALIDAR)
     */
    public function sugerirMapeo(string $carreraDestino, array $cursosOrigen, array $pool): array
    {
        if (! $this->disponible() || empty($cursosOrigen) || empty($pool)) {
            return [];
        }

        $labels = array_column($pool, 'label');
        $noConv = ConvalidacionEngine::NO_CONVALIDAR;
        $catalogo = implode("\n", array_map(fn ($l) => "- {$l}", $labels));

        $sistema = 'Eres un asistente experto en convalidación de cursos universitarios en Perú. '
            .'Te doy cursos APROBADOS por un estudiante y la lista oficial de cursos de la carrera USIL '
            ."'{$carreraDestino}'. Para cada curso de origen decide con QUÉ curso USIL se convalida según el "
            ."contenido temático real.\n\nREGLAS:\n"
            ."1. Convalidación 1 a 1: cada curso USIL puede usarse como destino COMO MÁXIMO UNA VEZ.\n"
            ."2. Si dos cursos de origen mapean al mismo USIL, asigna al más afín y deja el otro como \"{$noConv}\".\n"
            ."3. Inglés/English de cualquier nivel NUNCA se convalida.\n"
            .'4. Deportes, educación física, prácticas, ofimática, mantenimiento/reparación de equipos, '
            ."cultura artística, diseño gráfico, inserción laboral NUNCA se convalidan.\n"
            ."5. Si el contenido no coincide claramente (>70%), prefiere \"{$noConv}\".\n"
            ."Responde SOLO con JSON válido, sin markdown, con la forma:\n"
            .'{"convalidaciones": [{"origen": "...", "usil": "<label exacto del catálogo o '.$noConv.'>"}]}'
            ."\n\nCATÁLOGO USIL (usa el label EXACTO):\n".$catalogo;

        $prompt = "CURSOS DEL DOCUMENTO:\n".implode("\n", array_map(fn ($c) => "- {$c}", $cursosOrigen))
            ."\n\nDevuelve el JSON con la mejor convalidación para cada uno.";

        $texto = $this->generar($sistema, null, $prompt);
        $data = $this->extraerJson($texto);

        $labelsSet = array_flip($labels);
        $out = [];
        $usados = [];
        foreach ($data['convalidaciones'] ?? [] as $item) {
            $origen = trim($item['origen'] ?? '');
            $usil = trim($item['usil'] ?? '');
            if ($origen === '') {
                continue;
            }
            if ($this->engine->esNoConvalidable($origen) || $usil === $noConv
                || ! isset($labelsSet[$usil]) || isset($usados[$usil])) {
                $out[$origen] = $noConv;
            } else {
                $out[$origen] = $usil;
                $usados[$usil] = true;
            }
        }

        return $out;
    }

    /**
     * Prueba una configuración concreta sin guardarla.
     *
     * @return array{ok:bool, mensaje:string}
     */
    public function probar(string $proveedor, string $apiKey, string $modelo): array
    {
        if (trim($apiKey) === '') {
            return ['ok' => false, 'mensaje' => 'Falta la API key.'];
        }

        try {
            if ($proveedor === 'openai') {
                $resp = Http::withOptions(['verify' => $this->verify()])->withToken($apiKey)->timeout(20)
                    ->retry(2, 2000, $this->reintentarSi())
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $modelo ?: 'gpt-4o',
                        'max_tokens' => 5,
                        'messages' => [['role' => 'user', 'content' => 'ping']],
                    ]);
            } else {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.($modelo ?: 'gemini-2.5-flash').':generateContent';
                $resp = Http::withOptions(['verify' => $this->verify()])->timeout(20)->withQueryParameters(['key' => $apiKey])
                    ->retry(2, 2000, $this->reintentarSi())
                    ->post($url, ['contents' => [['parts' => [['text' => 'ping']]]]]);
            }

            if ($resp->successful()) {
                return ['ok' => true, 'mensaje' => 'Conexión correcta. La clave es válida.'];
            }

            $detalle = $resp->json('error.message') ?? "HTTP {$resp->status()}";

            return ['ok' => false, 'mensaje' => "La API respondió con error: {$detalle}"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo conectar: '.$e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Infraestructura de proveedores
    // ------------------------------------------------------------------

    /**
     * Certificado raíz para verificar TLS. Devuelve la ruta al CA bundle
     * incluido en el proyecto si existe; si no, deja la verificación por
     * defecto del sistema (true). Evita el cURL error 60 en local.
     */
    private function verify(): string|bool
    {
        $ruta = config('services.ia.ca_bundle');

        return ($ruta && is_file($ruta)) ? $ruta : true;
    }

    /**
     * Reintenta solo ante errores TRANSITORIOS del proveedor (saturación,
     * caídas momentáneas): 429 (rate limit), 500/502/503/529. No reintenta
     * ante errores definitivos como una API key inválida (400/401/403).
     */
    private function reintentarSi(): \Closure
    {
        return function ($exception) {
            if ($exception instanceof ConnectionException) {
                return true;
            }
            if ($exception instanceof RequestException) {
                return in_array($exception->response?->status(), [429, 500, 502, 503, 529], true);
            }

            return false;
        };
    }

    private function bloqueArchivo(string $nombre, string $contenido): ?array
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (isset(self::IMG_MIME[$ext])) {
            return ['mime' => self::IMG_MIME[$ext], 'data' => $contenido];
        }
        if ($ext === 'pdf') {
            return ['mime' => 'application/pdf', 'data' => $contenido];
        }
        // Texto plano / CSV: se manda embebido en la instrucción. RNF-09
        // (Ley N.º 29733): se retiran correos, documentos y teléfonos antes de
        // enviarlo al proveedor externo. Solo aplica a la rama de texto: en PDF
        // e imagen el contenido es binario y una regex lo corrompería.
        $limpio = Seudonimizador::limpiar($contenido);

        return ['texto' => "CONTENIDO DEL ARCHIVO (texto extraído):\n".mb_substr($limpio, 0, 20000)];
    }

    private function generar(string $sistema, ?array $bloque, string $instruccion): string
    {
        return $this->proveedor() === 'openai'
            ? $this->generarOpenAI($sistema, $bloque, $instruccion)
            : $this->generarGemini($sistema, $bloque, $instruccion);
    }

    private function generarGemini(string $sistema, ?array $bloque, string $instruccion): string
    {
        $partes = [];
        if ($bloque && isset($bloque['mime'])) {
            $partes[] = ['inline_data' => ['mime_type' => $bloque['mime'], 'data' => base64_encode($bloque['data'])]];
        } elseif ($bloque && isset($bloque['texto'])) {
            $partes[] = ['text' => $bloque['texto']];
        }
        $partes[] = ['text' => $instruccion];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->modelo()}:generateContent";
        $resp = Http::withOptions(['verify' => $this->verify()])
            ->timeout(120)
            ->retry(3, 3000, $this->reintentarSi())
            ->withQueryParameters(['key' => $this->apiKey()])
            ->post($url, [
                'system_instruction' => ['parts' => [['text' => $sistema]]],
                'contents' => [['parts' => $partes]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 8192,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        $resp->throw();

        return (string) $resp->json('candidates.0.content.parts.0.text', '');
    }

    private function generarOpenAI(string $sistema, ?array $bloque, string $instruccion): string
    {
        $contenidoUsuario = [];
        if ($bloque && isset($bloque['mime']) && str_starts_with($bloque['mime'], 'image/')) {
            $contenidoUsuario[] = ['type' => 'image_url', 'image_url' => [
                'url' => 'data:'.$bloque['mime'].';base64,'.base64_encode($bloque['data']),
            ]];
        } elseif ($bloque && isset($bloque['texto'])) {
            $contenidoUsuario[] = ['type' => 'text', 'text' => $bloque['texto']];
        }
        $contenidoUsuario[] = ['type' => 'text', 'text' => $instruccion];

        $resp = Http::withOptions(['verify' => $this->verify()])
            ->withToken($this->apiKey())
            ->timeout(120)
            ->retry(3, 3000, $this->reintentarSi())
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->modelo(),
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
                'messages' => [
                    ['role' => 'system', 'content' => $sistema],
                    ['role' => 'user', 'content' => $contenidoUsuario],
                ],
            ]);

        $resp->throw();

        return (string) $resp->json('choices.0.message.content', '');
    }

    /** Extrae el primer objeto JSON de una respuesta (tolera cercos ```json). */
    private function extraerJson(string $texto): array
    {
        $texto = trim(str_replace(['```json', '```'], '', $texto));
        $ini = strpos($texto, '{');
        $fin = strrpos($texto, '}');
        if ($ini !== false && $fin !== false) {
            $texto = substr($texto, $ini, $fin - $ini + 1);
        }
        $data = json_decode($texto, true);
        if (! is_array($data)) {
            throw new \RuntimeException('La IA no devolvió un JSON válido.');
        }

        return $data;
    }
}
