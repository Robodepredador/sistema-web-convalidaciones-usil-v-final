<?php

namespace Database\Seeders;

use App\Models\InstitucionExterna;
use App\Models\TipoInstitucion;
use Illuminate\Database\Seeder;

/**
 * Catálogo de instituciones de educación superior del Perú.
 *
 * Universidades: lista completa de las licenciadas por SUNEDU (públicas y privadas).
 * Institutos: selección representativa de IEST/ESFA públicos y privados
 * (no existe un registro único tipo SUNEDU para institutos).
 *
 * Ejecutar: php artisan db:seed --class=SuneduSeeder
 */
class SuneduSeeder extends Seeder
{
    public function run(): void
    {
        $tipoUniv = TipoInstitucion::firstOrCreate(['nombre' => 'Universidad'])->id;
        $tipoInst = TipoInstitucion::firstOrCreate(['nombre' => 'Instituto'])->id;

        // --- Universidades públicas (nacionales) licenciadas por SUNEDU ---
        $universidadesPublicas = [
            'Universidad Nacional Mayor de San Marcos',
            'Universidad Nacional de San Cristóbal de Huamanga',
            'Universidad Nacional San Antonio Abad del Cusco',
            'Universidad Nacional de Educación Enrique Guzmán y Valle',
            'Universidad Nacional de Trujillo',
            'Universidad Nacional de San Agustín',
            'Universidad Nacional del Altiplano de Puno',
            'Universidad Nacional de Ingeniería',
            'Universidad Nacional Agraria La Molina',
            'Universidad Nacional San Luis Gonzaga',
            'Universidad Nacional del Centro del Perú',
            'Universidad Nacional Daniel Alcides Carrión',
            'Universidad Nacional Hermilio Valdizán',
            'Universidad Nacional de la Amazonía Peruana',
            'Universidad Nacional de Piura',
            'Universidad Nacional de Cajamarca',
            'Universidad Nacional Federico Villarreal',
            'Universidad Nacional Agraria de la Selva',
            'Universidad Nacional del Callao',
            'Universidad Nacional José Faustino Sánchez Carrión',
            'Universidad Nacional Pedro Ruiz Gallo',
            'Universidad Nacional Jorge Basadre Grohmann',
            'Universidad Nacional Santiago Antúnez de Mayolo',
            'Universidad Nacional de Ucayali',
            'Universidad Nacional de San Martín',
            'Universidad Nacional del Santa',
            'Universidad Nacional de Tumbes',
            'Universidad Nacional de Huancavelica',
            'Universidad Nacional Amazónica de Madre de Dios',
            'Universidad Nacional Intercultural de la Amazonía',
            'Universidad Nacional Micaela Bastidas de Apurímac',
            'Universidad Nacional Toribio Rodríguez de Mendoza de Amazonas',
            'Universidad Nacional Tecnológica de Lima Sur',
            'Universidad Nacional José María Arguedas',
            'Universidad Nacional de Moquegua',
            'Universidad Nacional de Juliaca',
            'Universidad Nacional de Jaén',
            'Universidad Nacional Autónoma de Chota',
            'Universidad Nacional de Frontera',
            'Universidad Nacional Intercultural de la Selva Central Juan Santos Atahualpa',
            'Universidad Nacional Intercultural Fabiola Salazar Leguía de Bagua',
            'Universidad Nacional de Barranca',
            'Universidad Nacional Autónoma de Huanta',
            'Universidad Nacional Autónoma Altoandina de Tarma',
            'Universidad Nacional Autónoma de Alto Amazonas',
            'Universidad Nacional Ciro Alegría',
            'Universidad Nacional Autónoma de Tayacaja Daniel Hernández Morillo',
            'Universidad Nacional de Cañete',
            'Universidad Nacional Intercultural de Quillabamba',
            'Universidad Nacional Tecnológica de Frontera San Ignacio de Loyola',
        ];

        // --- Universidades privadas licenciadas por SUNEDU ---
        $universidadesPrivadas = [
            'Pontificia Universidad Católica del Perú',
            'Universidad Peruana Cayetano Heredia',
            'Universidad Católica de Santa María',
            'Universidad del Pacífico',
            'Universidad de Lima',
            'Universidad de San Martín de Porres',
            'Universidad Femenina del Sagrado Corazón',
            'Universidad Inca Garcilaso de la Vega',
            'Universidad Marcelino Champagnat',
            'Universidad de Piura',
            'Universidad Ricardo Palma',
            'Universidad Andina del Cusco',
            'Universidad Peruana Los Andes',
            'Universidad Peruana Unión',
            'Universidad Tecnológica de los Andes',
            'Universidad de Huánuco',
            'Universidad Privada de Tacna',
            'Universidad Privada Antenor Orrego',
            'Universidad Particular de Iquitos',
            'Universidad César Vallejo',
            'Universidad Privada del Norte',
            'Facultad de Teología Pontificia y Civil de Lima',
            'Universidad Peruana de Ciencias Aplicadas',
            'Universidad San Ignacio de Loyola',
            'Universidad Católica Santo Toribio de Mogrovejo',
            'Universidad Norbert Wiener',
            'Universidad Católica San Pablo',
            'Universidad Privada San Juan Bautista',
            'Universidad Tecnológica del Perú',
            'Universidad Católica Sedes Sapientiae',
            'Universidad Científica del Sur',
            'Universidad Continental',
            'Escuela de Postgrado Gerens',
            'Universidad Señor de Sipán',
            'Universidad Católica de Trujillo Benedicto XVI',
            'Universidad para el Desarrollo Andino',
            'Universidad Antonio Ruiz de Montoya',
            'Universidad ESAN',
            'Universidad Jaime Bausate y Meza',
            'Universidad Privada de Trujillo',
            'Universidad de Ciencias y Humanidades',
            'Universidad Autónoma de Ica',
            'Universidad Autónoma del Perú',
            'Universidad de Ciencias y Artes de América Latina',
            'Universidad La Salle',
            'Universidad Privada de Huancayo Franklin Roosevelt',
            'Universidad de Ingeniería y Tecnología',
            'Universidad María Auxiliadora',
            'Universidad Privada Peruano Alemana',
            'Escuela de Postgrado Neumann Business School',
        ];

        // --- Institutos de educación superior licenciados por MINEDU (Ley 30512) ---
        // Más SENATI y SENCICO (entidades públicas de formación). Nombres según fuente oficial.
        $institutosPublicos = [
            'José Carlos Mariátegui',
            'Túpac Amaru',
            'Francisco De Paula Gonzáles Vigil',
            'Simón Bolívar',
            'República Federal de Alemania',
            'Clorinda Matto de Turner',
            'Héctor Vásquez Jiménez',
            'Otuzco',
            'Huando',
            'De Crucero',
            'SENATI',
            'SENCICO',
        ];

        $institutosPrivados = [
            'Daniel Alcides Carrión', 'San Marcos', 'Instituto Peruano de Administración de Empresas (IPAE)',
            'Cibertec', 'San Ignacio de Loyola (ISIL)', 'Instituto Técnico de Administración de Empresas (ITAE)',
            'Cepea', 'Idat', 'Continental', 'María Montessori', 'Khipu', 'Jhalebet', 'EF', 'Tecsup', 'Tec',
            'María de los Ángeles Cimas', 'Arzobispo Loayza', 'Paul Muller', 'Capeco', 'Latino',
            'Wernher Von Braun', 'Columbia', 'Nina Design', 'Ricardo Palma', 'De Ciencias Multiculturales (CIM)',
            'El Buen Pastor', 'Peruano de Sistemas', 'Toulouse Lautrec', 'Charles Chaplin', 'Sergio Bernales',
            'Orson Welles', 'Peruano de Marketing', 'Instituto Peruano de Publicidad', 'Padre Abad',
            'Sabio Nacional Antúnez de Mayolo', 'Elmer Faucett', 'Lima Institute Of Technical Studies',
            'Instituto Peruano de Arte y Diseño', 'Certus', 'Centro de Altos Estudios de la Moda',
            'De Optometría y Ciencias Eurohispano', 'San Ignacio de Monterrico', 'Alexander Von Humboldt',
            'De Comercio Exterior', 'María Elena Moyano', 'Idatur', 'Sergio Bernales García', 'Condoray',
            'Santa Rosa de Lima', 'José Santos Chocano', 'La Pontificia', 'Cesde', 'De Emprendedores Isag',
            'Nuestra Señora del Carmen', 'Lilia Gutiérrez Molero', 'Juan Bosco de Huánuco',
            'Complejo Hospitalario San Pablo', 'Le Cordon Bleu Perú', 'Nuestra Señora de Montserrat',
            'Instituto de Investigación Socioeconómico Latinoamericano', 'Virgen de Guadalupe', 'Nuevo Pachacútec',
            'Instituto Peruano de Administración de Empresas (Iquitos)', 'Instituto Latino del Cusco', 'San Antonio',
            'Chio Lecca', 'Federico Villarreal Chincha', 'Corriente Alterna', 'Iberoamericana', 'Centro de la Imagen',
            'Alas Peruanas', 'Centro Peruano de Estudios Bancarios (Cepeban)', 'San José del Sur',
            'Instituto de Profesiones Empresariales (Inteci)', 'Alta Cocina D Gallia', 'BSG Institute',
            'Educación Médica San Fernando', 'Administración y Negocios', 'De Emprendedores', 'Ceturgh Perú',
            'Fibonacci', 'Peruano de Cine y Creatividad (EPIC)', 'ModArt Perú', 'Latino Barranca', 'Cetemin',
            'Investigación Ciencia y Tecnología', 'Interamericano', 'Inlog', 'San Lucas', 'Josb Capacity',
            'Ansimar', 'Santa María', 'Sinergia', 'ISCEMP',
        ];

        $sembrar = function (array $nombres, int $tipoId, string $gestion): int {
            $n = 0;
            foreach ($nombres as $nombre) {
                InstitucionExterna::updateOrCreate(
                    ['nombre' => $nombre],
                    ['tipo_id' => $tipoId, 'pais' => 'Perú', 'gestion' => $gestion, 'activa' => true]
                );
                $n++;
            }

            return $n;
        };

        $up = $sembrar($universidadesPublicas, $tipoUniv, 'publica');
        $upr = $sembrar($universidadesPrivadas, $tipoUniv, 'privada');

        // Reemplaza el set anterior de institutos del catálogo (conserva los de demo sin gestión).
        InstitucionExterna::where('tipo_id', $tipoInst)->whereNotNull('gestion')->delete();

        $ipu = $sembrar($institutosPublicos, $tipoInst, 'publica');
        $ipr = $sembrar($institutosPrivados, $tipoInst, 'privada');

        $this->command->info("Catálogo SUNEDU sembrado: {$up} universidades públicas, {$upr} privadas; {$ipu} institutos públicos, {$ipr} privados.");
    }
}
