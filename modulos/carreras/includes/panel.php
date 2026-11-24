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

/**
 * Datos para incrustar una vista previa del video, o null si la dirección no
 * se puede mostrar (entonces en la ficha pública queda solo como enlace).
 * Reconoce YouTube, Vimeo y archivos .mp4 / .webm / .ogg directos.
 *
 * @return array{tipo:string,src:string}|null
 */
function car_video_embed(?string $url): ?array
{
    $url = trim((string)$url);
    if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
        return null;
    }

    $partes = parse_url($url);
    if ($partes === false || empty($partes['host'])) {
        return null;
    }
    $host = strtolower(preg_replace('/^(www|m)\./i', '', $partes['host']));
    $ruta = $partes['path'] ?? '';

    // YouTube
    if ($host === 'youtube.com' || $host === 'youtu.be') {
        $id = null;
        if ($host === 'youtu.be') {
            $id = ltrim($ruta, '/');
        } elseif (preg_match('#^/(embed|shorts|live)/([A-Za-z0-9_-]{11})#', $ruta, $m)) {
            $id = $m[2];
        } else {
            parse_str($partes['query'] ?? '', $q);
            $id = $q['v'] ?? null;
        }
        if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1) {
            return ['tipo' => 'iframe', 'src' => 'https://www.youtube-nocookie.com/embed/' . $id];
        }
        return null;
    }

    // Vimeo
    if ($host === 'vimeo.com' && preg_match('#^/(\d+)#', $ruta, $m)) {
        return ['tipo' => 'iframe', 'src' => 'https://player.vimeo.com/video/' . $m[1]];
    }

    // Archivo de video directo
    if (preg_match('/\.(mp4|webm|ogg)$/i', $ruta) === 1) {
        return ['tipo' => 'video', 'src' => $url];
    }

    return null;
}
