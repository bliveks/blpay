-- Maliyyə İdarəetmə Sistemi - Quraşdırma SQL (boş verilənlər bazası strukturu)
-- Bu fayl install/index.php tərəfindən icra olunur.
-- İstifadəçi datası yoxdur; yalnız cədvəllər və standart kateqoriyalar.

CREATE TABLE IF NOT EXISTS istifadeciler (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ad VARCHAR(50) NOT NULL,
  soyad VARCHAR(50) NOT NULL,
  email VARCHAR(100) NOT NULL,
  sifre VARCHAR(255) NOT NULL,
  yaradilma_tarixi TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  son_giris TIMESTAMP NULL DEFAULT NULL,
  qeydiyyat_tamamlanib TINYINT(1) DEFAULT 0,
  status ENUM('aktiv','deaktiv') DEFAULT 'aktiv',
  rol ENUM('admin','user') DEFAULT 'user',
  remember_token VARCHAR(255) DEFAULT NULL,
  aylik_gelir DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Ayliq gelir cemi',
  aylik_xerc DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Ayliq xerc cemi',
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kateqoriyalar (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  istifadeci_id INT DEFAULT NULL,
  ad VARCHAR(50) NOT NULL,
  tip ENUM('gelir','xerc') NOT NULL,
  limit_mebleq DECIMAL(10,2) DEFAULT NULL,
  standart TINYINT(1) DEFAULT 0,
  yaradilma_tarixi TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY istifadeci_id (istifadeci_id),
  CONSTRAINT kateqoriyalar_ibfk_1 FOREIGN KEY (istifadeci_id) REFERENCES istifadeciler (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gelirler (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  istifadeci_id INT NOT NULL,
  kateqoriya_id INT NOT NULL,
  mebleq DECIMAL(10,2) NOT NULL,
  tarix DATE NOT NULL,
  hesab_novu ENUM('nagd','kredit_kart','debet_kart') NOT NULL,
  qeyd TEXT,
  yaradilma_tarixi TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY istifadeci_id (istifadeci_id),
  KEY kateqoriya_id (kateqoriya_id),
  CONSTRAINT gelirler_ibfk_1 FOREIGN KEY (istifadeci_id) REFERENCES istifadeciler (id) ON DELETE CASCADE,
  CONSTRAINT gelirler_ibfk_2 FOREIGN KEY (kateqoriya_id) REFERENCES kateqoriyalar (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS xercler (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  istifadeci_id INT NOT NULL,
  kateqoriya_id INT NOT NULL,
  mebleq DECIMAL(10,2) NOT NULL,
  tarix DATE NOT NULL,
  hesab_novu ENUM('nagd','kredit_kart','debet_kart') NOT NULL,
  qeyd TEXT,
  yaradilma_tarixi TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY istifadeci_id (istifadeci_id),
  KEY kateqoriya_id (kateqoriya_id),
  CONSTRAINT xercler_ibfk_1 FOREIGN KEY (istifadeci_id) REFERENCES istifadeciler (id) ON DELETE CASCADE,
  CONSTRAINT xercler_ibfk_2 FOREIGN KEY (kateqoriya_id) REFERENCES kateqoriyalar (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS giris_cehdleri (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  tarix TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hedefler (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  istifadeci_id INT NOT NULL,
  ad VARCHAR(100) NOT NULL,
  hedef_mebleq DECIMAL(10,2) NOT NULL,
  cari_mebleq DECIMAL(10,2) DEFAULT 0.00,
  baslangic_tarixi DATE NOT NULL,
  hedef_tarixi DATE NOT NULL,
  prioritet ENUM('yuksek','orta','asagi') DEFAULT 'orta',
  status ENUM('aktiv','tamamlanib','legv_edilib') DEFAULT 'aktiv',
  qeyd TEXT,
  yaradilma_tarixi TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY istifadeci_id (istifadeci_id),
  CONSTRAINT hedefler_ibfk_1 FOREIGN KEY (istifadeci_id) REFERENCES istifadeciler (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hedef_emeliyyatlar (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hedef_id INT NOT NULL,
  istifadeci_id INT NOT NULL,
  mebleq DECIMAL(10,2) NOT NULL,
  emeliyyat_novu ENUM('artirma','azaltma') NOT NULL,
  tarix DATE NOT NULL,
  qeyd TEXT,
  yaradilma_tarixi TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY hedef_id (hedef_id),
  KEY istifadeci_id (istifadeci_id),
  CONSTRAINT hedef_emeliyyatlar_ibfk_1 FOREIGN KEY (hedef_id) REFERENCES hedefler (id) ON DELETE CASCADE,
  CONSTRAINT hedef_emeliyyatlar_ibfk_2 FOREIGN KEY (istifadeci_id) REFERENCES istifadeciler (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  action VARCHAR(255) NOT NULL,
  details TEXT,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY user_id (user_id),
  CONSTRAINT audit_log_ibfk_1 FOREIGN KEY (user_id) REFERENCES istifadeciler (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_tokens (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(255) NOT NULL,
  user_agent TEXT,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY user_id (user_id),
  CONSTRAINT remember_tokens_ibfk_1 FOREIGN KEY (user_id) REFERENCES istifadeciler (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standart kateqoriyalar (qeydiyyatın 2-ci addımı üçün lazımdır)
INSERT INTO kateqoriyalar (istifadeci_id, ad, tip, limit_mebleq, standart) VALUES
(NULL, 'Maaş', 'gelir', NULL, 1),
(NULL, 'Biznes', 'gelir', NULL, 1),
(NULL, 'İnvestisiya', 'gelir', NULL, 1),
(NULL, 'Digər', 'gelir', NULL, 1),
(NULL, 'Qida', 'xerc', NULL, 1),
(NULL, 'Kommunal', 'xerc', NULL, 1),
(NULL, 'Nəqliyyat', 'xerc', NULL, 1),
(NULL, 'Təhsil', 'xerc', NULL, 1),
(NULL, 'Səhiyyə', 'xerc', NULL, 1),
(NULL, 'Əyləncə', 'xerc', NULL, 1),
(NULL, 'Geyim', 'xerc', NULL, 1),
(NULL, 'Digər', 'xerc', NULL, 1);
