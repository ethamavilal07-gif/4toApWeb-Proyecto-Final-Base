<?php
/**
 * CAR · Baja de un documento. Solo POST, con token y permisos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

verificar_token();

$id  = entero(post('id'));
$doc = $id !== null ? car_documento_obtener($id) : null;

if ($doc === null) {
    mensaje('error', 'El documento ya no existe.');
    redirigir(car_url_inicio());
}

// Permiso derivado del registro, no de lo que mande el navegador.
$ficha   = car_ficha_autorizada((int)$doc['ficha_id']);
$destino = 'documentos.php?ficha=' . (int)$ficha['id'];

if ($ficha['estado'] !== 'borrador') {
    mensaje('error', 'La ficha no está en borrador.');
    redirigir($destino);
}

$archivo = car_documento_eliminar((int)$doc['id']);
if ($archivo !== null) {
    @unlink(car_carpeta_docs() . '/' . $archivo);
}

mensaje('exito', 'Documento eliminado.');
redirigir($destino)