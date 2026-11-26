<?php
/**
 * CAR · Bandeja de revisión editorial (solo coordinación)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';

$busqueda = get('q');
$estado   = get('estado');
$filas    = car_listar_bandeja($estado, $busqueda);

cabecera('Bandeja de revisión', '', '../css/modulo.css');
?>

<section class="contenedor seccion">

  <nav aria-label="Ruta">
    <ol class="migas">
      <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
      <li>Bandeja de revisión</li>
    </ol>
  </nav>

  <h1>Bandeja de revisión</h1>

  <?php pintar_mensajes(); ?>

  <form class="panel mb-6 car-filtros" method="GET" action="">
    <div class="fila">
      <div class="campo sin-margen" style="flex:1 1 220px">
        <label class="campo__etiqueta" for="q">Buscar carrera</label>
        <input class="campo__control" type="search" id="q" name="q" value="<?= e($busqueda) ?>">
      </div>

      <div class="campo sin-margen" style="flex:0 1 220px">
        <label class="campo__etiqueta" for="estado">Estado</label>
        <select class="campo__control" id="estado" name="estado">
          <option value="">Todos</option>
          <?php foreach (CAR_ESTADOS as $clave => $texto): ?>
            <option value="<?= e($clave) ?>" <?= $estado === $clave ? 'selected' : '' ?>><?= e($texto) ?></option>
          <?php endforeach; ?>
          <option value="sin_ficha" <?= $estado === 'sin_ficha' ? 'selected' : '' ?>>Sin ficha</option>
        </select>
      </div>

      <button class="boton boton--secundario" type="submit">Filtrar</button>
    </div>
  </form>

  <?php if (!$filas): ?>
    <div class="vacio">
      <h3>No hay carreras con esos filtros</h3>
      <a class="boton boton--secundario" href="?">Quitar filtros</a>
    </div>
  <?php else: ?>
    <div class="tabla-envoltura">
      <table class="tabla">
        <caption><?= count($filas) ?> carrera<?= count($filas) === 1 ? '' : 's' ?></caption>
        <thead>
          <tr>
            <th scope="col">Carrera</th>
            <th scope="col">Estado</th>
            <th scope="col">Última edición</th>
            <th scope="col">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($filas as $f): ?>
          <tr>
            <td><?= e($f['nombre']) ?> <span class="leyenda">(<?= e($f['clave']) ?>)</span></td>
            <td>
              <?php if ($f['estado'] === null): ?>
                <span class="insignia insignia--archivado">Sin ficha</span>
              <?php else: ?>
                <span class="<?= e(car_clase_estado($f['estado'])) ?>"><?= e(CAR_ESTADOS[$f['estado']]) ?></span>
                <?php if ($f['publicada_en'] !== null && $f['estado'] !== 'publicado'): ?>
                  <br><span class="leyenda">Versión publicada visible</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($f['ficha_id'] !== null): ?>
                <?= e(date('d/m/Y H:i', strtotime($f['actualizado_en']))) ?>
                <span class="leyenda">· <?= e($f['actualizado_por'] ?? '—') ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <div class="tabla__acciones">
                <?php if ($f['ficha_id'] !== null): ?>
                  <a class="boton boton--primario boton--pequeno" href="revisar.php?ficha=<?= (int)$f['ficha_id'] ?>">Revisar</a>
                <?php endif; ?>
                <a class="boton boton--secundario boton--pequeno" href="ficha.php?carrera=<?= (int)$f['carrera_id'] ?>">Editar contenido</a>
              </div>
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
