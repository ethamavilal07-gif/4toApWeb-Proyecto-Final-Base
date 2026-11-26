<?php
/**
 * CAR · Panel del editor
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';

if (rol() === 'coordinacion') {
    redirigir('bandeja.php');
}

$carreraId = carrera_del_usuario();
if ($carreraId === null) {
    http_response_code(403);
    exit('Tu cuenta de editor no tiene una carrera asignada.');
}

$carrera = carrera($carreraId);
$ficha   = car_ficha_de_carrera($carreraId);

// Si la coordinación la devolvió, se muestra la última observación.
$observacion = null;
if ($ficha !== null && $ficha['estado'] === 'borrador') {
    $historial = car_historial((int)$ficha['id']);
    if ($historial && $historial[0]['estado_nuevo'] === 'borrador' && !empty($historial[0]['observaciones'])) {
        $observacion = $historial[0];
    }
}

cabecera('Panel · Carreras', $carrera['area'] ?? '', '../css/modulo.css');
?>

<section class="contenedor seccion">

  <nav aria-label="Ruta">
    <ol class="migas">
      <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
      <li>Carreras</li>
    </ol>
  </nav>

  <h1>Ficha de <?= e($carrera['nombre'] ?? '') ?></h1>

  <?php pintar_mensajes(); ?>

  <?php if ($observacion): ?>
    <div class="alerta alerta--alerta" role="status">
      <p><span class="alerta__titulo">La coordinación devolvió tu ficha.</span>
        <?= e($observacion['observaciones']) ?></p>
    </div>
  <?php endif; ?>

  <?php $publicadaEn = $ficha !== null ? car_publicada_en((int)$ficha['id']) : null; ?>
  <?php if ($publicadaEn !== null && $ficha['estado'] !== 'publicado'): ?>
    <div class="alerta alerta--info" role="status">
      <p><span class="alerta__titulo">El público sigue viendo la versión publicada</span>
        del <?= e(date('d/m/Y H:i', strtotime($publicadaEn))) ?>.
        Tus cambios se verán cuando la coordinación publique esta versión.
        <a href="../ficha.php?id=<?= (int)$carreraId ?>">Ver la versión publicada</a></p>
    </div>
  <?php endif; ?>

  <?php if ($ficha === null): ?>

    <div class="vacio">
      <h3>Tu carrera todavía no tiene ficha</h3>
      <p class="ancho-lectura">Captura los datos básicos para crearla como borrador.</p>
      <a class="boton boton--primario" href="ficha.php?carrera=<?= (int)$carreraId ?>">Crear ficha</a>
    </div>

  <?php else: ?>

    <div class="panel mb-6">
      <p>
        <strong>Estado:</strong>
        <span class="<?= e(car_clase_estado($ficha['estado'])) ?>"><?= e(CAR_ESTADOS[$ficha['estado']]) ?></span>
      </p>
      <p class="leyenda">Última actualización: <?= e(date('d/m/Y H:i', strtotime($ficha['actualizado_en']))) ?></p>

      <?php car_menu_ficha($ficha, ''); ?>

      <div class="fila">
        <?php if ($ficha['estado'] === 'borrador'): ?>
          <form method="POST" action="cambiar_estado.php"
                data-confirmar="¿Enviar la ficha a revisión? Ya no podrás editarla hasta que la coordinación responda."
                data-confirmar-boton="Enviar a revisión">
            <?= campo_token() ?>
            <input type="hidden" name="ficha" value="<?= (int)$ficha['id'] ?>">
            <input type="hidden" name="nuevo" value="revision">
            <button class="boton boton--primario" type="submit">Enviar a revisión</button>
          </form>
        <?php elseif ($ficha['estado'] === 'revision'): ?>
          <p class="leyenda">En espera de la coordinación.</p>
        <?php else: ?>
          <a class="boton boton--secundario"
            href="../ficha.php?id=<?= (int)$carreraId ?>">Ver ficha pública</a>
        <?php endif; ?>
      </div>
    </div>

  <?php endif; ?>

</section>
<script src="../js/confirmar.js"></script>
<script src="../js/validar.js"></script>
<?php
pie();