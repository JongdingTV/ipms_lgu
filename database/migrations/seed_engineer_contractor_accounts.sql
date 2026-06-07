-- Migration: seed multiple engineer accounts and contractor-linked portal accounts.
-- Demo passwords:
--   Engineers: engineer123
--   Contractors: contractor123

USE lgu_infrastructure;

INSERT INTO users (username, email, password_hash, full_name, role, status)
VALUES
('engineer_roads', 'engineer.roads@ipms.local', '$2y$10$slSJFcp3nQ8Td.csC9E8xOWJ0NV3AAidfdJY6f4uSBN2mHXjlttrC', 'Roads and Drainage Engineer', 'engineer', 'active'),
('engineer_structural', 'engineer.structural@ipms.local', '$2y$10$slSJFcp3nQ8Td.csC9E8xOWJ0NV3AAidfdJY6f4uSBN2mHXjlttrC', 'Structural Works Engineer', 'engineer', 'active'),
('engineer_site', 'engineer.site@ipms.local', '$2y$10$slSJFcp3nQ8Td.csC9E8xOWJ0NV3AAidfdJY6f4uSBN2mHXjlttrC', 'Site Monitoring Engineer', 'engineer', 'active'),
('engineer_electrical', 'engineer.electrical@ipms.local', '$2y$10$slSJFcp3nQ8Td.csC9E8xOWJ0NV3AAidfdJY6f4uSBN2mHXjlttrC', 'Electrical and Utilities Engineer', 'engineer', 'active'),
('contractor_abc', 'portal@abcconstruction.ph', '$2y$10$z4AdDvVhxAOXEVTPbYneJeizHVWHG7ZUntXs.IRan/LbGxMX5C6cO', 'ABC Construction Portal', 'contractor', 'active'),
('contractor_xyz', 'portal@xyzinfra.ph', '$2y$10$z4AdDvVhxAOXEVTPbYneJeizHVWHG7ZUntXs.IRan/LbGxMX5C6cO', 'XYZ Infrastructure Portal', 'contractor', 'active'),
('contractor_delta', 'portal@deltaworks.ph', '$2y$10$z4AdDvVhxAOXEVTPbYneJeizHVWHG7ZUntXs.IRan/LbGxMX5C6cO', 'Delta Civil Works Portal', 'contractor', 'active'),
('contractor_omega', 'portal@omegabldrs.ph', '$2y$10$z4AdDvVhxAOXEVTPbYneJeizHVWHG7ZUntXs.IRan/LbGxMX5C6cO', 'Omega Builders Portal', 'contractor', 'active')
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    full_name = VALUES(full_name),
    role = VALUES(role),
    status = VALUES(status);

UPDATE contractors c
JOIN users u ON u.username = 'contractor_abc'
SET c.user_id = u.id,
    c.email = 'portal@abcconstruction.ph',
    c.status = 'active'
WHERE c.name = 'ABC Construction';

UPDATE contractors c
JOIN users u ON u.username = 'contractor_xyz'
SET c.user_id = u.id,
    c.email = 'portal@xyzinfra.ph',
    c.status = 'active'
WHERE c.name = 'XYZ Infrastructure';

UPDATE contractors c
JOIN users u ON u.username = 'contractor_delta'
SET c.user_id = u.id,
    c.email = 'portal@deltaworks.ph',
    c.status = 'active'
WHERE c.name = 'Delta Civil Works';

UPDATE contractors c
JOIN users u ON u.username = 'contractor_omega'
SET c.user_id = u.id,
    c.email = 'portal@omegabldrs.ph',
    c.status = 'active'
WHERE c.name = 'Omega Builders Inc.';

INSERT IGNORE INTO engineer_project_assignments (engineer_id, project_id, assigned_by, assignment_notes, status)
SELECT u.id, p.id, a.id, 'Seeded demo assignment for engineer project scoping.', 'active'
FROM users u
JOIN projects p ON p.project_code IN ('PRJ-001', 'PRJ-005')
LEFT JOIN users a ON a.username = 'admin'
WHERE u.username = 'engineer_roads';

INSERT IGNORE INTO engineer_project_assignments (engineer_id, project_id, assigned_by, assignment_notes, status)
SELECT u.id, p.id, a.id, 'Seeded demo assignment for engineer project scoping.', 'active'
FROM users u
JOIN projects p ON p.project_code IN ('PRJ-003', 'PRJ-004')
LEFT JOIN users a ON a.username = 'admin'
WHERE u.username = 'engineer_structural';

INSERT IGNORE INTO engineer_project_assignments (engineer_id, project_id, assigned_by, assignment_notes, status)
SELECT u.id, p.id, a.id, 'Seeded demo assignment for engineer project scoping.', 'active'
FROM users u
JOIN projects p ON p.project_code IN ('PRJ-006', 'PRJ-007', 'PRJ-010')
LEFT JOIN users a ON a.username = 'admin'
WHERE u.username = 'engineer_site';

INSERT IGNORE INTO engineer_project_assignments (engineer_id, project_id, assigned_by, assignment_notes, status)
SELECT u.id, p.id, a.id, 'Seeded demo assignment for engineer project scoping.', 'active'
FROM users u
JOIN projects p ON p.project_code IN ('PRJ-008')
LEFT JOIN users a ON a.username = 'admin'
WHERE u.username = 'engineer_electrical';
