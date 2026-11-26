-- =============================================================================
-- CAR · migracion-instalaciones-video.sql
-- Las instalaciones admiten una foto O un video (mp4).
-- Se ejecuta UNA vez sobre una base que ya tenga las tablas car_ (phpMyAdmin:
-- seleccionar explorador_vocacional > Importar). Se puede repetir sin riesgo.
-- =============================================================================

USE explorador_vocacional;
SET NAMES utf8mb4;

ALTER TABLE car_instalaciones
    ADD COLUMN IF NOT EXISTS video_servidor VARCHAR(255) NULL AFTER imagen_servidor;
