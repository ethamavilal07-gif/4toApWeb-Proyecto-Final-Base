<?php
/**
 * CAR · Revisión de una ficha (solo coordinación)
 * Muestra todo lo que capturó el editor (textos, imágenes, video, galería,
 * plan de estudios con sus unidades, instalaciones y documentos) para que la
 * coordinación pueda revisarlo completo antes de dar su dictamen.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';

$fichaId = entero(get('ficha'));
if ($fichaId === null) {
    no_encontrado('Ficha no válida.');
}
$ficha = car_ficha_autorizada($fichaId);

$materias      = car_materias_de_ficha($fichaId);
$unidades      = car_unidades_de_ficha($fichaId);
$instalaciones = car_instalaciones_de_ficha($fichaId);
$documentos    = car_documentos_de_ficha($fichaId);
$historial     = car_historial($fichaId);

// La galería se acomoda por lugar (1 a 6) para mostrar también los vacíos.
$galeria = [];
foreach (car_galeria_de_ficha($fichaId) as $g) {
    $galeria[(int)$g['orden']] = $g;
}

// Materias agrupadas por cuatrimestre.
$porCuatri = [];
foreach ($materias as $m) {
    $porCuatri[(int)$m['cuatrimestre']][] = $m;
}

// Video: vista previa si es de YouTube, Vimeo o un archivo; si no, solo enlace.
$video       = trim((string)($ficha['video_url'] ?? ''));
$videoValido = $video !== '' && preg_match('#^https?://#i', $video) === 1;
$embed       = car_video_embed($video);

$textosCompletos = trim((string)$ficha['resumen']) !== ''
    && trim((string)$ficha['perfil_egreso']) !== ''
    && trim((string)$ficha['campo_laboral']) !== '';

/** "1 materia", "3 materias"… */
$cuenta = fn(int $n, string $singular, string $plural): string
    => $n . ' ' . ($n === 1 ? $singular : $plural);

/** Tamaño de archivo legible. */
$tamano = fn(int $bytes): string => $bytes >= 1048576
    ? number_format($bytes / 1048576, 1) . ' MB'
    : max(1, (int)round($bytes / 1024)) . ' KB';

// Resumen de la captura: qué apartados tienen contenido.
$apartados = [
    ['Textos', $textosCompletos,
        $textosCompletos ? 'Completos' : 'Incompletos', 'textos'],
    ['Imagen de portada', !empty($ficha['imagen_portada']),
        !empty($ficha['imagen_portada']) ? 'Cargada' : 'Sin imagen', 'imagenes'],
    ['Imagen de fondo', !empty($ficha['imagen_fondo']),
        !empty($ficha['imagen_fondo']) ? 'Cargada' : 'Sin imagen', 'imagenes'],
    ['Video', $videoValido, $videoValido ? 'Con enlace' : 'Sin video', 'imagenes'],
    ['Galería', count($galeria) > 0,
        count($galeria) . ' de ' . CAR_GALERIA_MAX . ' fotos', 'galeria'],
    ['Plan de estudios', count($materias) > 0,
        $cuenta(count($materias), 'materia', 'materias'), 'materias'],
    ['Instalaciones', count($instalaciones) > 0,
        $cuenta(count($instalaciones), 'instalación', 'instalaciones'), 'instalaciones'],
    ['Documentos', count($documentos) > 0,
        $cuenta(count($documentos), 'documento', 'documentos'), 'documentos'],
];

cabecera('Revisar ficha', $ficha['area'], '../css/modulo.css');
?>

<section class="contenedor seccion">

<nav aria-label="Ruta">
    <ol class="migas">
    <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
    <li><a href="bandeja.php">Bandeja de revisión</a></li>
    <li><?= e($ficha['nombre']) ?></li>
    </ol>
</nav>


<div class="fila fila--separada">
    <h1 class="sin-margen"><?= e($ficha['nombre']) ?></h1>
    <span class="<?= e(car_clase_estado($ficha['estado'])) ?>"><?= e(CAR_ESTADOS[$ficha['estado']]) ?></span>
</div>
<p class="leyenda">
    <?= e($ficha['clave']) ?> ·
    <?= e(CAR_AREAS[$ficha['area']] ?? $ficha['area']) ?> ·
    <?= e($ficha['modalidad']) ?> ·
    <?= e($ficha['duracion']) ?> ·
    Última edición: <?= e(date('d/m/Y H:i', strtotime($ficha['actualizado_en']))) ?>
</p>

<?php pintar_mensajes(); ?>

<?php $publicadaEn = car_publicada_en($fichaId); ?>
<?php if ($publicadaEn !== null && $ficha['estado'] !== 'publicado'): ?>
    <div class="alerta alerta--info" role="status">
    <p><span class="alerta__titulo">El público sigue viendo la versión publicada</span>
        del <?= e(date('d/m/Y H:i', strtotime($publicadaEn))) ?>.
        Lo que ves aquí no es visible hasta que se publique.
        <a href="../ficha.php?id=<?= (int)$ficha['carrera_id'] ?>">Ver la versión publicada</a></p>
    </div>
<?php endif; ?>

<div class="panel mb-6">
    <h2>Lo que capturó el editor</h2>
    <p class="leyenda">Revisa cada apartado antes de dar tu dictamen.</p>
    <div class="tabla-envoltura">
    <table class="tabla">
        <thead>
        <tr><th scope="col">Apartado</th><th scope="col">Captura</th></tr>
        </thead>
        <tbody>
        <?php foreach ($apartados as [$nombre, $listo, $texto, $ancla]): ?>
        <tr>
            <td><a href="#<?= e($ancla) ?>"><?= e($nombre) ?></a></td>
            <td><span class="insignia <?= $listo ? 'insignia--publicado' : 'insignia--borrador' ?>"><?= e($texto) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($ficha['estado'] !== 'borrador'): ?>
    <p class="mt-4"><a href="#dictamen">Ir al dictamen</a></p>
    <?php endif; ?>
</div>

<div class="panel mb-6" id="textos">
    <h2>Textos</h2>
    <h3>Resumen</h3>
    <p><?= nl2br(e($ficha['resumen'])) ?></p>
    <h3>Perfil de egreso</h3>
    <p><?= nl2br(e($ficha['perfil_egreso'])) ?></p>
    <h3>Campo laboral</h3>
    <p><?= nl2br(e($ficha['campo_laboral'])) ?></p>
</div>

<div class="panel mb-6" id="imagenes">
    <h2>Imágenes y video</h2>
    <div class="rejilla">
    <div>
        <h3>Imagen de portada</h3>
        <?php if (!empty($ficha['imagen_portada'])): ?>
        <img class="car-vista-previa car-vista-previa--corta" src="../uploads/<?= e($ficha['imagen_portada']) ?>"
            alt="Portada de <?= e($ficha['nombre']) ?>">
        <?php else: ?>
        <p class="leyenda">Sin imagen.</p>
        <?php endif; ?>
    </div>
    <div>
        <h3>Fondo de la primera vista</h3>
        <?php if (!empty($ficha['imagen_fondo'])): ?>
        <img class="car-vista-previa" src="../uploads/<?= e($ficha['imagen_fondo']) ?>"
            alt="Fondo de la primera vista de <?= e($ficha['nombre']) ?>">
        <?php else: ?>
        <p class="leyenda">Sin imagen.</p>
        <?php endif; ?>
    </div>
    </div>

    <h3>Video</h3>
    <?php if ($embed): ?>
    <div class="car-vista-video">
        <?php if ($embed['tipo'] === 'iframe'): ?>
        <iframe src="<?= e($embed['src']) ?>" title="Video de <?= e($ficha['nombre']) ?>" loading="lazy"
                allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        <?php else: ?>
        <video src="<?= e($embed['src']) ?>" controls preload="metadata"></video>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($videoValido): ?>
    <p><a href="<?= e($video) ?>" target="_blank" rel="noopener">Abrir el video en otra pestaña</a></p>
    <?php else: ?>
    <p class="leyenda">El editor no agregó video.</p>
    <?php endif; ?>
</div>

<div class="panel mb-6" id="galeria">
    <h2>Galería <span class="leyenda">· <?= count($galeria) ?> de <?= CAR_GALERIA_MAX ?> fotos</span></h2>
    <div class="car-galeria-admin">
    <?php for ($n = 1; $n <= CAR_GALERIA_MAX; $n++): $foto = $galeria[$n] ?? null; ?>
        <figure class="sin-margen">
        <?php if ($foto): ?>
            <img class="car-galeria-admin__imagen" src="../uploads/<?= e($foto['imagen_servidor']) ?>"
                alt="Foto <?= $n ?> de la galería" loading="lazy">
        <?php else: ?>
            <div class="car-galeria-admin__vacio">Sin foto</div>
        <?php endif; ?>
        <figcaption class="leyenda">Foto <?= $n ?></figcaption>
        </figure>
    <?php endfor; ?>
    </div>
</div>

<div class="panel mb-6" id="materias">
    <h2>Plan de estudios <span class="leyenda">· <?= e($cuenta(count($materias), 'materia', 'materias')) ?></span></h2>
    <?php if (!$porCuatri): ?>
    <p class="leyenda">El editor no ha capturado materias.</p>
    <?php else: ?>
    <?php foreach ($porCuatri as $num => $lista): ?>
        <h3>Cuatrimestre <?= (int)$num ?></h3>
        <div class="tabla-envoltura mb-6">
        <table class="tabla">
            <thead>
            <tr>
                <th scope="col">Clave</th>
                <th scope="col">Materia</th>
                <th scope="col">Horas totales</th>
                <th scope="col">Unidades</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lista as $m): $us = $unidades[(int)$m['id']] ?? []; ?>
            <tr>
                <td><?= $m['clave_materia'] ? e($m['clave_materia']) : '—' ?></td>
                <td>
                <strong><?= e($m['nombre']) ?></strong>
                <?php if ($m['proposito'] || $m['competencia'] || $us): ?>
                    <details>
                    <summary>Propósito, competencia y unidades</summary>
                    <?php if ($m['proposito']): ?>
                        <p><strong>Propósito:</strong> <?= nl2br(e($m['proposito'])) ?></p>
                    <?php endif; ?>
                    <?php if ($m['competencia']): ?>
                        <p><strong>Competencia:</strong> <?= nl2br(e($m['competencia'])) ?></p>
                    <?php endif; ?>
                    <?php if ($us): ?>
                        <ol>
                        <?php foreach ($us as $u): ?>
                            <li>
                            <?= e($u['nombre']) ?>
                            <span class="leyenda">
                                · <?= (int)$u['horas_saber'] ?> h saber
                                + <?= (int)$u['horas_saber_hacer'] ?> h saber hacer
                                = <?= (int)$u['horas_totales'] ?> h
                            </span>
                            </li>
                        <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                    </details>
                <?php endif; ?>
                </td>
                <td><?= $m['horas_totales'] !== null ? (int)$m['horas_totales'] . ' h' : '—' ?></td>
                <td><?= $us ? count($us) : ((int)$m['num_unidades'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="panel mb-6" id="instalaciones">
    <h2>Instalaciones <span class="leyenda">· <?= e($cuenta(count($instalaciones), 'instalación', 'instalaciones')) ?></span></h2>
    <?php if (!$instalaciones): ?>
    <p class="leyenda">El editor no ha capturado instalaciones.</p>
    <?php else: ?>
    <div class="rejilla">
        <?php foreach ($instalaciones as $i): ?>
        <div class="tarjeta">
            <?php if ($i['imagen_servidor']): ?>
            <img class="tarjeta__medio" src="../uploads/<?= e($i['imagen_servidor']) ?>"
                alt="<?= e($i['nombre']) ?>" loading="lazy">
            <?php endif; ?>
            <div class="tarjeta__cuerpo">
            <h3 class="tarjeta__titulo"><?= e($i['nombre']) ?></h3>
            <p><?= nl2br(e($i['descripcion'])) ?></p>
            <?php if (!$i['imagen_servidor']): ?>
                <p class="leyenda">Sin foto.</p>
            <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="panel mb-6" id="documentos">
    <h2>Documentos <span class="leyenda">· <?= e($cuenta(count($documentos), 'documento', 'documentos')) ?></span></h2>
    <?php if (!$documentos): ?>
    <p class="leyenda">El editor no ha subido documentos.</p>
    <?php else: ?>
    <div class="tabla-envoltura">
        <table class="tabla">
        <thead>
            <tr>
            <th scope="col">Título</th>
            <th scope="col">Archivo</th>
            <th scope="col">Tamaño</th>
            <th scope="col">Acceso</th>
            <th scope="col">Descargas</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($documentos as $d): ?>
            <tr>
            <td><a href="../descargar.php?id=<?= (int)$d['id'] ?>"><?= e($d['titulo']) ?></a></td>
            <td><?= e($d['nombre_original']) ?></td>
            <td><?= e($tamano((int)$d['tamano_bytes'])) ?></td>
            <td><?= (int)$d['restringido'] === 1 ? 'Solo con sesión' : 'Público' ?></td>
            <td><?= (int)$d['descargas'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

    <?php if ($ficha['estado'] === 'revision'): ?>
    <div class="panel mb-6" id="dictamen">
        <h2>Dictamen</h2>

        <form method="POST" action="cambiar_estado.php" class="mb-6"
            data-confirmar="¿Publicar esta ficha? Reemplazará a la versión publicada anteriormente, si la hay."
            data-confirmar-boton="Publicar">
        <?= campo_token() ?>
        <input type="hidden" name="ficha" value="<?= (int)$ficha['id'] ?>">
        <input type="hidden" name="nuevo" value="publicado">
        <button class="boton boton--primario" type="submit">Publicar</button>
        </form>

        <form method="POST" action="cambiar_estado.php">
        <?= campo_token() ?>
        <input type="hidden" name="ficha" value="<?= (int)$ficha['id'] ?>">
        <input type="hidden" name="nuevo" value="borrador">
        <div class="campo">
          <label class="campo__etiqueta" for="observaciones">Observaciones para devolver a borrador *</label>
        <textarea class="campo__control" id="observaciones" name="observaciones" rows="3"
                    maxlength="1000" required></textarea>
        </div>
        <button class="boton boton--peligro" type="submit">Devolver a borrador</button>
        </form>
    </div>

<?php elseif ($ficha['estado'] === 'publicado'): ?>
    <div class="panel mb-6" id="dictamen">
        <h2>Retirar publicación</h2>
        <form method="POST" action="cambiar_estado.php"
            data-confirmar="La ficha vuelve a borrador para modificarla. El público seguirá viendo la versión publicada hasta que se publique la nueva. ¿Continuar?"
            data-confirmar-boton="Devolver a borrador">
        <?= campo_token() ?>
        <input type="hidden" name="ficha" value="<?= (int)$ficha['id'] ?>">
        <input type="hidden" name="nuevo" value="borrador">
        <div class="campo">
          <label class="campo__etiqueta" for="observaciones">Qué debe cambiar *</label>
            <textarea class="campo__control" id="observaciones" name="observaciones" rows="2"
                    maxlength="1000" required></textarea>
        </div>
        <button class="boton boton--peligro" type="submit">Devolver a borrador</button>
        </form>
    </div>
<?php else: ?>
    <div class="alerta alerta--info"><p>La ficha está en borrador: el editor aún no la envía a revisión.</p></div>
    <?php endif; ?>

    <h2>Historial</h2>
    <?php if (!$historial): ?>
    <p class="leyenda">Sin cambios de estado registrados.</p>
    <?php else: ?>
    <div class="tabla-envoltura">
        <table class="tabla">
        <thead>
            <tr><th scope="col">Fecha</th><th scope="col">Cambio</th><th scope="col">Quién</th><th scope="col">Observaciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($historial as $h): ?>
            <tr>
            <td><?= e(date('d/m/Y H:i', strtotime($h['creado_en']))) ?></td>
            <td><?= e(CAR_ESTADOS[$h['estado_anterior']]) ?> → <?= e(CAR_ESTADOS[$h['estado_nuevo']]) ?></td>
            <td><?= e($h['usuario'] ?? 'Sistema') ?></td>
            <td><?= e($h['observaciones'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
    </div>
    <?php endif; ?>

</section>
<script src="../js/confirmar.js"></script>
<?php
pie();
