-- 006_shop_checkout.sql – Shop-Checkout & Online-Zahlung (§5.7, Pfad B, Konzept 20.10).
-- Test-Adapter zuerst (Owner 2026-08-16): kompletter Flow im Testmodus; echter Anbieter
-- ist später nur ein weiterer Gateway-Adapter. Geld als int Cents, Zeit UTC.

CREATE TABLE payment_intents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  order_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(24) NOT NULL,
  provider_ref VARCHAR(120) NULL,
  amount_cents INT NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  status VARCHAR(16) NOT NULL DEFAULT 'created',
  checkout_url VARCHAR(500) NULL,
  failure_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_provider_ref (provider, provider_ref),
  KEY idx_order (order_id),
  CONSTRAINT fk_intent_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(24) NOT NULL,
  event_ref VARCHAR(120) NOT NULL,
  event_type VARCHAR(48) NOT NULL,
  payload_json JSON NOT NULL,
  signature_valid TINYINT(1) NOT NULL DEFAULT 0,
  received_at DATETIME NOT NULL,
  processed_at DATETIME NULL,
  process_status VARCHAR(16) NOT NULL DEFAULT 'pending',
  error_message VARCHAR(255) NULL,
  UNIQUE KEY uq_provider_event (provider, event_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
