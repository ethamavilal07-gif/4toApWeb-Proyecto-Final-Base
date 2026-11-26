<?php
/**
 * CAR · PDF del plan de estudios de la VERSIÓN PUBLICADA (público).
 * Se genera al momento con lo que hay en la versión publicada.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';
require_once __DIR__ . '/includes/consultas.php';
require_once __DIR__ . '/includes/pdf_plan.php';

$carreraId = entero(get('id'));
if ($carreraId === null) {
    no_encontrado('Carrera no válida.');
}

$publicada = car_ficha_publica($carreraId);
if ($publicada === null) {
    no_encontrado('Esta carrera no tiene ficha publicada.');
}

$materias = $publicada['materias'];
foreach ($materias as &$m) {
    $m['unidades'] = $m['unidades'] ?? [];
}
unset($m);

$nombre = (string)$publicada['ficha']['nombre'];
car_plan_pdf_entregar(car_plan_pdf($nombre, $materias), car_plan_pdf_nombre($nombre));
