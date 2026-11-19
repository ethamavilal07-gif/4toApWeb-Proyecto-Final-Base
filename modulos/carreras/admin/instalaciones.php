<?php
/**
 * CAR · Instalaciones de una ficha (alta, consulta, modificación)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';

const CARPETA_SUBIDAS = __DIR__ . '/../uploads';

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
    'orden'       => (string)($editando['orden']       ?? '1'),
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verificar_token();

    if (!$editable) {
        mensaje('error', 'La ficha no está en borrador; no se puede modificar.');
        redirigir($volver);
    }

    $instId = entero(post('instalacion_id'));
    $existente = null;
    if ($instId !== null) {
        $existente = car_instalacion_obtener($instId);
        if ($existente === null || (int)$existente['ficha_id'] !== $fichaId) {
            no_encontrado('La instalación no existe.');
        }
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

    $orden = filter_var($datos['orden'] === '' ? '1' : $datos['orden'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    if ($orden === false) {
        $errores['orden'] = 'El orden debe ser un número entre 1 y 99.';
    }

    /* --- Imagen (opcional): solo si lo demás es válido --- */
    $imagen = $existente['imagen_servidor'] ?? null;
    $imagenNueva = null;
    if (!$errores && !empty($_FILES['imagen']['name'])) {
        try {
            $imagenNueva = guardar_archivo($_FILES['imagen'], CARPETA_SUBIDAS, SOLO_IMAGEN, 2);
        } catch (RuntimeException $e) {
            $errores['imagen'] = $e->getMessage();
        }
    }

    /* --- Guardar --- */
    if (!$errores) {
        $fila = [
            'nombre'          => $datos['nombre'],
            'descripcion'     => $datos['descripcion'],
            'imagen_servidor' => $imagenNueva ?? $imagen,
            'orden'           => $orden,
        ];

        if ($instId === null) {
            car_instalacion_crear($fichaId, $fila);
            mensaje('exito', 'Instalación agregada.');
        } else {
            car_instalacion_actualizar($instId, $fila);
            if ($imagenNueva !== null && !empty($imagen)) {
                @unlink(CARPETA_SUBIDAS . '/' . $imagen);   // se borra la anterior ya guardada la nueva
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

    <div class="campo <?= isset($errores['orden']) ? 'campo--invalido' : '' ?>" style="max-width:140px">
      <label class="campo__etiqueta" for="orden">Orden</label>
      <input class="campo__control" type="number" id="orden" name="orden"
             min="1" max="99" value="<?= e($datos['orden']) ?>">
      <?php if (isset($errores['orden'])): ?><small class="campo__error"><?= e($errores['orden']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['imagen']) ? 'campo--invalido' : '' ?>">
      <label class="campo__etiqueta" for="imagen">Imagen</label>
      <?php if (!empty($editando['imagen_servidor'])): ?>
        <img src="../uploads/<?= e($editando['imagen_servidor']) ?>" alt="Imagen actual"
             style="max-width:220px;border-radius:6px;margin-bottom:8px">
      <?php endif; ?>
      <input class="campo__control" type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
      <small class="campo__ayuda">JPG, PNG o WebP. Máximo 2 MB.</small>
      <?php if (isset($errores['imagen'])): ?><small class="campo__error"><?= e($errores['imagen']) ?></small><?php endif; ?>
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
          <tr><th scope="col">Orden</th><th scope="col">Nombre</th><th scope="col">Imagen</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($instalaciones as $i): ?>
          <tr>
            <td><?= (int)$i['orden'] ?></td>
            <td><?= e($i['nombre']) ?></td>
            <td><?= $i['imagen_servidor'] ? 'Sí' : '—' ?></td>
            <td>
              <?php if ($editable): ?>
              <div class="tabla__acciones">
                <a class="boton boton--secundario boton--pequeno" href="?ficha=<?= $fichaId ?>&amp;editar=<?= (int)$i['id'] ?>">Editar</a>
                <form method="POST" action="instalaciones_eliminar.php"
                      onsubmit="return confirm('¿Eliminar esta instalación? No se puede deshacer.')">
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

</section>

<?php
pie();