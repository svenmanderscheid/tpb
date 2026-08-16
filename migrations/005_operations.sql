-- 005_operations.sql – Betrieb (§5.6). M6 nutzt `expenses`; bank_imports/bank_lines/
-- reminders_sent kommen in M8 (Bankabgleich & Mahnwesen). Vollständig angelegt, damit
-- die Migration nach dem Merge unveränderlich bleibt (§14.5). Geld als int Cents.
-- Hinweis Zyklus: expenses.bank_line_id ↔ bank_lines.matched_expense_id – der Rückzeiger
-- expenses.bank_line_id bleibt bewusst OHNE FK (nur Index), um die Reihenfolge nicht zu
-- brechen; die Integrität sichert die M8-Matching-Logik.

CREATE TABLE expenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  expense_date DATE NOT NULL,
  vendor VARCHAR(120) NOT NULL,
  category VARCHAR(48) NOT NULL,
  description VARCHAR(255) NULL,
  amount_cents INT NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  receipt_asset_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  bank_line_id BIGINT UNSIGNED NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_date (expense_date),
  KEY idx_order (order_id),
  KEY idx_bank_line (bank_line_id),
  CONSTRAINT fk_expense_receipt FOREIGN KEY (receipt_asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_expense_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bank_imports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_label VARCHAR(64) NOT NULL,
  format VARCHAR(16) NOT NULL,
  original_asset_id BIGINT UNSIGNED NULL,
  file_sha256 CHAR(64) NOT NULL UNIQUE,
  line_count INT NOT NULL DEFAULT 0,
  imported_by BIGINT UNSIGNED NULL,
  imported_at DATETIME NOT NULL,
  CONSTRAINT fk_bankimport_asset FOREIGN KEY (original_asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bank_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bank_import_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  booking_date DATE NOT NULL,
  value_date DATE NULL,
  amount_cents INT NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  counterparty_name VARCHAR(160) NULL,
  counterparty_iban VARCHAR(40) NULL,
  remittance_info VARCHAR(500) NULL,
  end_to_end_id VARCHAR(80) NULL,
  dedupe_hash CHAR(64) NOT NULL UNIQUE,
  match_status VARCHAR(16) NOT NULL DEFAULT 'open',
  matched_payment_id BIGINT UNSIGNED NULL,
  matched_expense_id BIGINT UNSIGNED NULL,
  confirmed_by BIGINT UNSIGNED NULL,
  confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_import (bank_import_id),
  CONSTRAINT fk_bankline_import FOREIGN KEY (bank_import_id) REFERENCES bank_imports(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bankline_payment FOREIGN KEY (matched_payment_id) REFERENCES payments(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bankline_expense FOREIGN KEY (matched_expense_id) REFERENCES expenses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reminders_sent (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref_type VARCHAR(16) NOT NULL,
  ref_id BIGINT UNSIGNED NOT NULL,
  reminder_type VARCHAR(16) NOT NULL,
  level TINYINT UNSIGNED NOT NULL,
  sent_at DATETIME NOT NULL,
  outbox_id BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_reminder (ref_type, ref_id, reminder_type, level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
