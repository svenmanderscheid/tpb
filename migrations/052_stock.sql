-- 052_stock.sql – Lagermodul (Owner 2026-08-16, DECISIONS #31). Out-of-band-Block (050er).
-- Bestand je Variante (Rohlinge). Verfügbarkeit = max(0, stock_qty − reserve_qty − aktive
-- Reservierungen). Reservierung bei Checkout, Abbuchung erst bei bezahlter Bestellung,
-- Freigabe bei Abbruch/Ablauf. Jede physische Bestandsänderung schreibt eine Bewegung.

ALTER TABLE product_variants
  ADD COLUMN stock_qty INT NOT NULL DEFAULT 0,
  ADD COLUMN reserve_qty INT NOT NULL DEFAULT 3,
  ADD COLUMN reorder_threshold INT NOT NULL DEFAULT 5;

CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id BIGINT UNSIGNED NOT NULL,
  delta INT NOT NULL,                       -- signiert: Eingang +, Abbuchung −
  reason VARCHAR(24) NOT NULL,              -- receipt | sale | adjustment
  balance_after INT NOT NULL,
  ref_type VARCHAR(24) NULL,
  ref_id BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_variant (variant_id, id),
  CONSTRAINT fk_stockmove_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',   -- active | consumed | released
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_variant_status (variant_id, status),
  KEY idx_order (order_id),
  CONSTRAINT fk_stockres_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_stockres_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_alerts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id BIGINT UNSIGNED NOT NULL,
  level VARCHAR(16) NOT NULL,               -- reorder | critical | empty
  sent_at DATETIME NOT NULL,
  outbox_id BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_variant_level (variant_id, level),
  CONSTRAINT fk_stockalert_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
