<?php
declare(strict_types=1);

$engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
return [
    "CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(80) NOT NULL UNIQUE,
        email VARCHAR(254) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(40) NOT NULL, avatar_seed CHAR(32) NOT NULL, active BOOLEAN NOT NULL DEFAULT TRUE,
        auth_version INT NOT NULL DEFAULT 1, failed_logins INT NOT NULL DEFAULT 0, locked_until DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $engine",
    "CREATE TABLE IF NOT EXISTS audit_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL,
        action VARCHAR(80) NOT NULL, entity_type VARCHAR(40) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL,
        details JSON NOT NULL, ip_address VARCHAR(45) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(entity_type,entity_id,created_at), FOREIGN KEY(user_id) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT NOT NULL
    ) $engine",
    "CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bucket CHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(bucket,created_at)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS passkeys (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
        credential_id VARBINARY(1024) NOT NULL, credential_hash BINARY(32) NOT NULL UNIQUE,
        public_key TEXT NOT NULL, sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0, label VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, revoked_at DATETIME NULL,
        FOREIGN KEY(user_id) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS password_resets (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS shows (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, theatre VARCHAR(200) NOT NULL,
        lighting_designer VARCHAR(160) NOT NULL DEFAULT '', lighting_designer_email VARCHAR(254) NULL, lighting_designer_phone VARCHAR(40) NULL,
        assistant_lighting_designer VARCHAR(160) NOT NULL DEFAULT '', assistant_lighting_designer_email VARCHAR(254) NULL, assistant_lighting_designer_phone VARCHAR(40) NULL,
        production_electrician VARCHAR(160) NOT NULL DEFAULT '', production_electrician_email VARCHAR(254) NULL, production_electrician_phone VARCHAR(40) NULL,
        prep_start DATE NOT NULL, prep_end DATE NOT NULL, load_in_start DATE NOT NULL, load_in_end DATE NOT NULL,
        tech_start DATE NOT NULL, tech_end DATE NOT NULL, opening_night DATE NOT NULL, strike_date DATE NOT NULL, return_date DATE NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Planning', created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(status,return_date), FOREIGN KEY(created_by) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS folders (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, parent_id BIGINT UNSIGNED NULL, name VARCHAR(160) NOT NULL,
        sort_position INT NOT NULL DEFAULT 0, FOREIGN KEY(parent_id) REFERENCES folders(id), INDEX(parent_id,sort_position)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS item_types (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, folder_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL,
        prefix VARCHAR(24) NOT NULL UNIQUE, sort_position INT NOT NULL DEFAULT 0,
        FOREIGN KEY(folder_id) REFERENCES folders(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS assets (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, item_type_id BIGINT UNSIGNED NOT NULL,
        asset_tag VARCHAR(80) NOT NULL UNIQUE, serial_number VARCHAR(160) NULL,
        state VARCHAR(30) NOT NULL DEFAULT 'Available', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(item_type_id) REFERENCES item_types(id), INDEX(item_type_id,state)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS packages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL UNIQUE,
        description TEXT NULL, created_by BIGINT UNSIGNED NOT NULL, FOREIGN KEY(created_by) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS package_components (
        package_id BIGINT UNSIGNED NOT NULL, item_type_id BIGINT UNSIGNED NOT NULL,
        quantity INT UNSIGNED NOT NULL, PRIMARY KEY(package_id,item_type_id),
        FOREIGN KEY(package_id) REFERENCES packages(id), FOREIGN KEY(item_type_id) REFERENCES item_types(id),
        CHECK(quantity > 0)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS equipment_rules (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL,
        source_type_id BIGINT UNSIGNED NOT NULL, source_quantity INT UNSIGNED NOT NULL,
        dependency_type_id BIGINT UNSIGNED NOT NULL, dependency_quantity INT UNSIGNED NOT NULL,
        mode VARCHAR(10) NOT NULL DEFAULT 'warning', active BOOLEAN NOT NULL DEFAULT TRUE,
        FOREIGN KEY(source_type_id) REFERENCES item_types(id), FOREIGN KEY(dependency_type_id) REFERENCES item_types(id),
        CHECK(source_quantity > 0), CHECK(dependency_quantity > 0), CHECK(mode IN ('warning','strict'))
    ) $engine",
    "CREATE TABLE IF NOT EXISTS shop_orders (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, show_id BIGINT UNSIGNED NOT NULL,
        number VARCHAR(40) NOT NULL UNIQUE, title VARCHAR(200) NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(show_id) REFERENCES shows(id), FOREIGN KEY(created_by) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS revisions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL,
        revision_number INT UNSIGNED NOT NULL, notes TEXT NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(order_id,revision_number),
        FOREIGN KEY(order_id) REFERENCES shop_orders(id), FOREIGN KEY(created_by) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS revision_lines (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, revision_id BIGINT UNSIGNED NOT NULL,
        item_type_id BIGINT UNSIGNED NOT NULL, quantity INT UNSIGNED NOT NULL,
        package_id BIGINT UNSIGNED NULL, start_date DATE NOT NULL, return_date DATE NOT NULL,
        FOREIGN KEY(revision_id) REFERENCES revisions(id), FOREIGN KEY(item_type_id) REFERENCES item_types(id),
        FOREIGN KEY(package_id) REFERENCES packages(id), CHECK(quantity > 0), CHECK(return_date >= start_date)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS revision_approvals (
        revision_id BIGINT UNSIGNED PRIMARY KEY, status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL,
        FOREIGN KEY(revision_id) REFERENCES revisions(id), FOREIGN KEY(approved_by) REFERENCES users(id),
        CHECK(status IN ('Pending','Approved','Rejected'))
    ) $engine",
    "CREATE TABLE IF NOT EXISTS operations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, show_id BIGINT UNSIGNED NOT NULL,
        revision_id BIGINT UNSIGNED NULL, type VARCHAR(20) NOT NULL, mode VARCHAR(20) NOT NULL,
        created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(show_id) REFERENCES shows(id), FOREIGN KEY(revision_id) REFERENCES revisions(id),
        FOREIGN KEY(created_by) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS assignments (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, asset_id BIGINT UNSIGNED NOT NULL,
        show_id BIGINT UNSIGNED NOT NULL, line_id BIGINT UNSIGNED NOT NULL, operation_id BIGINT UNSIGNED NOT NULL,
        start_date DATE NOT NULL, return_date DATE NOT NULL,
        assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ended_at DATETIME NULL,
        end_reason VARCHAR(30) NULL, condition_on_return VARCHAR(30) NULL,
        FOREIGN KEY(asset_id) REFERENCES assets(id), FOREIGN KEY(show_id) REFERENCES shows(id),
        FOREIGN KEY(line_id) REFERENCES revision_lines(id), FOREIGN KEY(operation_id) REFERENCES operations(id),
        INDEX(asset_id,ended_at), INDEX(show_id,ended_at), INDEX(line_id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS asset_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, asset_id BIGINT UNSIGNED NOT NULL,
        show_id BIGINT UNSIGNED NULL, operation_id BIGINT UNSIGNED NULL, state VARCHAR(40) NOT NULL,
        details JSON NOT NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(asset_id) REFERENCES assets(id), FOREIGN KEY(show_id) REFERENCES shows(id),
        FOREIGN KEY(operation_id) REFERENCES operations(id), FOREIGN KEY(created_by) REFERENCES users(id),
        INDEX(asset_id,created_at)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS repairs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, asset_id BIGINT UNSIGNED NOT NULL,
        show_id BIGINT UNSIGNED NULL, description TEXT NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'Open',
        created_by BIGINT UNSIGNED NOT NULL, completed_by BIGINT UNSIGNED NULL, completed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(asset_id) REFERENCES assets(id), FOREIGN KEY(show_id) REFERENCES shows(id),
        FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(completed_by) REFERENCES users(id),
        CHECK(status IN ('Open','Working','Waiting Parts','Completed'))
    ) $engine",
    "CREATE TABLE IF NOT EXISTS show_notes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, show_id BIGINT UNSIGNED NOT NULL,
        body TEXT NOT NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(show_id) REFERENCES shows(id), FOREIGN KEY(created_by) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS files (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, show_id BIGINT UNSIGNED NOT NULL,
        original_name VARCHAR(200) NOT NULL, stored_name VARCHAR(80) NOT NULL UNIQUE, mime VARCHAR(100) NOT NULL,
        size_bytes BIGINT UNSIGNED NOT NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(show_id) REFERENCES shows(id), FOREIGN KEY(created_by) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS documents (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, type VARCHAR(40) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL,
        stored_name VARCHAR(80) NOT NULL UNIQUE, sha256 CHAR(64) NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(created_by) REFERENCES users(id),
        INDEX(type,entity_id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS user_workspaces (
        user_id BIGINT UNSIGNED PRIMARY KEY, tabs JSON NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id)
    ) $engine",
    "CREATE TABLE IF NOT EXISTS saved_views (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
        resource VARCHAR(40) NOT NULL, name VARCHAR(100) NOT NULL, configuration JSON NOT NULL,
        UNIQUE(user_id,resource,name), FOREIGN KEY(user_id) REFERENCES users(id)
    ) $engine",
];
