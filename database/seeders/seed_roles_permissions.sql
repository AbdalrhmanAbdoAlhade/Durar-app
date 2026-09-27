-- Run this AFTER migrating (including Spatie's published permission migrations).
-- guard_name = 'sanctum' because all protected routes use the `auth:sanctum` middleware.

-- 1) Permissions
INSERT INTO permissions (name, guard_name, created_at, updated_at) VALUES
('manage-categories', 'sanctum', NOW(), NOW()),
('manage-products',   'sanctum', NOW(), NOW()),
('manage-coupons',    'sanctum', NOW(), NOW()),
('manage-banners',    'sanctum', NOW(), NOW()),
('manage-reviews',    'sanctum', NOW(), NOW()),
('manage-auctions',   'sanctum', NOW(), NOW()),
('manage-orders',     'sanctum', NOW(), NOW());

-- 2) Admin role
INSERT INTO roles (name, guard_name, created_at, updated_at) VALUES
('admin', 'sanctum', NOW(), NOW());

-- 3) Attach every permission above to the admin role
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
JOIN roles r ON r.name = 'admin' AND r.guard_name = 'sanctum'
WHERE p.name IN (
    'manage-categories', 'manage-products', 'manage-coupons',
    'manage-banners', 'manage-reviews', 'manage-auctions', 'manage-orders'
);

-- 4) Make an existing user an admin (replace :user_id with the real users.id)
-- INSERT INTO model_has_roles (role_id, model_type, model_id)
-- SELECT r.id, 'App\\Models\\User', :user_id
-- FROM roles r WHERE r.name = 'admin' AND r.guard_name = 'sanctum';

-- After running this, clear Spatie's permission cache:
--   php artisan permission:cache-reset
