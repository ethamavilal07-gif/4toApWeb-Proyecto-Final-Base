<?php
/**
 * CAR · Alta y edición de la ficha (textos y portada)
 * Se identifica por ?carrera=ID. Solo se edita en borrador.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';

const CARPETA_SUBIDAS = __DIR__ . '/../uploads';

$carreraId = entero(get('carrera'));
$carrera   = $carreraId !== null ? carrera($carreraId) : null;
if ($carrera === null) {
    no_encontrado('Carrera no válida.');
}

// Un editor solo trabaja sobre su carrera.
requerir_carrera($carreraId);

$ficha = car_ficha_de_carrera($carreraId);

if ($ficha !== null && $ficha['estado'] !== 'borrador') {
    mensaje('alerta', 'La ficha está en «' . CAR_ESTADOS[$ficha['estado']] . '». Solo se edita en borrador.');
    redirigir(car_url_inicio());
}

$datos = [
    'resumen'       => $ficha['resumen']       ?? '',
    'perfil_egreso' => $ficha['perfil_egreso'] ?? '',
    'campo_laboral' => $ficha['campo_laboral'] ?? '',
    'video_url'     => $ficha['video_url']     ?? '',
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verificar_token();

    foreach ($datos as $campo => $_) {
        $datos[$campo] = post($campo);
    }

    // Botón "Quitar video": se vacía la dirección y se guarda.
    if (!empty($_POST['quitar_video'])) {
        $datos['video_url'] = '';
    }

    /* --- Validación en el servidor --- */
    if ($datos['resumen'] === '') {
        $errores['resumen'] = 'El resumen es obligatorio.';
    } elseif (mb_strlen($datos['resumen']) > 2000) {
        $errores['resumen'] = 'El resumen no puede pasar de 2000 caracteres.';
    }

    if ($datos['perfil_egreso'] === '') {
        $errores['perfil_egreso'] = 'El perfil de egreso es obligatorio.';
    } elseif (mb_strlen($datos['perfil_egreso']) > 2000) {
        $errores['perfil_egreso'] = 'Máximo 2000 caracteres.';
    }

    if ($datos['campo_laboral'] === '') {
        $errores['campo_laboral'] = 'El campo laboral es obligatorio.';
    } elseif (mb_strlen($datos['campo_laboral']) > 2000) {
        $errores['campo_laboral'] = 'Máximo 2000 caracteres.';
    }

    if ($datos['video_url'] !== '') {
        if (mb_strlen($datos['video_url']) > 255
            || !preg_match('#^https?://#i', $datos['video_url'])
            || filter_var($datos['video_url'], FILTER_VALIDATE_URL) === false) {
            $errores['video_url'] = 'Escribe una dirección que empiece con http:// o https://';
        }
    }
    $datos['video_url'] = $datos['video_url'] !== '' ? $datos['video_url'] : null;

    /* --- Portada (opcional). Solo se guarda si lo demás es válido --- */
    $nuevaPortada = null;
    $quitarPortada = !empty($_POST['quitar_portada']);
    if (!$errores && !empty($_FILES['portada']['name'])) {
        try {
            $nuevaPortada = guardar_archivo($_FILES['portada'], CARPETA_SUBIDAS, SOLO_IMAGEN, 2);
        } catch (RuntimeException $e) {
            $errores['portada'] = $e->getMessage();
        }
    }

    /* --- Fondo de la primera vista (opcional) --- */
    $nuevoFondo = null;
    $quitarFondo = !empty($_POST['quitar_fondo']);
    if (!$errores && !empty($_FILES['fondo']['name'])) {
        try {
            $nuevoFondo = guardar_archivo($_FILES['fondo'], CARPETA_SUBIDAS, SOLO_IMAGEN, 3);
        } catch (RuntimeException $e) {
            $errores['fondo'] = $e->getMessage();
            if ($nuevaPortada !== null) {            // no dejar archivos huérfanos
                @unlink(CARPETA_SUBIDAS . '/' . $nuevaPortada);
                $nuevaPortada = null;
            }
        }
    }

    /* --- Guardar --- */
    if (!$errores) {
        $uid     = (int)usuario()['id'];
        $fichaId = $ficha !== null ? (int)$ficha['id'] : car_asegurar_ficha($carreraId, $uid);

        car_actualizar_ficha($fichaId, $datos, $uid);

        if ($nuevaPortada !== null) {
            car_guardar_portada($fichaId, $nuevaPortada);
            if (!empty($ficha['imagen_portada'])) {
                @unlink(CARPETA_SUBIDAS . '/' . $ficha['imagen_portada']);
            }
        } elseif ($quitarPortada && !empty($ficha['imagen_portada'])) {
            car_guardar_portada($fichaId, null);
            @unlink(CARPETA_SUBIDAS . '/' . $ficha['imagen_portada']);
        }

        if ($nuevoFondo !== null) {
            car_guardar_fondo($fichaId, $nuevoFondo);
            if (!empty($ficha['imagen_fondo'])) {
                @unlink(CARPETA_SUBIDAS . '/' . $ficha['imagen_fondo']);
            }
        } elseif ($quitarFondo && !empty($ficha['imagen_fondo'])) {
            car_guardar_fondo($fichaId, null);
            @unlink(CARPETA_SUBIDAS . '/' . $ficha['imagen_fondo']);
        }

        mensaje('exito', 'Ficha guardada.');
        redirigir('ficha.php?carrera=' . $carreraId);   // POST-Redirect-GET
    }
}

cabecera('Editar ficha', $carrera['area'] ?? '', '../css/modulo.css');
?>

<section class="contenedor seccion">

<nav aria-label="Ruta">
    <ol class="migas">
    <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
    <li><a href="<?= e(car_url_inicio()) ?>">Carreras</a></li>
    <li>Ficha</li>
</ol>
</nav>

<h1>Ficha de <?= e($carrera['nombre']) ?></h1>

<?php pintar_mensajes(); ?>

<?php if ($ficha !== null) { car_menu_ficha($ficha, 'ficha'); } ?>

<?php if ($errores): ?>
    <div class="alerta alerta--error" role="alert">
    <p><span class="alerta__titulo">Revisa los datos.</span>
        Hay <?= count($errores) ?> campo<?= count($errores) === 1 ? '' : 's' ?> con problemas.</p>
    </div>
<?php endif; ?>

<form class="formulario" method="POST" action="" enctype="multipart/form-data">
    <?= campo_token() ?>

    <fieldset class="grupo-campos">
    <legend class="grupo-campos__titulo">Contenido de la ficha</legend>

    <div class="campo <?= isset($errores['resumen']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="resumen">Resumen *</label>
        <textarea class="campo__control" id="resumen" name="resumen" rows="3"
                maxlength="2000" required><?= e($datos['resumen']) ?></textarea>
        <small class="campo__ayuda">Se muestra en la tarjeta del índice. Máximo 2000 caracteres.</small>
        <?php if (isset($errores['resumen'])): ?><small class="campo__error"><?= e($errores['resumen']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['perfil_egreso']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="perfil_egreso">Perfil de egreso *</label>
        <textarea class="campo__control" id="perfil_egreso" name="perfil_egreso" rows="5"
                maxlength="2000" required><?= e($datos['perfil_egreso']) ?></textarea>
        <?php if (isset($errores['perfil_egreso'])): ?><small class="campo__error"><?= e($errores['perfil_egreso']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['campo_laboral']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="campo_laboral">Campo laboral *</label>
        <textarea class="campo__control" id="campo_laboral" name="campo_laboral" rows="5"
                maxlength="2000" required><?= e($datos['campo_laboral']) ?></textarea>
        <?php if (isset($errores['campo_laboral'])): ?><small class="campo__error"><?= e($errores['campo_laboral']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['video_url']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="video_url">Video (dirección)</label>
        <?php $embed = car_video_embed((string)$datos['video_url']); ?>
        <div id="vista-video" class="car-vista-video" <?= $embed ? '' : 'hidden' ?>>
        <?php if ($embed && $embed['tipo'] === 'iframe'): ?>
            <iframe src="<?= e($embed['src']) ?>" title="Vista previa del video" loading="lazy"
                    allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        <?php elseif ($embed): ?>
            <video src="<?= e($embed['src']) ?>" controls preload="metadata"></video>
        <?php endif; ?>
        </div>
        <?php if (!empty($ficha['video_url'])): ?>
        <div class="fila" style="margin-bottom: var(--e3);">
            <button class="boton boton--peligro boton--pequeno" type="submit"
                    name="quitar_video" value="1" formnovalidate
                    data-confirmar="¿Quitar el video de la ficha? Se elimina al instante."
                    data-confirmar-boton="Quitar video" data-confirmar-tipo="peligro">
                Borrar video actual
            </button>
        </div>
        <?php endif; ?>
        <input class="campo__control" type="url" id="video_url" name="video_url"
            value="<?= e((string)$datos['video_url']) ?>" maxlength="255" placeholder="https://"
            data-vista-video="vista-video">
        <small class="campo__ayuda">Enlace de YouTube, Vimeo o un archivo .mp4. La vista previa se actualiza al escribir.</small>
        <?php if (isset($errores['video_url'])): ?><small class="campo__error"><?= e($errores['video_url']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['portada']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="portada">Imagen de portada</label>
        <img id="vista-portada" class="car-vista-previa car-vista-previa--corta"
            src="<?= !empty($ficha['imagen_portada']) ? '../uploads/' . e($ficha['imagen_portada']) : '' ?>"
            alt="Vista previa de la portada" <?= empty($ficha['imagen_portada']) ? 'hidden' : '' ?>>
        <div class="fila" style="margin-bottom: var(--e3);">
            <?php if (!empty($ficha['imagen_portada'])): ?>
            <button class="boton boton--peligro boton--pequeno" type="submit"
                    name="quitar_portada" value="1" formnovalidate
                    data-confirmar="¿Borrar la imagen actual? Se elimina al instante y no se puede deshacer."
                    data-confirmar-boton="Borrar imagen" data-confirmar-tipo="peligro">
                Borrar imagen actual
            </button>
            <?php endif; ?>
            <button class="boton boton--secundario boton--pequeno" type="button"
                    id="limpiar-portada" hidden>Quitar selección</button>
        </div>
        <input class="campo__control" type="file" id="portada" name="portada"
            accept="image/jpeg,image/png,image/webp" data-vista="vista-portada" data-vista-limpiar="limpiar-portada">
        <small class="campo__ayuda">JPG, PNG o WebP. Máximo 2 MB.</small>
        <?php if (isset($errores['portada'])): ?><small class="campo__error"><?= e($errores['portada']) ?></small><?php endif; ?>
    </div>
    <div class="campo <?= isset($errores['fondo']) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="fondo">Imagen de fondo de la primera vista</label>
        <img id="vista-fondo" class="car-vista-previa"
            src="<?= !empty($ficha['imagen_fondo']) ? '../uploads/' . e($ficha['imagen_fondo']) : '' ?>"
            alt="Vista previa del fondo" <?= empty($ficha['imagen_fondo']) ? 'hidden' : '' ?>>
        <div class="fila" style="margin-bottom: var(--e3);">
            <?php if (!empty($ficha['imagen_fondo'])): ?>
            <button class="boton boton--peligro boton--pequeno" type="submit"
                    name="quitar_fondo" value="1" formnovalidate
                    data-confirmar="¿Borrar la imagen actual? Se elimina al instante y no se puede deshacer."
                    data-confirmar-boton="Borrar imagen" data-confirmar-tipo="peligro">
                Borrar imagen actual
            </button>
            <?php endif; ?>
            <button class="boton boton--secundario boton--pequeno" type="button"
                    id="limpiar-fondo" hidden>Quitar selección</button>
        </div>
        <input class="campo__control" type="file" id="fondo" name="fondo"
            accept="image/jpeg,image/png,image/webp" data-vista="vista-fondo" data-vista-limpiar="limpiar-fondo">
        <small class="campo__ayuda">Es el fondo del encabezado de la ficha. Se recomienda una foto horizontal. JPG, PNG o WebP. Máximo 3 MB.</small>
        <?php if (isset($errores['fondo'])): ?><small class="campo__error"><?= e($errores['fondo']) ?></small><?php endif; ?>
    </div>
    </fieldset>

    <div class="fila">
    <button class="boton boton--primario" type="submit">Guardar</button>
    <a class="boton boton--secundario" href="<?= e(car_url_inicio()) ?>">Volver</a>
    </div>
</form>

</section>

<script src="../js/confirmar.js"></script>
<script src="../js/validar.js"></script>
<script src="../js/vista-previa.js"></script>
<script src="../js/vista-video.js"></script>
<?php
pie();
