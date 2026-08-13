<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee un plan de estudios USIL en Excel y lo convierte en la estructura editable
 * que revisa el usuario antes de registrar la malla (ciclos y menciones).
 *
 * El formato real no es una tabla plana: trae metadatos de cabecera (Facultad,
 * Carrera, Plan de Estudios), la fila de encabezado más abajo (Ciclo · Curso · CR
 * · TH · Pre-Requisito), filas de «Total» por ciclo que se descartan, y al final
 * bloques de mención/especialidad. Este lector reconoce cada parte.
 *
 * No persiste nada: solo interpreta. El guardado ocurre tras la revisión humana.
 */
class LectorMallaExcel
{
    public function __construct(private ConvalidacionEngine $engine) {}

    /**
     * @return array{hoja:string, meta:array, ciclos:array, menciones:array, resumen:array}
     */
    public function parse(string $rutaAbsoluta, string $carreraNombre, string $carreraCodigo): array
    {
        $spreadsheet = IOFactory::load($rutaAbsoluta);
        $hoja = $this->elegirHoja($spreadsheet, $carreraNombre);
        $filas = $hoja->toArray(null, true, true, false); // filas y columnas indexadas desde 0

        $encabezado = $this->localizarEncabezado($filas);
        if ($encabezado === null) {
            throw new \RuntimeException('No se encontró la tabla de cursos (una fila con «Ciclo» y «Curso»).');
        }
        [$hdrRow, $cols] = $encabezado;

        $meta = $this->extraerMeta($filas, $hdrRow);
        $prefijo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $carreraCodigo)) ?: 'C';

        $ciclos = [];      // numero => ['numero','cursos']
        $menciones = [];      // nombre => ['nombre','indice','cursos']
        $mencionActual = null;
        $mIdx = 0;
        $contadorCiclo = [];
        $contadorMencion = [];

        for ($r = $hdrRow + 1; $r < count($filas); $r++) {
            $fila = $filas[$r];
            $cicloRaw = trim((string) ($fila[$cols['ciclo']] ?? ''));
            $codigo = $cols['codigo'] !== null ? trim((string) ($fila[$cols['codigo']] ?? '')) : '';
            $nombre = trim((string) ($fila[$cols['nombre']] ?? ''));
            $crRaw = trim((string) ($fila[$cols['creditos']] ?? ''));
            
            $thRaw = $cols['horas_teoria'] !== null ? trim((string) ($fila[$cols['horas_teoria']] ?? '')) : '';
            $tpRaw = $cols['horas_practica'] !== null ? trim((string) ($fila[$cols['horas_practica']] ?? '')) : '';
            $caracter = $cols['caracter'] !== null ? trim((string) ($fila[$cols['caracter']] ?? '')) : '';
            $mencionCol = $cols['mencion'] !== null ? trim((string) ($fila[$cols['mencion']] ?? '')) : '';

            // Fila totalmente vacía.
            if ($cicloRaw === '' && $nombre === '' && $crRaw === '') {
                continue;
            }
            // Filas de subtotal / total: se descartan.
            if (str_contains($this->engine->normaliza($nombre), 'total')
                || str_contains($this->engine->normaliza($cicloRaw), 'total')) {
                continue;
            }

            // A partir de aquí, una fila válida requiere ciclo numérico y nombre de curso.
            if (! is_numeric($cicloRaw) || $nombre === '') {
                continue;
            }
            $ciclo = (int) $cicloRaw;
            if ($ciclo < 1 || $ciclo > 14) {
                continue;
            }

            $creditos = is_numeric($crRaw) ? (float) $crRaw : null;
            $horas_teoria = is_numeric($thRaw) ? (float) $thRaw : null;
            $esElectivo = stripos($caracter, 'electivo') !== false || stripos($nombre, 'electivo') !== false;

            // Si no hay codigo, generarlo
            $codigoFinal = $codigo;

            if ($mencionCol !== '') {
                if (!isset($menciones[$mencionCol])) {
                    $mIdx++;
                    $menciones[$mencionCol] = ['nombre' => $mencionCol, 'indice' => $mIdx, 'cursos' => []];
                    $contadorMencion[$mIdx] = 0;
                }
                
                $indice = $menciones[$mencionCol]['indice'];
                $n = ++$contadorMencion[$indice];
                
                if ($codigoFinal === '') {
                    $codigoFinal = sprintf('%s-M%d-%02d', $prefijo, $indice, $n);
                }

                $menciones[$mencionCol]['cursos'][] = [
                    'codigo' => $codigoFinal,
                    'nombre' => $nombre,
                    'ciclo' => $ciclo,
                    'creditos' => $creditos,
                    'horas' => $horas_teoria,
                    'prerequisito' => '',
                    'es_electivo' => $esElectivo,
                    'convalidable' => true,
                ];
            } else {
                if (! isset($ciclos[$ciclo])) {
                    $ciclos[$ciclo] = ['numero' => $ciclo, 'cursos' => []];
                    $contadorCiclo[$ciclo] = 0;
                }
                $n = ++$contadorCiclo[$ciclo];
                
                if ($codigoFinal === '') {
                    $codigoFinal = sprintf('%s-C%d-%02d', $prefijo, $ciclo, $n);
                }

                $ciclos[$ciclo]['cursos'][] = [
                    'codigo' => $codigoFinal,
                    'nombre' => $nombre,
                    'creditos' => $creditos,
                    'horas' => $horas_teoria,
                    'prerequisito' => '',
                    'es_electivo' => $esElectivo,
                    'convalidable' => true,
                ];
            }
        }

        ksort($ciclos);
        $ciclos = array_values($ciclos);
        $menciones = array_values($menciones);

        $totalCursos = array_sum(array_map(fn ($c) => count($c['cursos']), $ciclos))
            + array_sum(array_map(fn ($m) => count($m['cursos']), $menciones));

        return [
            'hoja' => $hoja->getTitle(),
            'meta' => $meta,
            'ciclos' => $ciclos,
            'menciones' => $menciones,
            'resumen' => [
                'cursos' => $totalCursos,
                'ciclos' => count($ciclos),
                'menciones' => count($menciones),
            ],
        ];
    }

    /**
     * Elige la hoja cuya línea «Carrera Profesional…» más se parece a la carrera
     * seleccionada (un mismo Excel puede traer varias carreras en hojas distintas).
     */
    private function elegirHoja(Spreadsheet $spreadsheet, string $carreraNombre): Worksheet
    {
        $mejor = null;
        $mejorScore = -1.0;

        foreach ($spreadsheet->getAllSheets() as $hoja) {
            $texto = '';
            foreach ($hoja->toArray(null, true, true, false) as $i => $fila) {
                if ($i > 6) {
                    break;
                }
                foreach ($fila as $v) {
                    if ($v && stripos((string) $v, 'carrera') !== false) {
                        $texto = (string) $v;
                        break 2;
                    }
                }
            }
            $score = $texto !== '' ? $this->engine->similitud($texto, $carreraNombre) : 0.0;
            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejor = $hoja;
            }
        }

        return $mejor ?? $spreadsheet->getSheet(0);
    }

    /**
     * Localiza la fila de encabezado y a qué columna corresponde cada dato.
     *
     * @return array{0:int,1:array{ciclo:int,curso:int,cr:int,th:?int,pre:?int}}|null
     */
    private function localizarEncabezado(array $filas): ?array
    {
        foreach ($filas as $r => $fila) {
            $map = [];
            foreach ($fila as $ci => $val) {
                $n = $this->engine->normaliza((string) $val);
                if ($n === 'ciclo') {
                    $map['ciclo'] = $ci;
                } elseif ($n === 'codigo') {
                    $map['codigo'] = $ci;
                } elseif ($n === 'nombre' || $n === 'curso') {
                    $map['nombre'] = $ci;
                } elseif ($n === 'creditos' || $n === 'cr' || str_contains($n, 'credito')) {
                    $map['creditos'] = $ci;
                } elseif ($n === 'horas_teoria' || $n === 'th' || str_contains($n, 'hora')) {
                    $map['horas_teoria'] = $ci;
                } elseif ($n === 'horas_practica') {
                    $map['horas_practica'] = $ci;
                } elseif ($n === 'caracter') {
                    $map['caracter'] = $ci;
                } elseif ($n === 'mencion') {
                    $map['mencion'] = $ci;
                }
            }
            // Aceptar si tiene ciclo, nombre/curso y creditos.
            if (isset($map['ciclo'], $map['nombre'], $map['creditos'])) {
                return [$r, $map + ['codigo' => null, 'horas_teoria' => null, 'horas_practica' => null, 'caracter' => null, 'mencion' => null]];
            }
        }

        return null;
    }

    /** Extrae año y versión del plan desde los metadatos de cabecera. */
    private function extraerMeta(array $filas, int $hdrRow): array
    {
        $meta = ['anio' => null, 'version' => null, 'facultad' => null, 'carrera' => null];

        for ($r = 0; $r < $hdrRow; $r++) {
            foreach ($filas[$r] as $v) {
                $s = trim((string) $v);
                if ($s === '') {
                    continue;
                }
                if (stripos($s, 'plan de estudios') !== false
                    && preg_match('/(\d{4})\s*[-–]\s*(\d{1,2})/', $s, $m)) {
                    $meta['anio'] = (int) $m[1];
                    $meta['version'] = $m[1].'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT);
                } elseif (stripos($s, 'facultad') !== false && $meta['facultad'] === null) {
                    $meta['facultad'] = $s;
                } elseif (stripos($s, 'carrera') !== false && $meta['carrera'] === null) {
                    $meta['carrera'] = $s;
                }
            }
        }

        return $meta;
    }
}
