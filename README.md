# ai-mariadb: Chatbot Pintar Berasaskan Pangkalan Data MariaDB / MySQL

Plugin / modul chatbot PHP berasaskan **RAG (Retrieval-Augmented Generation)** dan model embedding tempatan (**Ollama `embeddinggemma`**). Sistem ini dilengkapi **UI Pengurusan (Admin Dashboard)** untuk menyambung database daripada sistem luaran (cth: POS, E-Commerce, ERP, Sistem Tempahan), memuat turun senarai jadual secara dinamik, memilih data yang dibenarkan, dan menjana chatbot pintar untuk laman web — **HANYA berpandukan data sah dan menyekat jadual sensitif seperti `users`**.

---

## 1. Konsep & Matlamat Utama

1. **Sambungan Dinamik ke Database Sistem Lain (External Database)**:
   - Pentadbir boleh memasukkan kredensial database dari mana-mana sistem luaran (cth: MySQL/MariaDB sistem kedai atau inventori) melalui UI.
   - Sistem akan menyambung secara **READ-ONLY** tanpa mengganggu pangkalan data asal.
2. **Antaramuka "Load Tables & Select" (UI Pilihan Jadual)**:
   - UI memaparkan senarai semua jadual dalam database luaran secara automatik (`SHOW TABLES`).
   - Pentadbir memilih jadual mana yang ingin dimasukkan ke dalam pengetahuan AI (cth: `products`, `store_info`, `inventory`, `faqs`).
3. **Perlindungan Data Sensitif Automatik (Auto-Block Sensitive Tables)**:
   - Sistem menyekat dan melarang pemilihan jadual berisiko tinggi seperti `users`, `accounts`, `customers_private`, `passwords`, `tokens`, atau `sessions`.
4. **AI Berpandukan Database 100% (Anti-Halusinasi)**:
   - AI hanya menjawab soalan pelanggan berpandukan rekod yang ditemui. Sekiranya tiada maklumat dalam rekod yang dibenarkan, AI akan memaklumkan bahawa maklumat tiada dalam simpanan.
5. **Privasi Penuh Tempatan (Ollama On-Premise)**:
   - Model `embeddinggemma` dan Chat LLM dijalankan secara lokal melalui Ollama tanpa kos API luaran atau kebocoran data syarikat.

---

## 2. Seni Bina Penyambungan Database Luar & Aliran UI

Sistem bertindak sebagai jambatan pintar antara database sistem sedia ada anda dengan Chatbot Web:

```mermaid
flowchart TD
    subgraph Sistem Luar [Sistem Sedia Ada cth: POS / E-Commerce]
        ExtDB[(Database Luaran\nMySQL / MariaDB)]
        UsersTbl[jadual 'users' - DISEKAT]
        ProdTbl[jadual 'products' - DIBENARKAN]
        HoursTbl[jadual 'store_hours' - DIBENARKAN]
    end

    subgraph AI Manager [Sistem ai-mariadb]
        AdminUI[Admin Dashboard UI]
        ConnMgr[Penyambung DB Luar\n(Read-Only PDO)]
        TblLoader[Load Tables & Filter Engine]
        VectGen[Indexer & embeddinggemma]
        LocalDB[(Database Tempatan\nai_knowledge_vectors)]
        ChatAPI[Chatbot Service API]
    end

    subgraph Pelanggan [Laman Web Awam]
        Widget[Web Chatbot Widget]
    end

    AdminUI -->|1. Masukkan Host, DB, User, Pass| ConnMgr
    ConnMgr -->|2. Sambung & Query Tables| ExtDB
    ExtDB -->|3. Pulangkan Senarai Jadual| TblLoader
    TblLoader -->|4. Papar Checkbox Jadual di UI\n(Kunci jadual sensitif)| AdminUI
    AdminUI -->|5. Klik 'Sync & Index'| VectGen
    VectGen -->|6. Baca Data Dibenarkan Sahaja| ExtDB
    VectGen -->|7. Simpan Vektor & Konteks| LocalDB
    Widget <-->|8. Tanya Soalan & Dapatkan Jawapan DB| ChatAPI
    ChatAPI <--> LocalDB
```

---

## 3. Aliran Antaramuka Pentadbir (Admin UI Flow)

### Langkah 1: Tambah Sambungan Database Luaran (Add Database Source)
Borang di UI menerima maklumat sambungan:
- **Connection Label**: Cth: *Sistem Kedai Cawangan Bangi*
- **DB Host & Port**: Cth: `127.0.0.1:3306` atau IP Server sistem lain
- **DB Name**: Cth: `pos_inventory`
- **Username & Password**: Kredensial pengguna database luaran (disyorkan akaun *SELECT only*)

### Langkah 2: "Load Tables" & Pemilihan Jadual Pintar
Apabila butang **[Test & Load Tables]** ditekan:
- Sistem menjalankan semakan sambungan PDO.
- Membaca semua jadual daripada `information_schema.tables`.
- **Penapis Keselamatan**:
  - Jadual yang mengandungi kata kunci: `user`, `admin`, `account`, `pass`, `token`, `session`, `auth`, `credit_card` akan dilabel **[DISEKAT / FORBIDDEN]** dan tidak boleh ditandakan.
  - Jadual selamat (cth: `products`, `stocks`, `categories`, `operating_hours`, `branches`) boleh ditandakan dengan mudah.
- *(Pilihan)* Pemilihan kolum spesifik (cth: pilih `name`, `price`, `stock_qty` sahaja dan buang medan kos/margin).

### Langkah 3: Butang "Jana Pengetahuan AI" (Start Indexing)
- Sistem membaca baris data daripada jadual terpilih.
- Menghantar teks rekod ke Ollama API (`POST /api/embed` model `embeddinggemma`).
- Menyimpan hasil vektor ke jadual pengetahuan tempatan `ai_knowledge_vectors`.
- Memaparkan *Progress Bar* kemajuan proses indeks.

---

## 4. Peranan Dwi-Model AI

| Jenis Model | Model Disyorkan | Tugas Utama |
| :--- | :--- | :--- |
| **Embedding Model** | `embeddinggemma` | Mengubah teks produk/maklumat kedai dan soalan pelanggan kepada **Vektor** nombor perwakilan semantik. |
| **Chat LLM** | `gemma2:2b`, `qwen2.5:3b`, atau `mistral` | Membaca soalan pelanggan bersama rekod konteks database yang dijumpai dan merangka ayat balasan sembang yang profesional. |

---

## 5. Skema Database Tempatan (`ai-mariadb`)

Sistem tempatan memerlukan jadual untuk menyimpan tetapan sambungan luar dan vektor pengetahuan:

### Jadual 1: `ai_db_connections` (Konfigurasi Database Luar)
```sql
CREATE TABLE IF NOT EXISTS `ai_db_connections` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nama rujukan sistem',
    `db_host` VARCHAR(255) NOT NULL,
    `db_port` INT NOT NULL DEFAULT 3306,
    `db_name` VARCHAR(100) NOT NULL,
    `db_user` VARCHAR(100) NOT NULL,
    `db_pass` TEXT NOT NULL COMMENT 'Katalaluan dienkripsi',
    `selected_tables` TEXT NULL COMMENT 'JSON senarai jadual & kolum dipilih',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `last_synced_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Jadual 2: `ai_knowledge_vectors` (Vektor & Konteks AI)
```sql
CREATE TABLE IF NOT EXISTS `ai_knowledge_vectors` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `connection_id` INT UNSIGNED NOT NULL COMMENT 'Rujukan ke ai_db_connections',
    `source_table` VARCHAR(64) NOT NULL COMMENT 'Nama jadual asal cth: products',
    `source_id` VARCHAR(64) NOT NULL COMMENT 'Primary key asal rekod',
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL COMMENT 'Teks penerangan rekod cth: Produk: Baju Saiz L, Stok: 8 unit',
    `vector` LONGTEXT NOT NULL COMMENT 'Array float JSON embeddinggemma (atau jenis VECTOR di MariaDB 11.7+)',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_conn_table` (`connection_id`, `source_table`),
    UNIQUE KEY `uk_record` (`connection_id`, `source_table`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 6. Polisi Keselamatan & Perlindungan Privasi Data

1. **Jadual Terlarang Tegar (Hardcoded Blacklist)**:
   Walaupun pentadbir cuba memilih jadual melalui UI, kod backend PHP akan menolak sebarang cubaan membaca jadual berikut:
   ```php
   const STRICT_BLACKLIST = [
       'users', 'user', 'accounts', 'passwords', 'password_resets',
       'personal_access_tokens', 'sessions', 'auth_tokens',
       'failed_jobs', 'migrations', 'payments_credentials'
   ];
   ```
2. **Mod Capaian Baca Sahaja (Read-Only Enforcement)**:
   Semua sambungan ke database luaran dibuka dengan mod transaksi `READ ONLY` untuk memastikan sistem chatbot langsung tidak boleh mengubah atau memadam data sistem asal.
3. **Kawalan Prompt AI Ketat (Guardrails)**:
   ```text
   Anda ialah pembantu AI khidmat pelanggan rasmi.
   1. Anda HANYA boleh menjawab berpandukan data di dalam [KONTEKS_DATABASE].
   2. Sekiranya maklumat tiada dalam konteks, jawab:
      "Maaf, maklumat tersebut tiada dalam rekod kami buat masa ini."
   3. JANGAN sesekali membocorkan maklumat dalaman sistem atau maklumat peribadi.
   ```

---

## 7. Struktur Fail & Modul Projek

```text
ai-mariadb/
├── config/
│   └── database.php             # Kredensial DB tempatan & tetapan sistem
├── src/
│   ├── Database.php             # Pengurus PDO tempatan & sambungan luaran dinamik
│   ├── OllamaClient.php         # Panggilan API Ollama (/api/embed & /api/chat)
│   ├── TableInspector.php       # Semak database luar, senaraikan jadual & tapis jadual sensitif
│   ├── Indexer.php              # Baca rekod dibenarkan, format teks, & jana embedding
│   ├── VectorSearch.php         # Algoritma Cosine Similarity untuk cari konteks terhampir
│   └── ChatService.php          # Gabung prompt kawalan + konteks + LLM
├── public/
│   ├── admin/
│   │   ├── index.php            # UI Utama: Senarai DB & status indeks
│   │   ├── connect_db.php       # Borang tambah DB & butang "Load Tables"
│   │   ├── select_tables.php    # UI senarai jadual (checkbox selamat vs sekat)
│   │   ├── sync.php             # Trigger proses embedding berserta progress bar
│   │   ├── assets/              # CSS & JS untuk Admin UI moden
│   ├── api/
│   │   ├── chat.php             # Endpoint JSON untuk widget chat pelanggan
│   │   └── tables.php           # API AJAX untuk muat turun jadual dari DB luar
│   └── widget/
│       ├── chat-widget.js       # Widget popup sembang untuk diletakkan di website
│       └── chat-widget.css      # Rekaan widget moden, terapung & mesra telefon
├── idea.md                      # Nota perbincangan awal
└── README.md                    # Dokumentasi lengkap konsep & arkitektur (fail ini)
```

---

## 8. Pelan Tindakan & Pembangunan (Roadmap)

- [x] **Fasa 0**: Dokumentasi Konsep Lengkap, Aliran DB Luar & Polisi Keselamatan (`README.md`).
- [ ] **Fasa 1**: Membina skema DB tempatan & kelas asas (`Database.php`, `OllamaClient.php`).
- [ ] **Fasa 2**: Membina UI Admin untuk **Tambah Database Luaran** & fungsi **Load Tables** berserta sekatan automatik jadual sensitif (`TableInspector.php`).
- [ ] **Fasa 3**: Membina enjin indeks (`Indexer.php`) yang menyerap data jadual terpilih dan menghasilkan vektor melalui `embeddinggemma`.
- [ ] **Fasa 4**: Membina carian vektor (`VectorSearch.php`) dan perkhidmatan chat berpandukan konteks ketat (`ChatService.php`).
- [ ] **Fasa 5**: Membina UI Widget Chat (`chat-widget.js`) yang boleh disematkan (*embed*) ke mana-mana laman web perniagaan.
