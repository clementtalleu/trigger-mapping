CREATE TRIGGER <?= $trigger_name ?>
    <?= $when ?> <?= $events ?> ON <?= $table_name ?>
    FOR EACH ROW
BEGIN
    -- TODO: Add your SQL logic here
    -- Example: SET NEW.updated_at = NOW();
END;