-- 050_finance.sql – Finanzmodul (Branch feature/finanzen, Abweichung siehe docs/DECISIONS.md #14-18).
-- Nummer 050 hält die für die Roadmap reservierten Nummern 002-006 frei.
-- Geld als int Cents, Anteile als Basispunkte (§3). Migrationen unveränderlich (§14.5).

CREATE TABLE finance_partners (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  profit_share_bps SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE capital_contributions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  partner_id BIGINT UNSIGNED NOT NULL,
  contributed_on DATE NOT NULL,
  kind VARCHAR(16) NOT NULL DEFAULT 'cash',
  amount_cents INT NOT NULL,
  note VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_partner (partner_id),
  KEY idx_date (contributed_on),
  CONSTRAINT fk_cc_partner FOREIGN KEY (partner_id) REFERENCES finance_partners(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE finance_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  entry_date DATE NOT NULL,
  direction VARCHAR(8) NOT NULL,
  category VARCHAR(48) NOT NULL,
  description VARCHAR(255) NULL,
  amount_cents INT NOT NULL,
  partner_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_date (entry_date),
  KEY idx_dir (direction, entry_date),
  KEY idx_category (category),
  CONSTRAINT fk_fe_partner FOREIGN KEY (partner_id) REFERENCES finance_partners(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
