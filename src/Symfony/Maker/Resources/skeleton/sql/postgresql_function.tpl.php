CREATE OR REPLACE FUNCTION <?= $function_name ?>()
RETURNS trigger AS $$
<?php if (!empty($content)): ?>
    <?= $content ?>
<?php else: ?>
    BEGIN
    -- TODO: Here goes your SQL logic
    -- Example: NEW.updated_at := NOW();
    RETURN <?= $return_value ?>;
    END;
<?php endif; ?>
$$ LANGUAGE plpgsql;