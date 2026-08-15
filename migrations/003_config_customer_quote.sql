-- 003_config_customer_quote.sql – Konfigurator, Kunde, Angebot (§5.4).
-- M2 nutzt den Konfigurations-Teil, M3 den Kunden-/Angebots-Teil.
-- Maße in realen Millimetern (DECIMAL(6,1)), Geld als int Cents. FKs ON DELETE RESTRICT.
-- Hinweis: quotes.amends_order_id referenziert die erst in Migration 004 angelegte
-- Tabelle `orders` – daher hier bewusst OHNE FK-Constraint (nur indiziert), um die
-- Migrationsreihenfolge nicht zu brechen (siehe docs/DECISIONS.md).

CREATE TABLE customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  type VARCHAR(16) NOT NULL DEFAULT 'private',
  company_name VARCHAR(160) NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NULL,
  lang CHAR(2) NOT NULL DEFAULT 'de',
  billing_street VARCHAR(160) NULL,
  billing_zip VARCHAR(16) NULL,
  billing_city VARCHAR(80) NULL,
  billing_country CHAR(2) NULL,
  delivery_street VARCHAR(160) NULL,
  delivery_zip VARCHAR(16) NULL,
  delivery_city VARCHAR(80) NULL,
  delivery_country CHAR(2) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_consents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  purpose VARCHAR(48) NOT NULL,
  legal_doc_version_id BIGINT UNSIGNED NULL,
  granted_at DATETIME NOT NULL,
  source VARCHAR(48) NOT NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_customer (customer_id),
  CONSTRAINT fk_consent_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_consent_legaldoc FOREIGN KEY (legal_doc_version_id) REFERENCES legal_document_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configurations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  customer_id BIGINT UNSIGNED NULL,
  guest_email VARCHAR(190) NULL,
  guest_name VARCHAR(120) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  price_book_id BIGINT UNSIGNED NULL,
  cost_version_id BIGINT UNSIGNED NULL,
  express TINYINT(1) NOT NULL DEFAULT 0,
  note VARCHAR(500) NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_customer (customer_id),
  CONSTRAINT fk_config_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_config_book FOREIGN KEY (price_book_id) REFERENCES price_books(id) ON DELETE RESTRICT,
  CONSTRAINT fk_config_cost FOREIGN KEY (cost_version_id) REFERENCES cost_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuration_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  configuration_id BIGINT UNSIGNED NOT NULL,
  pos_no SMALLINT UNSIGNED NOT NULL,
  item_type VARCHAR(16) NOT NULL DEFAULT 'configured',
  product_id BIGINT UNSIGNED NOT NULL,
  technique_id BIGINT UNSIGNED NULL,
  comment VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_config (configuration_id),
  CONSTRAINT fk_item_config FOREIGN KEY (configuration_id) REFERENCES configurations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_item_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_item_technique FOREIGN KEY (technique_id) REFERENCES techniques(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuration_item_sizes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL,
  qty SMALLINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_item_variant (item_id, variant_id),
  CONSTRAINT fk_size_item FOREIGN KEY (item_id) REFERENCES configuration_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_size_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuration_layers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  placement_id BIGINT UNSIGNED NOT NULL,
  layer_no SMALLINT UNSIGNED NOT NULL,
  layer_type VARCHAR(16) NOT NULL,
  asset_id BIGINT UNSIGNED NULL,
  text_content VARCHAR(255) NULL,
  color_name VARCHAR(48) NULL,
  width_mm DECIMAL(6,1) NULL,
  height_mm DECIMAL(6,1) NULL,
  offset_x_mm DECIMAL(6,1) NULL,
  offset_y_mm DECIMAL(6,1) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_item (item_id),
  CONSTRAINT fk_layer_item FOREIGN KEY (item_id) REFERENCES configuration_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_layer_placement FOREIGN KEY (placement_id) REFERENCES placements(id) ON DELETE RESTRICT,
  CONSTRAINT fk_layer_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuration_units (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  unit_no SMALLINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(60) NULL,
  number VARCHAR(8) NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_item (item_id),
  CONSTRAINT fk_unit_item FOREIGN KEY (item_id) REFERENCES configuration_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_unit_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_calculations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  configuration_id BIGINT UNSIGNED NOT NULL,
  price_book_id BIGINT UNSIGNED NOT NULL,
  cost_version_id BIGINT UNSIGNED NOT NULL,
  input_hash CHAR(64) NOT NULL,
  breakdown_json JSON NOT NULL,
  total_cents INT NOT NULL,
  floor_cents INT NOT NULL,
  below_floor TINYINT(1) NOT NULL DEFAULT 0,
  calc_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_config (configuration_id),
  KEY idx_calc_hash (calc_hash),
  CONSTRAINT fk_calc_config FOREIGN KEY (configuration_id) REFERENCES configurations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  quote_number VARCHAR(20) NULL UNIQUE,
  customer_id BIGINT UNSIGNED NOT NULL,
  configuration_id BIGINT UNSIGNED NOT NULL,
  amends_order_id BIGINT UNSIGNED NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  total_cents INT NOT NULL DEFAULT 0,
  valid_until DATE NULL,
  snapshot_json JSON NULL,
  snapshot_sha256 CHAR(64) NULL,
  pdf_asset_id BIGINT UNSIGNED NULL,
  sent_at DATETIME NULL,
  accepted_at DATETIME NULL,
  declined_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_customer (customer_id),
  KEY idx_amends (amends_order_id),
  CONSTRAINT fk_quote_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_quote_config FOREIGN KEY (configuration_id) REFERENCES configurations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_quote_pdf FOREIGN KEY (pdf_asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote_id BIGINT UNSIGNED NOT NULL,
  pos_no SMALLINT UNSIGNED NOT NULL,
  sku VARCHAR(64) NULL,
  description VARCHAR(255) NOT NULL,
  qty SMALLINT UNSIGNED NOT NULL,
  unit_cents INT NOT NULL,
  line_cents INT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_quote (quote_id),
  CONSTRAINT fk_qitem_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
