<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #111; font-size: 11px; margin: 40px 48px; }
        .cab { text-align: right; margin-bottom: 6px; }
        .cab .carrera { font-weight: bold; font-size: 12px; }
        .cab .facultad { font-size: 10px; }
        .cab .codigo { font-size: 10px; text-decoration: underline; }
        .titulo { text-align: center; font-weight: bold; font-size: 13px; margin: 14px 0 16px; }
        .anulado { color: #C00000; font-weight: bold; border: 2px solid #C00000; padding: 4px 8px; text-align: center; margin-bottom: 12px; }
        .campos p { margin: 3px 0; }
        .campos .k { font-weight: bold; }
        .estudiante { font-weight: bold; margin: 6px 0; }
        .intro { margin: 12px 0 8px; }
        .datos p { margin: 3px 0; }
        .datos .k { font-weight: bold; }
        table.tabla { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.tabla th { border-bottom: 2px solid #000; border-top: 2px solid #000; padding: 6px 6px; font-size: 9.5px; text-align: left; vertical-align: bottom; }
        table.tabla td { padding: 5px 6px; font-size: 9.5px; border-bottom: 1px solid #e0e0e0; }
        table.tabla td.c { text-align: center; }
        table.tabla tr.total td { border-top: 2px solid #000; border-bottom: none; font-weight: bold; }
        .firmas { margin-top: 70px; width: 100%; }
        .firmas td { width: 50%; text-align: center; font-size: 10px; padding-top: 4px; }
        .firmas .linea { border-top: 1px solid #333; margin: 0 40px 4px; }
        .firmas .nombre { font-weight: bold; }
        .pie { margin-top: 28px; font-size: 8.5px; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="cab">
        <div class="carrera">{{ mb_strtoupper($carrera ?? '') }}</div>
        <div class="facultad">Facultad de: {{ mb_strtoupper($facultad) }}</div>
        <div class="codigo">{{ $codigoMemo }}</div>
    </div>

    <div class="titulo">Memorándum</div>

    @if ($convalidacion->estado === 'anulada')
        <div class="anulado">CONVALIDACIÓN ANULADA</div>
    @endif

    <div class="campos">
        <p><span class="k">Para</span> : {{ $resp['memo_para_nombre'] }}</p>
        <p style="margin-left:34px;">{{ $resp['memo_para_cargo'] }}</p>
        <p><span class="k">De</span> : {{ $resp['memo_de_nombre'] }}</p>
        <p style="margin-left:34px;">{{ $resp['memo_de_cargo'] }}</p>
        <p><span class="k">Asunto</span> : {{ $resp['memo_asunto'] }}</p>
        <p class="estudiante">Estudiante: {{ $estudiante }}</p>
        <p><span class="k">Fecha</span> : {{ $fecha }}</p>
    </div>

    <p class="intro">Por el siguiente, solicitamos a su Dirección realizar el siguiente trámite:</p>

    <div class="datos">
        <p><span class="k">PROCEDENCIA:</span> {{ $procedencia }}</p>
        <p><span class="k">CÓDIGO DEL ESTUDIANTE:</span> {{ $codigo }}</p>
        <p><span class="k">APLICABLE AL PERÍODO:</span> {{ $periodo }}</p>
    </div>

    <table class="tabla">
        <thead>
            <tr>
                <th style="width:5%;">Item</th>
                <th style="width:31%;">Curso de Malla por Competencias convalidado en USIL</th>
                <th style="width:34%;">Curso llevado en la Institución de procedencia y/o donde realizó el intercambio</th>
                <th style="width:8%; text-align:center;">Nota de origen</th>
                <th style="width:9%; text-align:center;">Ciclo (Curso USIL)</th>
                <th style="width:13%; text-align:center;">Créditos USIL Reconocidos</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($detalles as $i => $d)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ mb_strtoupper($d->cursoUsil?->nombre ?? '') }}</td>
                    <td>{{ $d->nombre_origen ?? $d->cursoExterno?->nombre }}</td>
                    {{-- Evidencia de que el curso estaba aprobado: sin ella el acto no se sustenta. --}}
                    <td class="c">{{ $d->nota_origen ?: '—' }}</td>
                    <td class="c">{{ $d->cursoUsil?->ciclo?->numero ?? '—' }}</td>
                    {{-- El '+' quita el decimal sobrante sin redondear: 3.5 → 3.5, 4.0 → 4. --}}
                    <td class="c">{{ +$d->creditos_reconocidos }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="c" style="color:#999;">Sin cursos convalidados.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="5" style="text-align:right;">Total de Créditos convalidados</td>
                <td class="c">{{ +$total }}</td>
            </tr>
        </tbody>
    </table>

    @if ($convalidacion->estado === 'anulada')
        <p style="color:#C00000; margin-top:10px;"><strong>Motivo de anulación:</strong> {{ $convalidacion->motivo_anulacion }}</p>
    @endif

    <p style="margin-top:26px;">Atentamente.</p>

    <table class="firmas">
        <tr>
            <td>
                <div class="linea"></div>
                <div class="nombre">{{ $resp['memo_firma_izq_nombre'] }}</div>
                <div>{{ $resp['memo_firma_izq_cargo'] }}</div>
            </td>
            <td>
                <div class="linea"></div>
                <div class="nombre">{{ $resp['memo_firma_der_nombre'] }}</div>
                <div>{{ $resp['memo_firma_der_cargo'] }}</div>
            </td>
        </tr>
    </table>

    @if ($emitidoPor)
        <p class="pie">Emitido en el Sistema de Convalidaciones por {{ $emitidoPor }} · {{ $codigoMemo }}</p>
    @endif
</body>
</html>
