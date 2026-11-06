-- New operational role: inventory_admin (Property Inventory manager).
-- The system now supports up to 4 concurrent operational roles stored in
-- officers.role: admin, clerk, inventory_admin (staff) + client (client_accounts).
--
-- officers.role is VARCHAR(50) with no enum, so 'inventory_admin' needs NO
-- schema change. Two code paths previously collapsed every non-admin staff
-- role to 'clerk' and must be taught about the new value:
--   * php/api_auth.php  -> preserves admin/clerk/inventory_admin at login
--   * js/log in.js      -> redirectByRole() sends inventory_admin to the
--                          admin dashboard (Property Inventory lives there)
--
-- Ms. Rea manages the Property Inventory module under the admin section.
-- Her account is seeded below, keyed on the UNIQUE email (uq_officers_email)
-- so it is idempotent and never clobbers an unrelated officer who might
-- already occupy the next auto-increment id.
--
-- Login: rea@ihc.com / inventory123   (change this password in production)

INSERT INTO officers (full_name, role, email, password_hash, must_change_password)
VALUES (
  'Rea',
  'inventory_admin',
  'rea@ihc.com',
  '$2y$10$3UOBUc6oDGGOw9/3/5RzxOwOvLf6b.k/ticpokdx0gbjX94cQcwx2',  -- bcrypt('inventory123')
  0
)
ON DUPLICATE KEY UPDATE
  full_name          = VALUES(full_name),
  role               = VALUES(role),
  password_hash      = VALUES(password_hash),
  must_change_password = VALUES(must_change_password);

-- Access-control note (php/api_holding_reservation.php):
--   Property Inventory create/edit/delete/status-change actions
--   (create_unit, update_unit, delete_unit, unit_status_update) are gated to
--   role IN ('admin','inventory_admin') by requireInventoryManager(), which
--   resolves the acting officer's role from this table via the actorId the
--   dashboard already sends for audit logging. Clerks/cashiers keep their
--   holding- & reservation-fee flows (create/update_holding_fee,
--   create/update_reservation_fee, update_holding_status,
--   update_reservation_status), which are NOT gated.
