<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #111; font-size: 12px; margin: 32px 40px; }
        .titulo { text-align: center; margin-bottom: 18px; }
        .titulo h1 { margin: 0; font-size: 15px; font-weight: bold; }
        .datos { margin-bottom: 16px; }
        .datos p { margin: 2px 0; }
        .datos .k { font-weight: bold; }
        table.tabla { width: 100%; border-collapse: collapse; }
        table.tabla th, table.tabla td { border: 1px solid #000; padding: 6px 8px; }
        table.tabla th { background: #e9e9e9; text-align: center; font-size: 12px; }
        table.tabla td.c { text-align: center; }
        table.tabla tr td { font-size: 11px; }
        .total td { font-weight: bold; background: #f4f4f4; }
        .sub { margin: 18px 0 6px; font-size: 13px; font-weight: bold; color: #1F2A44; }
        .footer { margin-top: 24px; font-size: 9px; color: #888; }
    </style>
</head>
<body>
    @php
        $fac = $simulacion->carreraUsil?->facultad?->nombre ?? 'Ingeniería';
        $fac = \Illuminate\Support\Str::startsWith(mb_strtolower($fac), 'facultad') ? $fac : 'Facultad de '.$fac;
    @endphp
    <div class="titulo">
        <h1>{{ $fac }}</h1>
        <h1>Carrera Profesional: {{ $simulacion->carreraUsil?->nombre }}</h1>
        <h1>Plan de Estudios: {{ $malla?->anio ?? '—' }}</h1>
    </div>

    <div class="datos">
        <p><span class="k">Alumno:</span> {{ $simulacion->nombres }} {{ $simulacion->apellidos }}</p>
        <p><span class="k">Código:</span> {{ $simulacion->postulante?->codigo ?? '-' }}</p>
        <p><span class="k">Año - Semestre de Ingreso:</span> {{ $simulacion->ciclo_postulacion }}</p>
        <br>
        <p><span class="k">Convalidación - Institución de Procedencia:</span> {{ $simulacion->universidad_origen ?? $simulacion->postulante?->institucionOrigen?->nombre }}</p>
        <p><span class="k">Carrera de Procedencia:</span> {{ $simulacion->carreraExterna?->nombre }}</p>
        {{-- La fecha de la evaluación, no la de la impresión: dos descargas del
             mismo expediente daban dos documentos con fechas distintas. --}}
        <p><span class="k">Fecha de Revisión:</span> {{ optional($simulacion->updated_at ?? $simulacion->created_at)->format('d/m/Y') ?: '—' }}</p>
    </div>

    <table class="tabla">
        <thead>
            <tr>
                <th style="width:8%;">Ciclo</th>
                <th style="width:40%;">Curso USIL</th>
                <th style="width:40%;">Curso Convalidado</th>
                <th style="width:12%;">Créditos</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($convalidados as $d)
                <tr>
                    <td class="c">{{ $d->cursoUsil?->ciclo?->numero }}</td>
                    <td>{{ $d->cursoUsil?->nombre }}</td>
                    <td>{{ $d->nombre_origen }}</td>
                    {{-- El '+' quita el decimal sobrante sin redondear: 3.5 → 3.5, 4.0 → 4. --}}
                    <td class="c">{{ +$d->creditos_reconocidos }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="c" style="color:#999;">Sin cursos convalidados.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="3" style="text-align:right;">Total de créditos convalidados</td>
                <td class="c">{{ +$creditos }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Documento de preconvalidación (referencial) · Sistema de Convalidaciones USIL · {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
