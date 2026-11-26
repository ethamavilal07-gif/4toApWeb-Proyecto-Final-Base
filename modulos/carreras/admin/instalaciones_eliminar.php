<?php
/**
 * CAR · Baja de una instalación. Solo POST, con token y permisos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';

const CARPETA_SUBIDAS = __DIR__ . '/../uploads';

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

foreach (['imagen_servidor', 'video_servidor'] as $col) {
    if (!empty($inst[$col])) {
        car_borrar_archivo(CARPETA_SUBIDAS, $inst[$col]);
    }
}

mensaje('exito', 'Instalación eliminada.');
redirigir($destino);