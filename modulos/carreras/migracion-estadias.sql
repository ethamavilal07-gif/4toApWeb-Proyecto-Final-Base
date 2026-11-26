-- =============================================================================
-- CAR · migracion-estadias.sql
-- Agrega los cuatrimestres de ESTADÍA (prácticas): en ellos no se capturan materias.
-- Se ejecuta UNA vez sobre una base que ya tenga las tablas car_ (phpMyAdmin:
-- seleccionar explorador_vocacional > Importar). Se puede repetir sin riesgo.
-- =============================================================================

USE explorador_vocacional;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS car_estadias (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ficha_id     INT UNSIGNED NOT NULL,
    cuatrimestre TINYINT UNSIGNED NOT NULL,

    UNIQUE KEY uq_car_estadias_ficha_cuatri (ficha_id, cuatrimestre),

    CONSTRAINT fk_car_estadias_ficha
        FOREIGN KEY (ficha_id) REFERENCES car_fichas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
