<?php

/**
 * Genera un script SQL completo (estructura + datos) para replicar la base de
 * datos conectada por Laravel. Evita el problema de mysqldump con nombres de BD
 * acentuados en Windows usando la misma conexión PDO de la app.
 *
 * Uso:  php scripts/dump_db.php
 * Salida: backups/<db>_<timestamp>.sql
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$db = DB::connection()->getDatabaseName();

$dir = __DIR__.'/../backups';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$file = $dir.'/'.preg_replace('/[^A-Za-z0-9_]/', '_', $db).'_'.date('Y-m-d_His').'.sql';
$out = fopen($file, 'w');

$w = fn (string $s) => fwrite($out, $s);

// --- Cabecera ---
$w("-- ------------------------------------------------------------\n");
$w("-- Backup de la base de datos `{$db}`\n");
$w('-- Generado: '.date('Y-m-d H:i:s')."\n");
$w("-- Motor: MySQL · Charset: utf8mb4\n");
$w("-- ------------------------------------------------------------\n\n");
$w("SET NAMES utf8mb4;\n");
$w("SET FOREIGN_KEY_CHECKS = 0;\n");
$w("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
$w("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
$w("USE `{$db}`;\n\n");

// --- Tablas ---
$tablas = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
$vistas = $pdo->query('SHOW FULL TABLES WHERE Table_type = "VIEW"')->fetchAll(PDO::FETCH_COLUMN);

$totalFilas = 0;
foreach ($tablas as $tabla) {
    $create = $pdo->query("SHOW CREATE TABLE `{$tabla}`")->fetch(PDO::FETCH_ASSOC);
    $ddl = $create['Create Table'];

    $w("-- ----------------------------\n");
    $w("-- Estructura de `{$tabla}`\n");
    $w("-- ----------------------------\n");
    $w("DROP TABLE IF EXISTS `{$tabla}`;\n");
    $w($ddl.";\n\n");

    // Datos
    $filas = $pdo->query("SELECT * FROM `{$tabla}`");
    $columnas = null;
    $buffer = [];
    $count = 0;

    while ($fila = $filas->fetch(PDO::FETCH_ASSOC)) {
        if ($columnas === null) {
            $columnas = '`'.implode('`, `', array_keys($fila)).'`';
        }
        $vals = array_map(function ($v) use ($pdo) {
            if ($v === null) {
                return 'NULL';
            }

            return $pdo->quote((string) $v);
        }, array_values($fila));
        $buffer[] = '('.implode(', ', $vals).')';
        $count++;

        // Inserta por lotes de 200 filas.
        if (count($buffer) >= 200) {
            $w("INSERT INTO `{$tabla}` ({$columnas}) VALUES\n".implode(",\n", $buffer).";\n");
            $buffer = [];
        }
    }
    if ($buffer) {
        $w("INSERT INTO `{$tabla}` ({$columnas}) VALUES\n".implode(",\n", $buffer).";\n");
    }
    $w("\n");
    $totalFilas += $count;
    echo str_pad($tabla, 34)." {$count} filas\n";
}

// --- Vistas (si existieran) ---
foreach ($vistas as $vista) {
    $create = $pdo->query("SHOW CREATE VIEW `{$vista}`")->fetch(PDO::FETCH_ASSOC);
    $w("DROP VIEW IF EXISTS `{$vista}`;\n".$create['Create View'].";\n\n");
}

$w("SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($out);

echo "\n==============================\n";
echo 'Backup generado: '.realpath($file)."\n";
echo 'Tablas: '.count($tablas).' · Vistas: '.count($vistas).' · Filas: '.$totalFilas."\n";
echo 'Tamaño: '.round(filesize($file) / 1024, 1)." KB\n";
