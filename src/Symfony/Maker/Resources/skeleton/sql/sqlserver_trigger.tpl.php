CREATE OR ALTER TRIGGER <?= $trigger_name ?> 
ON <?= $table_name ?> 
AFTER <?= $events ?> 
AS BEGIN
    -- TODO: Add your SQL logic here
    -- Example: SET NEW.updated_at = NOW();
    SELECT 'Sample Instead of trigger' as [Message]
END;