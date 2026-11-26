<?php
/**
 * CAR · PDF del plan de estudios generado con las materias del BORRADOR.
 * Sirve para revisar cómo quedará antes de publicar.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';
require_once __DIR__ . '/../includes/pdf_plan.php';

$fichaId = entero(get('ficha'));
if ($fichaId === null) {
    no_encontrado('Ficha no válida.');
}
$ficha = car_ficha_autorizada($fichaId);   // comprueba que sea de su carrera

$materias = car_materias_de_ficha($fichaId);
$unidades = car_unidades_de_ficha($fichaId);
foreach ($materias as &$m) {
    $m['unidades'] = $unidades[(int)$m['id']] ?? [];
}
unset($m);

$nombre = (string)$ficha['nombre'];
car_plan_pdf_entregar(
    car_plan_pdf($nombre, $materias, 'Borrador: refleja las materias capturadas hoy.', car_estadias_de_ficha($fichaId)),
    car_plan_pdf_nombre($nombre)
);
