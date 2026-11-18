<?php
/**
 * CAR · Cambio de estado de una ficha. Solo POST.
 * La regla de quién puede pasar de qué estado a cuál vive en
 * car_cambiar_estado() (consultas.php), validada con el rol del servidor.
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

$fichaId = entero(post('ficha'));
$nuevo   = post('nuevo');
$obs     = post('observaciones');

if ($fichaId === null) {
    mensaje('error', 'No se indicó la ficha.');
    redirigir(car_url_inicio());
}

// 404 si no existe; 403 si es un editor de otra carrera.
$ficha  = car_ficha_autorizada($fichaId);
$volver = rol() === 'coordinacion' ? 'revisar.php?ficha=' . $fichaId : 'index.php';

if (mb_strlen($obs) > 1000) {
    mensaje('error', 'Las observaciones no pueden pasar de 1000 caracteres.');
    redirigir($volver);
}

// Antes de enviar a revisión, la ficha debe estar completa.
if ($nuevo === 'revision') {
    if ($ficha['resumen'] === '' || $ficha['perfil_egreso'] === '' || $ficha['campo_laboral'] === '') {
        mensaje('error', 'Completa el resumen, el perfil de egreso y el campo laboral antes de enviar a revisión.');
        redirigir($volver);
    }
}

try {
    car_cambiar_estado($fichaId, $nuevo, $obs !== '' ? $obs : null, (int)usuario()['id'], rol());
    mensaje('exito', 'La ficha ahora está: ' . CAR_ESTADOS[$nuevo] . '.');
} catch (InvalidArgumentException | RuntimeException $e) {
    mensaje('error', $e->getMessage());       // mensajes propios, seguros de mostrar
} catch (PDOException $e) {
    error_log('Error al cambiar estado: ' . $e->getMessage());
    mensaje('error', 'No se pudo cambiar el estado. Intenta de nuevo.');
}

redirigir(rol() === 'coordinacion' ? 'bandeja.php' : 'index.php');   // POST-Redirect-GET
