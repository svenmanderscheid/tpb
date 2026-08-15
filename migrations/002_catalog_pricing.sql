-- 002_catalog_pricing.sql – Katalog & Preis (M1). Abgeleitet aus PROJECT.md §5.3.
-- Alle Tabellen mit created_at/updated_at, FKs mit ON DELETE RESTRICT.
-- Geld als int Cents, Prozente/Margen als Basispunkte, Maße in mm (DECIMAL(6,1)).

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  product_type VARCHAR(16) NOT NULL DEFAULT 'configurable',
  sku_root VARCHAR(48) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  description_md MEDIUMTEXT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_variants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(64) NOT NULL UNIQUE,
  color_code VARCHAR(24) NULL,
  color_name VARCHAR(48) NULL,
  size VARCHAR(24) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_product (product_id),
  CONSTRAINT fk_variant_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE techniques (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE placements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  side VARCHAR(16) NOT NULL,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(80) NOT NULL,
  area_x_mm DECIMAL(6,1) NULL,
  area_y_mm DECIMAL(6,1) NULL,
  max_w_mm DECIMAL(6,1) NULL,
  max_h_mm DECIMAL(6,1) NULL,
  preset_x_mm DECIMAL(6,1) NULL,
  preset_y_mm DECIMAL(6,1) NULL,
  is_preset TINYINT(1) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_placement (product_id, code),
  KEY idx_product (product_id),
  CONSTRAINT fk_placement_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_prints (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  placement_id BIGINT UNSIGNED NOT NULL,
  technique_id BIGINT UNSIGNED NOT NULL,
  design_asset_id BIGINT UNSIGNED NULL,
  design_name VARCHAR(120) NOT NULL,
  design_version SMALLINT NOT NULL DEFAULT 1,
  width_mm DECIMAL(6,1) NULL,
  height_mm DECIMAL(6,1) NULL,
  offset_x_mm DECIMAL(6,1) NULL,
  offset_y_mm DECIMAL(6,1) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_product (product_id),
  CONSTRAINT fk_print_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_print_placement FOREIGN KEY (placement_id) REFERENCES placements(id) ON DELETE RESTRICT,
  CONSTRAINT fk_print_technique FOREIGN KEY (technique_id) REFERENCES techniques(id) ON DELETE RESTRICT,
  CONSTRAINT fk_print_asset FOREIGN KEY (design_asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_books (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version SMALLINT NOT NULL UNIQUE,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  valid_from DATE NULL,
  valid_until DATE NULL,
  published_by BIGINT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_tiers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  price_book_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty_from SMALLINT NOT NULL,
  qty_to SMALLINT NULL,
  unit_cents INT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_tier (price_book_id, product_id, qty_from),
  KEY idx_book_product (price_book_id, product_id),
  CONSTRAINT fk_tier_book FOREIGN KEY (price_book_id) REFERENCES price_books(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tier_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE price_params (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  price_book_id BIGINT UNSIGNED NOT NULL,
  param_key VARCHAR(48) NOT NULL,
  value_int INT NOT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_param (price_book_id, param_key),
  CONSTRAINT fk_param_book FOREIGN KEY (price_book_id) REFERENCES price_books(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cost_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version SMALLINT NOT NULL UNIQUE,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  valid_from DATE NULL,
  labor_rate_cents_h INT NOT NULL DEFAULT 0,
  machine_rate_cents_h INT NOT NULL DEFAULT 0,
  scrap_bps SMALLINT NOT NULL DEFAULT 0,
  target_margin_bps SMALLINT NOT NULL DEFAULT 0,
  published_by BIGINT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cost_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cost_version_id BIGINT UNSIGNED NOT NULL,
  ref_type VARCHAR(16) NOT NULL,
  ref_id BIGINT UNSIGNED NOT NULL,
  param_key VARCHAR(32) NOT NULL,
  value_int INT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_cost_item (cost_version_id, ref_type, ref_id, param_key),
  KEY idx_version (cost_version_id),
  CONSTRAINT fk_costitem_version FOREIGN KEY (cost_version_id) REFERENCES cost_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
