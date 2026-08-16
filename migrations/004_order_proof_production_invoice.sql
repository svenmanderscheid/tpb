-- 004_order_proof_production_invoice.sql – Auftrag, Proof, Produktion, Rechnung (§5.5).
-- M3 nutzt orders/order_items/order_item_units/order_terms_acceptance/deposit_requests.
-- Die übrigen Tabellen (artwork/proofs/production/invoices/payments/print_jobs) werden
-- hier vollständig angelegt (M4–M6), damit die Migration nach dem Merge unveränderlich
-- bleibt (§14.5). Geld als int Cents, Zeit UTC, IDs intern BIGINT + public_id (ULID).

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  order_number VARCHAR(20) NOT NULL UNIQUE,
  quote_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  customer_snapshot_json JSON NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  total_cents INT NOT NULL DEFAULT 0,
  deposit_required_cents INT NOT NULL DEFAULT 0,
  ordered_at DATETIME NOT NULL,
  cur_payment VARCHAR(32) NOT NULL DEFAULT 'NOT_DUE',
  cur_artwork VARCHAR(32) NOT NULL DEFAULT 'MISSING',
  cur_production VARCHAR(32) NOT NULL DEFAULT 'BLOCKED',
  cur_fulfillment VARCHAR(32) NOT NULL DEFAULT 'UNFULFILLED',
  cur_invoice VARCHAR(32) NOT NULL DEFAULT 'NONE',
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_customer (customer_id),
  KEY idx_quote (quote_id),
  CONSTRAINT fk_order_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  pos_no SMALLINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(64) NULL,
  description VARCHAR(255) NOT NULL,
  qty SMALLINT UNSIGNED NOT NULL,
  unit_cents INT NOT NULL,
  line_cents INT NOT NULL,
  config_snapshot_json JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_oitem_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_oitem_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_item_units (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_item_id BIGINT UNSIGNED NOT NULL,
  unit_no SMALLINT UNSIGNED NOT NULL,
  variant_sku VARCHAR(64) NOT NULL,
  name VARCHAR(60) NULL,
  number VARCHAR(8) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_oitem (order_item_id),
  CONSTRAINT fk_ounit_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_terms_acceptance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NULL,
  quote_id BIGINT UNSIGNED NULL,
  doc_type VARCHAR(32) NOT NULL,
  legal_doc_version_id BIGINT UNSIGNED NOT NULL,
  shown_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  actor_label VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_order (order_id),
  KEY idx_quote (quote_id),
  CONSTRAINT fk_terms_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_terms_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_terms_legaldoc FOREIGN KEY (legal_doc_version_id) REFERENCES legal_document_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  kind VARCHAR(16) NOT NULL DEFAULT 'original',
  preflight_json JSON NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'uploaded',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_artwork_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_artwork_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proofs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  artwork_version_id BIGINT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  pdf_asset_id BIGINT UNSIGNED NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_proof_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_proof_artwork FOREIGN KEY (artwork_version_id) REFERENCES artwork_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_proof_pdf FOREIGN KEY (pdf_asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proof_approvals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proof_id BIGINT UNSIGNED NOT NULL,
  decision VARCHAR(24) NOT NULL,
  comment TEXT NULL,
  actor_label VARCHAR(120) NULL,
  access_token_id BIGINT UNSIGNED NULL,
  decided_at DATETIME NOT NULL,
  KEY idx_proof (proof_id),
  CONSTRAINT fk_approval_proof FOREIGN KEY (proof_id) REFERENCES proofs(id) ON DELETE RESTRICT,
  CONSTRAINT fk_approval_token FOREIGN KEY (access_token_id) REFERENCES access_tokens(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  job_number VARCHAR(20) NOT NULL UNIQUE,
  order_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'BLOCKED',
  route VARCHAR(64) NULL,
  planned_min INT NULL,
  due_date DATE NULL,
  gate_override_by BIGINT UNSIGNED NULL,
  gate_override_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_job_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_job_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(24) NOT NULL,
  step VARCHAR(24) NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  qty INT NULL,
  minutes INT NULL,
  note VARCHAR(255) NULL,
  idem_key VARCHAR(80) NULL UNIQUE,
  occurred_at DATETIME NOT NULL,
  KEY idx_job (job_id),
  CONSTRAINT fk_pevent_job FOREIGN KEY (job_id) REFERENCES production_jobs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  doc_type VARCHAR(16) NOT NULL DEFAULT 'invoice',
  invoice_number VARCHAR(20) NULL UNIQUE,
  status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
  order_id BIGINT UNSIGNED NULL,
  credited_invoice_id BIGINT UNSIGNED NULL,
  seller_snapshot_json JSON NULL,
  customer_snapshot_json JSON NULL,
  tax_regime_code VARCHAR(32) NULL,
  tax_legend VARCHAR(255) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  net_cents INT NOT NULL DEFAULT 0,
  tax_cents INT NOT NULL DEFAULT 0,
  gross_cents INT NOT NULL DEFAULT 0,
  prepayment_applied_cents INT NOT NULL DEFAULT 0,
  issued_at DATETIME NULL,
  due_date DATE NULL,
  snapshot_json JSON NULL,
  snapshot_sha256 CHAR(64) NULL,
  pdf_asset_id BIGINT UNSIGNED NULL,
  json_asset_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_invoice_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoice_credited FOREIGN KEY (credited_invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoice_pdf FOREIGN KEY (pdf_asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoice_json FOREIGN KEY (json_asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  pos_no SMALLINT UNSIGNED NOT NULL,
  sku VARCHAR(64) NULL,
  description VARCHAR(255) NOT NULL,
  qty SMALLINT UNSIGNED NOT NULL,
  unit_cents INT NOT NULL,
  line_cents INT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_invoice (invoice_id),
  CONSTRAINT fk_iline_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NULL,
  method VARCHAR(24) NOT NULL,
  amount_cents INT NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  received_at DATE NOT NULL,
  reference VARCHAR(140) NULL,
  fee_cents INT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_allocations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NOT NULL,
  amount_cents INT NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_alloc (payment_id, invoice_id),
  CONSTRAINT fk_alloc_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE RESTRICT,
  CONSTRAINT fk_alloc_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE deposit_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  request_number VARCHAR(20) NOT NULL UNIQUE,
  order_id BIGINT UNSIGNED NOT NULL,
  amount_cents INT NOT NULL,
  due_date DATE NULL,
  bank_reference VARCHAR(20) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  pdf_asset_id BIGINT UNSIGNED NULL,
  sent_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_deposit_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_deposit_pdf FOREIGN KEY (pdf_asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE print_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label_type VARCHAR(32) NOT NULL,
  entity_type VARCHAR(32) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  template_version VARCHAR(16) NOT NULL,
  copies TINYINT UNSIGNED NOT NULL DEFAULT 1,
  idempotency_key VARCHAR(80) NOT NULL UNIQUE,
  payload_json JSON NULL,
  render_format VARCHAR(8) NOT NULL DEFAULT 'pdf',
  rendered_asset_id BIGINT UNSIGNED NULL,
  rendered_sha256 CHAR(64) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'queued',
  requested_by BIGINT UNSIGNED NULL,
  requested_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  error_message VARCHAR(255) NULL,
  reprint_of_id BIGINT UNSIGNED NULL,
  reprint_reason VARCHAR(255) NULL,
  KEY idx_entity (entity_type, entity_id),
  CONSTRAINT fk_printjob_asset FOREIGN KEY (rendered_asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_printjob_reprint FOREIGN KEY (reprint_of_id) REFERENCES print_jobs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
