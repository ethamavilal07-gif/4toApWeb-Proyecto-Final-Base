-- =============================================================================
-- CAR · migracion-publicaciones.sql
-- Versión publicada de cada ficha, separada del borrador que se está editando.
-- Así, al devolver una ficha a borrador, el público sigue viendo la versión
-- publicada hasta que la coordinación publique la nueva.
--
-- Se ejecuta UNA vez sobre una base que ya tenga las tablas car_ (phpMyAdmin:
-- seleccionar explorador_vocacional > Importar). Se puede repetir sin riesgo.
-- Las fichas que ya estaban publicadas generan su versión sola la primera vez
-- que alguien abre el índice público.
-- =============================================================================

USE explorador_vocacional;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS car_publicaciones (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ficha_id       INT UNSIGNED NOT NULL,
    resumen        TEXT NOT NULL,                 -- copia para el índice y la búsqueda
    imagen_portada VARCHAR(255) NULL,             -- copia para las tarjetas del índice
    contenido      LONGTEXT NOT NULL,             -- JSON: ficha, materias, instalaciones, galería, documentos
    publicado_por  INT UNSIGNED NULL,
    publicado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_car_publicaciones_ficha (ficha_id),

    CONSTRAINT fk_car_publicaciones_ficha
        FOREIGN KEY (ficha_id) REFERENCES car_fichas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_car_publicaciones_usuario
        FOREIGN KEY (publicado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
