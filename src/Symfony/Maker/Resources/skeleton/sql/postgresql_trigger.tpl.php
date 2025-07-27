<?php if (!empty($definition)): ?>
    <?= $definition ?>;
<?php else: ?>
    CREATE OR REPLACE TRIGGER <?= $trigger_name ?> <?= $timing ?> <?= $events ?> ON <?= $table_name ?>
    FOR EACH <?= $scope ?>
    EXECUTE FUNCTION <?= $function_name ?>();
<?php endif; ?>
