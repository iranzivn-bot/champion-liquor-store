-- POS Permission Migration
-- Adds pos.access permission and assigns to super_admin and admin roles.

INSERT INTO permissions (name, description, module)
SELECT 'pos.access', 'Access Point of Sale module', 'pos'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'pos.access');

-- Assign to super_admin (if not already assigned)
INSERT INTO role_permissions (role, permission_id)
SELECT 'super_admin', id FROM permissions WHERE name = 'pos.access'
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role = 'super_admin' AND rp.permission_id = (SELECT id FROM permissions WHERE name = 'pos.access'));

-- Assign to admin
INSERT INTO role_permissions (role, permission_id)
SELECT 'admin', id FROM permissions WHERE name = 'pos.access'
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role = 'admin' AND rp.permission_id = (SELECT id FROM permissions WHERE name = 'pos.access'));
