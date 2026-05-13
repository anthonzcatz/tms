<?php
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

$rows = Database::fetchAll(
    "SELECT user_id, profile_image FROM user_accounts WHERE profile_image LIKE '/api/images/users/%'"
);

if (empty($rows)) {
    echo "No rows to update.\n";
    exit;
}

foreach ($rows as $r) {
    $newPath = str_replace('/api/images/users/', '/resources/profile-images/', $r['profile_image']);
    Database::execute(
        "UPDATE user_accounts SET profile_image = :p WHERE user_id = :id",
        ['p' => $newPath, 'id' => $r['user_id']]
    );
    echo "Updated user_id {$r['user_id']}: {$r['profile_image']} -> {$newPath}\n";
}

echo "Done.\n";
