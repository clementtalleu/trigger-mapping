<?php declare(strict_types=1);
echo "<?php\n"; ?>

namespace <?php echo $namespace; ?>;

use Talleu\TriggerMapping\Contract\PostgreSQLTriggerInterface;

class <?php echo $class_name; ?> implements PostgreSQLTriggerInterface
{
    public static function getTrigger(): string
    {
<?php if (!empty($definition)): ?>
        return <<<SQL
            <?= $definition ?>

        SQL;
<?php else: ?>
        return <<<SQL
            CREATE OR REPLACE TRIGGER <?= $trigger_name ?> <?= $when ?> <?= $events ?> ON <?= $table_name ?>
            FOR EACH <?= $scope ?>
            EXECUTE FUNCTION <?= $function_name ?>();
            SQL;
    <?php endif; ?>
    }

    public static function getFunction(): string
    {
        return <<<SQL
            CREATE OR REPLACE FUNCTION <?= $function_name ?>()
            RETURNS trigger AS $$
<?php if (!empty($content)): ?>
    <?= '            ' . str_replace("\n", "\n            ", trim($content)) ?>
<?php else: ?>
        BEGIN
                -- TODO: Add your PostgreSQL logic here
                -- Example: NEW.updated_at := NOW();
                RETURN <?= $return_value ?>;
            END;
        <?php endif; ?>
        $$ LANGUAGE plpgsql;
        SQL;
    }
}
