<?php
/**
 * CAR · Instalaciones de una ficha (alta, consulta, modificación)
 * Incluye las fotos del carrusel de la ficha pública (hasta 6).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';

const CARPETA_SUBIDAS = __DIR__ . '/../uploads';
const CAR_VIDEO_MAX_MB = 30;

$fichaId = entero(get('ficha'));
if ($fichaId === null) {
    no_encontrado('Ficha no válida.');
}
$ficha    = car_ficha_autorizada($fichaId);
$editable = $ficha['estado'] === 'borrador';
$volver   = 'instalaciones.php?ficha=' . $fichaId;

$editando = null;
$editarId = entero(get('editar'));
if ($editarId !== null) {
    $editando = car_instalacion_obtener($editarId);
    if ($editando === null || (int)$editando['ficha_id'] !== $fichaId) {
        no_encontrado('La instalación no existe.');
    }
}

$datos = [
    'nombre'      => (string)($editando['nombre']      ?? ''),
    'descripcion' => (string)($editando['descripcion'] ?? ''),
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verificar_token();

    if (!$editable) {
        mensaje('error', 'La ficha no está en borrador; no se puede modificar.');
        redirigir($volver);
    }

    $accion = post('accion');

    /* ---------------------------------------------------------------
     * Fotos del carrusel (6 lugares): subir, reemplazar o borrar
     * ------------------------------------------------------------- */
    if ($accion === 'galeria_subir' || $accion === 'galeria_quitar') {

        // El lugar (1 a 6) se valida en el servidor, no se confía en el formulario.
        $lugar = entero(post('lugar'));
        if ($lugar === null || $lugar < 1 || $lugar > CAR_GALERIA_MAX) {
            mensaje('error', 'Lugar de la galería no válido.');
            redirigir($volver);
        }
        $actual = car_galeria_obtener($fichaId, $lugar);

        if ($accion === 'galeria_quitar') {
            if ($actual !== null) {
                car_galeria_eliminar($fichaId, $lugar);
                car_borrar_archivo(CARPETA_SUBIDAS, $actual['imagen_servidor']);
                mensaje('exito', 'Foto ' . $lugar . ' eliminada.');
            }
            redirigir($volver);
        }

        if (empty($_FILES['imagen']['name'])) {
            mensaje('error', 'Selecciona una imagen para la foto ' . $lugar . '.');
            redirigir($volver);
        }

        try {
            // Tipo real (finfo), tamaño y nombre generado: guardar_archivo().
            $nuevo = guardar_archivo($_FILES['imagen'], CARPETA_SUBIDAS, SOLO_IMAGEN, 3);
            car_galeria_guardar($fichaId, $lugar, $nuevo);
            if ($actual !== null) {
                car_borrar_archivo(CARPETA_SUBIDAS, $actual['imagen_servidor']);
            }
            mensaje('exito', 'Foto ' . $lugar . ' guardada.');
        } catch (RuntimeException $e) {
            mensaje('error', 'Foto ' . $lugar . ': ' . $e->getMessage());
        }
        redirigir($volver);   // POST-Redirect-GET
    }

    /* ---------------------------------------------------------------
     * Instalaciones
     * ------------------------------------------------------------- */
    $instId = entero(post('instalacion_id'));
    $existente = null;
    if ($instId !== null) {
        $existente = car_instalacion_obtener($instId);
        if ($existente === null || (int)$existente['ficha_id'] !== $fichaId) {
            no_encontrado('La instalación no existe.');
        }
    }

    // Borrar solo la imagen de una instalación ya guardada.
    if ($accion === 'quitar_imagen') {
        if ($existente !== null && (!empty($existente['imagen_servidor']) || !empty($existente['video_servidor']))) {
            car_instalacion_actualizar((int)$existente['id'], [
                'nombre'          => $existente['nombre'],
                'descripcion'     => $existente['descripcion'],
                'imagen_servidor' => null,
                'video_servidor'  => null,
            ]);
            car_borrar_archivo(CARPETA_SUBIDAS, $existente['imagen_servidor']);
            car_borrar_archivo(CARPETA_SUBIDAS, $existente['video_servidor'] ?? null);
            mensaje('exito', !empty($existente['video_servidor']) ? 'Video eliminado.' : 'Foto eliminada.');
        }
        redirigir($volver . ($existente !== null ? '&editar=' . (int)$existente['id'] : ''));
    }

    foreach ($datos as $campo => $_) {
        $datos[$campo] = post($campo);
    }

    /* --- Validación en el servidor --- */
    if ($datos['nombre'] === '') {
        $errores['nombre'] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($datos['nombre']) > 150) {
        $errores['nombre'] = 'Máximo 150 caracteres.';
    }

    if ($datos['descripcion'] === '') {
        $errores['descripcion'] = 'La descripción es obligatoria.';
    } elseif (mb_strlen($datos['descripcion']) > 1000) {
        $errores['descripcion'] = 'Máximo 1000 caracteres.';
    }

    /* --- Foto o video (opcional): solo si lo demás es válido --- */
    $imagen = $existente['imagen_servidor'] ?? null;
    $video  = $existente['video_servidor'] ?? null;
    $medioNuevo = null;
    $medioEsVideo = false;
    if (!$errores && !empty($_FILES['medio']['name'])) {
        try {
            // El tipo real decide las reglas: video mp4 hasta 30 MB, foto hasta 2 MB.
            $tipoReal = '';
            if (($_FILES['medio']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_string($_FILES['medio']['tmp_name'] ?? null)) {
                $tipoReal = (string)(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['medio']['tmp_name']);
            }
            if (in_array($tipoReal, SOLO_VIDEO, true)) {
                $medioNuevo = guardar_archivo($_FILES['medio'], CARPETA_SUBIDAS, SOLO_VIDEO, CAR_VIDEO_MAX_MB);
                $medioEsVideo = true;
            } else {
                $medioNuevo = guardar_archivo($_FILES['medio'], CARPETA_SUBIDAS, SOLO_IMAGEN, 2);
            }
        } catch (RuntimeException $e) {
            $errores['medio'] = $e->getMessage();
        }
    }

    /* --- Guardar --- */
    if (!$errores) {
        // Una instalación lleva foto O video: el nuevo reemplaza al otro.
        $imagenFinal = $imagen;
        $videoFinal  = $video;
        if ($medioNuevo !== null) {
            $imagenFinal = $medioEsVideo ? null : $medioNuevo;
            $videoFinal  = $medioEsVideo ? $medioNuevo : null;
        }

        $fila = [
            'nombre'          => $datos['nombre'],
            'descripcion'     => $datos['descripcion'],
            'imagen_servidor' => $imagenFinal,
            'video_servidor'  => $videoFinal,
        ];

        if ($instId === null) {
            car_instalacion_crear($fichaId, $fila);
            mensaje('exito', 'Instalación agregada.');
        } else {
            car_instalacion_actualizar($instId, $fila);
            if ($medioNuevo !== null) {
                // Se borran los anteriores ya guardado el nuevo.
                car_borrar_archivo(CARPETA_SUBIDAS, $imagen);
                car_borrar_archivo(CARPETA_SUBIDAS, $video);
            }
            mensaje('exito', 'Instalación actualizada.');
        }

        redirigir($volver);   // POST-Redirect-GET
    }

    if ($existente !== null) {
        $editando = $existente;
    }
}

$instalaciones = car_instalaciones_de_ficha($fichaId);

// Fotos del carrusel indexadas por lugar, para pintar los 6 espacios.
$fotos = [];
foreach (car_galeria_de_ficha($fichaId) as $f) {
    $fotos[(int)$f['orden']] = $f;
}

cabecera('Instalaciones', $ficha['area'], '../css/modulo.css');
?>

<section class="contenedor seccion">

  <nav aria-label="Ruta">
    <ol class="migas">
      <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
      <li><a href="<?= e(car_url_inicio()) ?>">Carreras</a></li>
      <li>Instalaciones</li>
    </ol>
  </nav>

  <h1>Instalaciones de <?= e($ficha['nombre']) ?></h1>

  <?php pintar_mensajes(); ?>
  <?php car_menu_ficha($ficha, 'instalaciones'); ?>

  <?php if ($errores): ?>
    <div class="alerta alerta--error" role="alert">
      <p><span class="alerta__titulo">Revisa los datos.</span> Hay campos con problemas.</p>
    </div>
  <?php endif; ?>

  <?php if ($editable): ?>
  <form class="formulario panel mb-6" method="POST" action="" enctype="multipart/form-data">
    <?= campo_token() ?>
    <?php if ($editando): ?>
      <input type="hidden" name="instalacion_id" value="<?= (int)$editando['id'] ?>">
    <?php endif; ?>
    <h2><?= $editando ? 'Editar instalación' : 'Agregar instalación' ?></h2>

    <div class="campo <?= isset($errores['nombre']) ? 'campo--invalido' : '' ?>">
      <label class="campo__etiqueta" for="nombre">Nombre *</label>
      <input class="campo__control" type="text" id="nombre" name="nombre"
             maxlength="150" value="<?= e($datos['nombre']) ?>" required>
      <?php if (isset($errores['nombre'])): ?><small class="campo__error"><?= e($errores['nombre']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['descripcion']) ? 'campo--invalido' : '' ?>">
      <label class="campo__etiqueta" for="descripcion">Descripción *</label>
      <textarea class="campo__control" id="descripcion" name="descripcion" rows="3"
                maxlength="1000" required><?= e($datos['descripcion']) ?></textarea>
      <?php if (isset($errores['descripcion'])): ?><small class="campo__error"><?= e($errores['descripcion']) ?></small><?php endif; ?>
    </div>

    <?php
      $tieneVideo = !empty($editando['video_servidor']);
      $tieneFoto  = !empty($editando['imagen_servidor']);
    ?>
    <div class="campo <?= isset($errores['medio']) ? 'campo--invalido' : '' ?>">
      <label class="campo__etiqueta" for="medio">Foto o video</label>
      <img id="vista-imagen" class="car-vista-previa car-vista-previa--corta"
           src="<?= $tieneFoto ? '../uploads/' . e($editando['imagen_servidor']) : '' ?>"
           alt="Vista previa de la foto" <?= $tieneFoto ? '' : 'hidden' ?>>
      <video id="vista-video-inst" class="car-vista-previa car-vista-previa--corta" controls preload="metadata"
             src="<?= $tieneVideo ? '../uploads/' . e($editando['video_servidor']) : '' ?>"
             <?= $tieneVideo ? '' : 'hidden' ?>></video>
      <div class="fila" style="margin-bottom: var(--e3);">
        <?php if ($tieneFoto || $tieneVideo): ?>
          <button class="boton boton--peligro boton--pequeno" type="submit"
                  name="accion" value="quitar_imagen" formnovalidate
                  data-confirmar="¿Borrar <?= $tieneVideo ? 'el video actual' : 'la foto actual' ?>? Se elimina al instante y no se puede deshacer."
                  data-confirmar-boton="Borrar <?= $tieneVideo ? 'video' : 'foto' ?>" data-confirmar-tipo="peligro">
            <?= $tieneVideo ? 'Borrar video actual' : 'Borrar imagen actual' ?>
          </button>
        <?php endif; ?>
        <button class="boton boton--secundario boton--pequeno" type="button"
                id="limpiar-imagen" hidden>Quitar selección</button>
      </div>
      <input class="campo__control" type="file" id="medio" name="medio"
             accept="image/jpeg,image/png,image/webp,video/mp4"
             data-vista="vista-imagen" data-vista-video="vista-video-inst" data-vista-limpiar="limpiar-imagen">
      <small class="campo__ayuda">Foto (JPG, PNG o WebP, máximo 2 MB) o video MP4 (máximo <?= CAR_VIDEO_MAX_MB ?> MB). Se guarda una cosa u otra.</small>
      <?php if (isset($errores['medio'])): ?><small class="campo__error"><?= e($errores['medio']) ?></small><?php endif; ?>
    </div>

    <div class="fila">
      <button class="boton boton--primario" type="submit"><?= $editando ? 'Guardar cambios' : 'Agregar' ?></button>
      <?php if ($editando): ?><a class="boton boton--secundario" href="<?= e($volver) ?>">Cancelar</a><?php endif; ?>
    </div>
  </form>
  <?php else: ?>
    <div class="alerta alerta--info"><p>La ficha no está en borrador: solo puedes consultar las instalaciones.</p></div>
  <?php endif; ?>

  <?php if (!$instalaciones): ?>
    <div class="vacio"><h3>Sin instalaciones</h3><p>Agrega la primera con el formulario.</p></div>
  <?php else: ?>
    <div class="tabla-envoltura">
      <table class="tabla">
        <caption><?= count($instalaciones) ?> instalación<?= count($instalaciones) === 1 ? '' : 'es' ?></caption>
        <thead>
          <tr><th scope="col">Orden</th><th scope="col">Nombre</th><th scope="col">Foto / video</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($instalaciones as $i): ?>
          <tr>
            <td><?= (int)$i['orden'] ?></td>
            <td><?= e($i['nombre']) ?></td>
            <td><?= !empty($i['video_servidor']) ? 'Video' : ($i['imagen_servidor'] ? 'Foto' : '—') ?></td>
            <td>
              <?php if ($editable): ?>
              <div class="tabla__acciones">
                <a class="boton boton--secundario boton--pequeno" href="?ficha=<?= $fichaId ?>&amp;editar=<?= (int)$i['id'] ?>">Editar</a>
                <form method="POST" action="instalaciones_eliminar.php"
                      data-confirmar="¿Eliminar esta instalación? No se puede deshacer."
                      data-confirmar-boton="Eliminar" data-confirmar-tipo="peligro">
                  <?= campo_token() ?>
                  <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                  <button class="boton boton--peligro boton--pequeno" type="submit">Eliminar</button>
                </form>
              </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h2 class="mt-6">Fotos del carrusel</h2>
  <p class="ancho-lectura">
    Hasta <?= CAR_GALERIA_MAX ?> fotos. En la ficha pública se muestran en un carrusel
    que gira solo, con la foto central enfocada. JPG, PNG o WebP, máximo 3 MB cada una.
    <strong><?= count($fotos) ?> de <?= CAR_GALERIA_MAX ?></strong> cargadas.
  </p>

  <div class="car-galeria-admin">
    <?php for ($n = 1; $n <= CAR_GALERIA_MAX; $n++): $foto = $fotos[$n] ?? null; ?>
      <div class="panel car-galeria-admin__lugar">
        <h3 class="car-galeria-admin__titulo">Foto <?= $n ?></h3>

        <img class="car-galeria-admin__imagen" id="vista-galeria-<?= $n ?>"
             src="<?= $foto ? '../uploads/' . e($foto['imagen_servidor']) : '' ?>"
             alt="Foto <?= $n ?> del carrusel" <?= $foto ? '' : 'hidden' ?>>
        <?php if (!$foto): ?>
          <div class="car-galeria-admin__vacio" id="vacio-galeria-<?= $n ?>">Sin foto</div>
        <?php endif; ?>

        <?php if ($editable): ?>
          <form class="formulario" method="POST" action="" enctype="multipart/form-data">
            <?= campo_token() ?>
            <input type="hidden" name="accion" value="galeria_subir">
            <input type="hidden" name="lugar" value="<?= $n ?>">
            <div class="campo">
              <label class="campo__etiqueta" for="imagen-galeria-<?= $n ?>"><?= $foto ? 'Reemplazar' : 'Elegir imagen' ?></label>
              <input class="campo__control" type="file" id="imagen-galeria-<?= $n ?>" name="imagen"
                     accept="image/jpeg,image/png,image/webp" required
                     data-vista="vista-galeria-<?= $n ?>" data-vista-vacio="vacio-galeria-<?= $n ?>"
                     data-vista-limpiar="limpiar-galeria-<?= $n ?>">
            </div>
            <div class="fila">
              <button class="boton boton--primario boton--pequeno" type="submit">
                <?= $foto ? 'Reemplazar' : 'Subir' ?>
              </button>
              <button class="boton boton--secundario boton--pequeno" type="button"
                      id="limpiar-galeria-<?= $n ?>" hidden>Quitar selección</button>
            </div>
          </form>

          <?php if ($foto): ?>
            <form method="POST" action=""
                  data-confirmar="¿Borrar la foto <?= $n ?> del carrusel? No se puede deshacer."
                  data-confirmar-boton="Borrar imagen" data-confirmar-tipo="peligro">
              <?= campo_token() ?>
              <input type="hidden" name="accion" value="galeria_quitar">
              <input type="hidden" name="lugar" value="<?= $n ?>">
              <button class="boton boton--peligro boton--pequeno" type="submit">Borrar imagen actual</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>

</section>

<script src="../js/confirmar.js"></script>
<script src="../js/validar.js"></script>
<script src="../js/vista-previa.js"></script>

<?php
pie();
