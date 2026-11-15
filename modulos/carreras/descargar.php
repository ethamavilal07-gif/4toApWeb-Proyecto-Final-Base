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

$doc = car_documento_obtener($id);
if ($doc === null) {
    no_encontrado('El documento no existe.');
}

// 1. Si la ficha NO está publicada, solo el editor de esa carrera o la
//    coordinación pueden bajar el documento.
if ($doc['ficha_estado'] !== 'publicado') {
    requerir_carrera((int)$doc['carrera_id']);
}

// 2. Si el documento está marcado como restringido, exige sesión.
if ((int)$doc['restringido'] === 1) {
    requerir_sesion();
}

car_documento_sumar_descarga($id);
entregar_archivo(__DIR__ . '/uploads/docs/' . $doc['archivo_servidor'], $doc['nombre_original']);