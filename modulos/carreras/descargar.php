<?php
/**
 * CAR · Descarga del plan de estudios
 * Verifica permisos ANTES de entregar y cuenta la descarga.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';
require_once __DIR__ . '/includes/consultas.php';

$id = entero(get('id'));
if ($id === null) {
    no_encontrado('Documento no válido.');
}

$vivo      = car_documento_obtener($id);
$publicado = car_documento_publicado($id);   // dentro de la versión que ve el público

if ($vivo === null && $publicado === null) {
    no_encontrado('El documento no existe.');
}

if ($publicado !== null) {
    // 1. Forma parte de la versión publicada: lo puede bajar el público, aunque
    //    el borrador se esté modificando (o el documento ya se haya quitado de él).
    $doc = $publicado;
} else {
    // 2. Solo está en el borrador: únicamente el editor de esa carrera o la coordinación.
    requerir_carrera((int)$vivo['carrera_id']);
    $doc = $vivo;
}

// 3. Si el documento está marcado como restringido, exige sesión.
if ((int)$doc['restringido'] === 1) {
    requerir_sesion();
}

if ($vivo !== null) {
    car_documento_sumar_descarga($id);
}
entregar_archivo(__DIR__ . '/uploads/docs/' . $doc['archivo_servidor'], $doc['nombre_original']);