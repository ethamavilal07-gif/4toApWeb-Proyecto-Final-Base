<?php
/**
 * CAR · Generador del PDF del plan de estudios
 *
 * Sin librerías externas: arma un PDF sencillo (texto en Helvetica, tamaño A4)
 * con las materias de la carrera agrupadas por cuatrimestre.
 */

declare(strict_types=1);

/** Escritor mínimo de PDF: páginas de texto, rectángulos y líneas. */
final class CarPdf
{
    private const ANCHO = 595.0;
    private const ALTO  = 842.0;
    public const MARGEN = 50.0;

    /** Anchos de Helvetica (milésimas de em) de los caracteres 32 a 126. */
    private const ANCHOS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var string[] contenido de cada página */
    private array $paginas = [''];
    public float $y;

    public function __construct()
    {
        $this->y = self::ALTO - self::MARGEN;
    }

    public static function anchoUtil(): float
    {
        return self::ANCHO - 2 * self::MARGEN;
    }

    /** UTF-8 → Windows-1252 (lo que entiende la fuente estándar). */
    private static function codificar(string $t): string
    {
        $t = str_replace(["\r", "\t"], ['', '    '], $t);
        $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $t);
        return $r === false ? '' : $r;
    }

    private static function escapar(string $t): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $t);
    }

    public function ancho(string $texto, float $tam, bool $negrita = false): float
    {
        $c = self::codificar($texto);
        $u = 0;
        for ($i = 0, $n = strlen($c); $i < $n; $i++) {
            $b = ord($c[$i]);
            if ($b >= 32 && $b <= 126) {
                $u += self::ANCHOS[$b - 32];
            } else {
                $u += match ($b) {
                    0xE1, 0xE0, 0xE4 => 556, 0xE9, 0xE8 => 556, 0xED => 222, 0xF3, 0xF2 => 556,
                    0xFA, 0xFC => 556, 0xF1 => 556, 0xC1 => 667, 0xC9 => 667, 0xCD => 278,
                    0xD3 => 778, 0xDA, 0xDC => 722, 0xD1 => 722, 0xA1 => 333, 0xBF => 611,
                    0x95 => 350, 0x96 => 556, 0xBA => 365,
                    default => 556,
                };
            }
        }
        return $u / 1000 * $tam * ($negrita ? 1.07 : 1.0);
    }

    /** Parte un texto en líneas que caben en $maxAncho. */
    public function partir(string $texto, float $tam, float $maxAncho, bool $negrita = false): array
    {
        $lineas = [];
        foreach (preg_split('/\n/u', trim($texto)) as $parrafo) {
            $actual = '';
            foreach (preg_split('/\s+/u', trim($parrafo)) as $palabra) {
                if ($palabra === '') {
                    continue;
                }
                $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
                if ($this->ancho($prueba, $tam, $negrita) <= $maxAncho) {
                    $actual = $prueba;
                    continue;
                }
                if ($actual !== '') {
                    $lineas[] = $actual;
                }
                // una palabra más larga que la línea se corta
                while ($this->ancho($palabra, $tam, $negrita) > $maxAncho && mb_strlen($palabra) > 1) {
                    $corte = mb_strlen($palabra) - 1;
                    while ($corte > 1 && $this->ancho(mb_substr($palabra, 0, $corte), $tam, $negrita) > $maxAncho) {
                        $corte--;
                    }
                    $lineas[] = mb_substr($palabra, 0, $corte);
                    $palabra  = mb_substr($palabra, $corte);
                }
                $actual = $palabra;
            }
            $lineas[] = $actual;
        }
        return $lineas;
    }

    /** Si no queda espacio para $alto, pasa a una página nueva. */
    public function espacio(float $alto): void
    {
        if ($this->y - $alto < self::MARGEN + 20) {
            $this->paginas[] = '';
            $this->y = self::ALTO - self::MARGEN;
        }
    }

    private function agregar(string $op): void
    {
        $this->paginas[count($this->paginas) - 1] .= $op . "\n";
    }

    private static function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /** @param float[] $rgb valores de 0 a 1 */
    public function texto(float $x, float $y, string $t, float $tam, bool $negrita = false, array $rgb = [0.1, 0.12, 0.2]): void
    {
        $this->agregar(sprintf(
            '%s rg BT /%s %s Tf %s %s Td (%s) Tj ET',
            implode(' ', array_map([self::class, 'num'], $rgb)),
            $negrita ? 'F2' : 'F1',
            self::num($tam), self::num($x), self::num($y),
            self::escapar(self::codificar($t))
        ));
    }

    public function rectangulo(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->agregar(sprintf(
            '%s rg %s %s %s %s re f',
            implode(' ', array_map([self::class, 'num'], $rgb)),
            self::num($x), self::num($y), self::num($w), self::num($h)
        ));
    }

    public function linea(float $x1, float $y1, float $x2, float $y2, array $rgb = [0.8, 0.82, 0.88]): void
    {
        $this->agregar(sprintf(
            '%s RG 0.6 w %s %s m %s %s l S',
            implode(' ', array_map([self::class, 'num'], $rgb)),
            self::num($x1), self::num($y1), self::num($x2), self::num($y2)
        ));
    }

    /** Párrafo con ajuste de línea; avanza el cursor. */
    public function parrafo(string $t, float $tam, float $x, float $maxAncho, bool $negrita = false, array $rgb = [0.1, 0.12, 0.2]): void
    {
        $paso = $tam * 1.35;
        foreach ($this->partir($t, $tam, $maxAncho, $negrita) as $l) {
            $this->espacio($paso);
            $this->y -= $paso;
            $this->texto($x, $this->y, $l, $tam, $negrita, $rgb);
        }
    }

    /** Devuelve el archivo PDF completo como cadena. */
    public function salida(string $titulo): string
    {
        $total = count($this->paginas);
        $objetos = [];                       // el índice 0 es el objeto 1
        $objetos[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $total; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }
        $objetos[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
        $objetos[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->paginas as $i => $contenido) {
            $pie = sprintf('Página %d de %d', $i + 1, $total);
            $contenido .= sprintf(
                '0.45 0.48 0.56 rg BT /F1 8 Tf %s 30 Td (%s) Tj ET',
                self::num(self::ANCHO - self::MARGEN - $this->ancho($pie, 8)),
                self::escapar(self::codificar($pie))
            ) . "
";
            // objeto de la página (5 + 2i) y su contenido (6 + 2i)
            $objetos[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::num(self::ANCHO) . ' ' . self::num(self::ALTO)
                . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . (6 + $i * 2) . ' 0 R >>';
            $objetos[] = '<< /Length ' . strlen($contenido) . " >>
stream
" . $contenido . 'endstream';
        }

        $objetos[] = '<< /Title (' . self::escapar(self::codificar($titulo)) . ') /Producer (Explorador Vocacional UTBB) >>';
        $infoNum = count($objetos);

        $pdf = "%PDF-1.4\n";
        $pos = [];
        foreach ($objetos as $n => $obj) {
            $pos[$n + 1] = strlen($pdf);
            $pdf .= ($n + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . ($infoNum + 1) . "\n0000000000 65535 f \n";
        for ($n = 1; $n <= $infoNum; $n++) {
            $pdf .= sprintf("%010d 00000 n \n", $pos[$n]);
        }
        $pdf .= "trailer\n<< /Size " . ($infoNum + 1) . ' /Root 1 0 R /Info ' . $infoNum . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}

/**
 * Arma el PDF del plan de estudios.
 *
 * @param array $materias cada una con: cuatrimestre, clave_materia, nombre, horas_totales,
 *                        proposito, competencia y 'unidades' (numero, nombre,
 *                        horas_saber, horas_saber_hacer, horas_totales)
 */
function car_plan_pdf(string $carrera, array $materias, string $nota = ''): string
{
    $pdf   = new CarPdf();
    $azul  = [0.10, 0.25, 0.63];
    $suave = [0.90, 0.93, 0.99];
    $mx    = CarPdf::MARGEN;
    $ancho = CarPdf::anchoUtil();

    // Portada del documento
    $pdf->parrafo('Plan de estudios', 12, $mx, $ancho, false, [0.4, 0.43, 0.52]);
    $pdf->y -= 4;
    $pdf->parrafo($carrera, 22, $mx, $ancho, true, [0.06, 0.13, 0.29]);
    $pdf->y -= 6;
    $pdf->parrafo('Universidad Tecnológica de Bahía de Banderas · Información detallada de las asignaturas', 9, $mx, $ancho, false, [0.4, 0.43, 0.52]);
    if ($nota !== '') {
        $pdf->parrafo($nota, 9, $mx, $ancho, true, [0.7, 0.3, 0.05]);
    }
    $pdf->y -= 8;
    $pdf->linea($mx, $pdf->y, $mx + $ancho, $pdf->y);
    $pdf->y -= 14;

    if (!$materias) {
        $pdf->parrafo('Aún no hay materias registradas para esta carrera.', 11, $mx, $ancho);
        return $pdf->salida('Plan de estudios · ' . $carrera);
    }

    $porCuatri = [];
    foreach ($materias as $m) {
        $porCuatri[(int)$m['cuatrimestre']][] = $m;
    }
    ksort($porCuatri);

    foreach ($porCuatri as $num => $lista) {
        $pdf->espacio(60);
        $pdf->y -= 6;
        $pdf->rectangulo($mx, $pdf->y - 22, $ancho, 24, $azul);
        $pdf->texto($mx + 10, $pdf->y - 15, $num . '.º Cuatrimestre', 12, true, [1, 1, 1]);
        $pdf->y -= 34;

        foreach ($lista as $m) {
            $pdf->espacio(70);
            $titulo = trim(($m['clave_materia'] ? $m['clave_materia'] . ' · ' : '') . $m['nombre']);
            $pdf->parrafo($titulo, 12, $mx, $ancho, true, [0.06, 0.13, 0.29]);

            $meta = [];
            if (!empty($m['horas_totales'])) { $meta[] = 'Horas totales: ' . (int)$m['horas_totales']; }
            if (!empty($m['unidades']))      { $meta[] = 'Unidades: ' . count($m['unidades']); }
            if ($meta) {
                $pdf->parrafo(implode('   ·   ', $meta), 9, $mx, $ancho, false, [0.4, 0.43, 0.52]);
            }
            $pdf->y -= 3;

            foreach (['proposito' => 'Propósito', 'competencia' => 'Competencia'] as $campo => $etiqueta) {
                $valor = trim((string)($m[$campo] ?? ''));
                if ($valor === '') { continue; }
                $pdf->parrafo($etiqueta, 9, $mx, $ancho, true, $azul);
                $pdf->parrafo($valor, 10, $mx, $ancho);
                $pdf->y -= 3;
            }

            if (!empty($m['unidades'])) {
                $x = [$mx, $mx + 30, $mx + 330, $mx + 385, $mx + 440];   // columnas
                $pdf->espacio(34);
                $pdf->y -= 4;
                $pdf->rectangulo($mx, $pdf->y - 14, $ancho, 16, $suave);
                $enc = ['N.º', 'Unidad', 'Saber', 'Saber hacer', 'Total'];
                foreach ($enc as $i => $t) {
                    $pdf->texto($x[$i] + 4, $pdf->y - 9, $t, 8.5, true, $azul);
                }
                $pdf->y -= 16;
                foreach ($m['unidades'] as $u) {
                    $lineas = $pdf->partir((string)$u['nombre'], 9, 290);
                    $alto = max(1, count($lineas)) * 12 + 4;
                    $pdf->espacio($alto);
                    $arriba = $pdf->y;
                    $pdf->texto($x[0] + 4, $arriba - 11, (string)(int)$u['numero'], 9);
                    foreach ($lineas as $k => $l) {
                        $pdf->texto($x[1] + 4, $arriba - 11 - $k * 12, $l, 9);
                    }
                    $pdf->texto($x[2] + 4, $arriba - 11, (string)(int)$u['horas_saber'], 9);
                    $pdf->texto($x[3] + 4, $arriba - 11, (string)(int)$u['horas_saber_hacer'], 9);
                    $pdf->texto($x[4] + 4, $arriba - 11, (string)(int)$u['horas_totales'], 9);
                    $pdf->y -= $alto;
                    $pdf->linea($mx, $pdf->y, $mx + $ancho, $pdf->y);
                }
            }
            $pdf->y -= 16;
        }
    }

    return $pdf->salida('Plan de estudios · ' . $carrera);
}

/** Entrega el PDF al navegador. */
function car_plan_pdf_entregar(string $contenido, string $nombreArchivo): never
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($contenido));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    echo $contenido;
    exit;
}

/** Nombre de archivo seguro a partir del nombre de la carrera. */
function car_plan_pdf_nombre(string $carrera): string
{
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $carrera);
    $t = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', (string)$t), '-'));
    return 'plan-de-estudios-' . ($t !== '' ? $t : 'carrera') . '.pdf';
}
