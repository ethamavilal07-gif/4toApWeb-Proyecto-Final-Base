<?php
/**
 * CAR · Baja de una materia. Solo POST, con token y permisos.
 */

declare(strict_types=1);

require_once _DIR_ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once _DIR_ . '/../includes/consultas.php';
require_once _DIR_ . '/../includes/panel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

verificar_token();

$id      = entero(post('id'));
$materia = $id !== null ? car_materia_obtener($id) : null;

if ($materia === null) {
    mensaje('error', 'La materia ya no existe.');
    redirigir(car_url_inicio());
}

$ficha   = car_ficha_autorizada((int)$materia['ficha_id']);
$destino = 'materias.php?ficha=' . (int)$ficha['id'];

if ($ficha['estado'] !== 'borrador') {
    mensaje('error', 'La ficha no está en borrador.');
    redirigir($destino);
}

car_materia_eliminar((int)$materia['id']);
mensaje('exito', 'Materia eliminada.');
redirigir($destino);