-- Тип содержимого переезжает с blobs на files.
--
-- Зачем: mime и расширение — свойства файла, а не байтов. При дедупликации
-- один блоб обслуживает несколько файлов, и тип первого загрузившего
-- навязывался всем остальным: data.csv и data.txt с одинаковым содержимым
-- обязаны иметь разный Content-Type.
--
-- `call db migrate` существующие таблицы не меняет (пишет [EXIST] и проходит
-- мимо), поэтому перенос — руками. Выполнять целиком, одной транзакцией:
--
--   psql "$DSN" -v ON_ERROR_STOP=1 -f docs/migrations/2026-08-16-mime-to-files.sql
--
-- Если схема не public — сначала: SET search_path TO new;

BEGIN;

ALTER TABLE files
    ADD COLUMN mime_type varchar(127),
    ADD COLUMN extension varchar(32);

UPDATE files f
   SET mime_type = b.mime_type,
       extension = b.extension
  FROM blobs b
 WHERE b.id = f.blob_id;

-- Страховка: NOT NULL ниже упадёт сам, но так видно причину.
DO $$
DECLARE left_over bigint;
BEGIN
    SELECT count(*) INTO left_over FROM files WHERE mime_type IS NULL;
    IF left_over > 0 THEN
        RAISE EXCEPTION 'Осталось % файлов без блоба — переносить нечего', left_over;
    END IF;
END $$;

ALTER TABLE files
    ALTER COLUMN mime_type SET NOT NULL,
    ALTER COLUMN extension SET NOT NULL,
    ALTER COLUMN extension SET DEFAULT '';

-- Обязательный шаг, а не уборка: колонки NOT NULL без значения по умолчанию,
-- а новый код их больше не заполняет — INSERT в blobs упадёт.
ALTER TABLE blobs
    DROP COLUMN mime_type,
    DROP COLUMN extension;

COMMIT;
