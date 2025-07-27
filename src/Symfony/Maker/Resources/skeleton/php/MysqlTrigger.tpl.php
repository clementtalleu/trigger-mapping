<?php declare(strict_types=1);
echo "<?php\n"; ?>

namespace <?php echo $namespace; ?>;

use Talleu\TriggerMapping\Contract\MySQLTriggerInterface;

class <?php echo $class_name; ?> implements MySQLTriggerInterface
{
    public static function getTrigger(): string
    {
        return <<<SQL
            CREATE TRIGGER <?= $trigger_name ?> <?= $timing ?> <?= $events ?> ON <?= $table_name ?> FOR EACH ROW
    <?php if (!empty($content)): ?>
        <?= '            ' . str_replace("\n", "\n            ", trim($content)) ?>
        
        SQL;
    <?php else: ?>
            BEGIN
                -- TODO: Add your SQL logic here
                -- Example: SET NEW.updated_at = NOW();
                END
            SQL;
    <?php endif; ?>
    }
}
