-- 051_shipping.sql – Versand & Fulfillment (Owner-Entscheidung 2026-08-16, DECISIONS #28).
-- Out-of-band-Block (050er, DECISIONS #15), da 005/006 für Operations/Shop reserviert sind.
-- Eine Sendung je Auftrag. Fulfillment-Status ist die Order-Achse (orders.cur_fulfillment
-- + status_events, §7); shipments hält Versandart, Lieferadresse (Snapshot), Kosten,
-- Tracking, Etikett und Zeitstempel. Geld als int Cents, Zeit UTC.

CREATE TABLE shipments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  order_id BIGINT UNSIGNED NOT NULL,
  method VARCHAR(24) NOT NULL DEFAULT 'pickup',   -- pickup | self_premium | carrier_standard
  carrier VARCHAR(48) NULL,                        -- günstigster Anbieter (Standardversand), NULL sonst
  recipient_name VARCHAR(160) NOT NULL,
  recipient_company VARCHAR(160) NULL,
  street VARCHAR(160) NULL,
  zip VARCHAR(16) NULL,
  city VARCHAR(80) NULL,
  country CHAR(2) NULL,
  shipping_cost_cents INT NOT NULL DEFAULT 0,
  tracking_ref VARCHAR(120) NULL,
  label_asset_id BIGINT UNSIGNED NULL,
  packed_at DATETIME NULL,
  shipped_at DATETIME NULL,
  delivered_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_order (order_id),
  CONSTRAINT fk_shipment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_shipment_label FOREIGN KEY (label_asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
