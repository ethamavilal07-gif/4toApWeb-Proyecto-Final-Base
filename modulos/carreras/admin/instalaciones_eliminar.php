<?php
/**
 * CAR · Baja de una instalación. Solo POST, con token y permisos.
 */

declare(strict_types=1);

require_once _DIR_ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once _DIR_ . '/../includes/consultas.php';
require_once _DIR_ . '/../includes/panel.php';

const CARPETA_SUBIDAS = _DIR_ . '/../uploads';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

verificar_token();

$id   = entero(post('id'));
$inst = $id !== null ? car_instalacion_obtener($id) : null;

if ($inst === null) {
    mensaje('error', 'La instalación ya no existe.');
    redirigir(car_url_inicio());
}

$ficha   = car_ficha_autorizada((int)$inst['ficha_id']);
$destino = 'instalaciones.php?ficha=' . (int)$ficha['id'];

if ($ficha['estado'] !== 'borrador') {
    mensaje('error', 'La ficha no está en borrador.');
    redirigir($destino);
}

car_instalacion_eliminar((int)$inst['id']);

if (!empty($inst['imagen_servidor'])) {
    @unlink(CARPETA_SUBIDAS . '/' . $inst['imagen_servidor']);
}

mensaje('exito', 'Instalación eliminada.');
redirigir($destino);