<?php
/**
 * CAR · Ayudas comunes del panel
 * Las usan todas las páginas de admin/.
 */

declare(strict_types=1);

/**
 * Carga una ficha por id. 404 si no existe; 403 si el editor no es de esa
 * carrera. Se llama SIEMPRE antes de leer o escribir algo ligado a la ficha.
 */
function car_ficha_autorizada(int $fichaId): array
{
    $ficha = car_ficha_obtener($fichaId);
    if ($ficha === null) {
        no_encontrado('La ficha no existe.');
    }
    requerir_carrera((int)$ficha['carrera_id']);
    return $ficha;
}

/** Página de inicio del panel según el rol. */
function car_url_inicio(): string
{
    return rol() === 'coordinacion' ? 'bandeja.php' : 'index.php';
}

/**
 * Carpeta de los PDF. Se crea con un .htaccess que prohíbe el acceso directo:
 * los PDF solo se entregan por descargar.php, que verifica permisos.
 */
function car_carpeta_docs(): string
{
    $carpeta = __DIR__ . '/../uploads/docs';
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0775, true);
    }
    $ht = $carpeta . '/.htaccess';
    if (!is_file($ht)) {
        file_put_contents($ht, "Require all denied\n");
    }
    return $carpeta;
}

/** Barra de navegación entre las secciones de una ficha. */
function car_menu_ficha(array $ficha, string $actual): void
{
    $enlaces = [
        'ficha'         => ['ficha.php?carrera=' . (int)$ficha['carrera_id'], 'Ficha'],
        'materias'      => ['materias.php?ficha=' . (int)$ficha['id'], 'Materias'],
        'instalaciones' => ['instalaciones.php?ficha=' . (int)$ficha['id'], 'Instalaciones'],
        'documentos'    => ['documentos.php?ficha=' . (int)$ficha['id'], 'Documentos'],
    ];

    echo '<div class="fila mb-6">';
    foreach ($enlaces as $clave => [$url, $texto]) {
        $clase = $clave === $actual
            ? 'boton boton--primario boton--pequeno'
            : 'boton boton--secundario boton--pequeno';
        printf('<a class="%s" href="%s">%s</a>', $clase, e($url), e($texto));
    }
    echo '</div>';
}