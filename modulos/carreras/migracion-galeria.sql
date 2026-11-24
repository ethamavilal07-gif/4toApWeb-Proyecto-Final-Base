-- =============================================================================
-- CAR · migracion-galeria.sql
-- Agrega el fondo de la primera vista y la galería de 6 fotos.
-- Se ejecuta UNA vez sobre una base que ya tenga las tablas car_ (phpMyAdmin:
-- seleccionar explorador_vocacional > Importar). Se puede repetir sin riesgo.
-- =============================================================================

USE explorador_vocacional;
SET NAMES utf8mb4;

ALTER TABLE car_fichas
    ADD COLUMN IF NOT EXISTS imagen_fondo VARCHAR(255) NULL AFTER imagen_portada;

CREATE TABLE IF NOT EXISTS car_galeria (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ficha_id        INT UNSIGNED NOT NULL,
    orden           TINYINT UNSIGNED NOT NULL,
    imagen_servidor VARCHAR(255) NOT NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_car_galeria_ficha_orden (ficha_id, orden),

    CONSTRAINT fk_car_galeria_ficha
        FOREIGN KEY (ficha_id) REFERENCES car_fichas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
