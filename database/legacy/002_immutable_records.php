<?php
declare(strict_types=1);

$statements = [];
foreach (['revisions','revision_lines','audit_logs','asset_events'] as $table) {
    foreach (['UPDATE','DELETE'] as $action) {
        $name = $table . '_no_' . strtolower($action);
        $statements[] = [
            'sql'=>"CREATE TRIGGER $name BEFORE $action ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Historical records are immutable; append a new record'",
            'exists_sql'=>'SELECT COUNT(*) AS total FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=?',
            'exists_params'=>[$name],
        ];
    }
}
return $statements;
