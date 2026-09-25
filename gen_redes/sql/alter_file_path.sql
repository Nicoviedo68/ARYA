-- Ejecutar en DbGate (Arya / public)
ALTER TABLE public.arya_media_assets
    ALTER COLUMN file_data DROP NOT NULL;

ALTER TABLE public.arya_media_assets
    ADD COLUMN IF NOT EXISTS file_path TEXT;

CREATE INDEX IF NOT EXISTS idx_arya_media_file_path
    ON public.arya_media_assets (file_path)
    WHERE file_path IS NOT NULL;
