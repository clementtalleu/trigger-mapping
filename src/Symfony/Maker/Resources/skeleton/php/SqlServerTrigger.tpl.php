<?php declare(strict_types=1);
echo "<?php\n"; ?>

namespace <?php echo $namespace; ?>;

use Talleu\TriggerMapping\Contract\MySQLTriggerInterface;

class <?php echo $class_name; ?> implements MySQLTriggerInterface
{
    public static function getTrigger(): string
    {
        return <<<SQL
            CREATE OR ALTER TRIGGER <?= $trigger_name ?> ON <?= $table_name ?> AFTER <?= $events ?> AS BEGIN
    <?php if (!empty($content)): ?>
        <?= '            ' . str_replace("\n", "\n            ", trim($content)) ?>
            END
        SQL;
    <?php else: ?>
            -- TODO: Add your SQL logic here
            -- Example: SET NEW.updated_at = NOW();
            SELECT 'Sample Instead of trigger' as [Message]
            END
        SQL;
    <?php endif; ?>
    }
}
