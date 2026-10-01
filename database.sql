-- Tabel Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'pimpinan', 'staf', 'pending') NOT NULL DEFAULT 'pending',
    telegram_chat_id VARCHAR(50) DEFAULT NULL
);

-- Tabel Tasks (sebagai perantara bot & tracking waktu)
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_tugas VARCHAR(50) NULL,
    sender_id INT NOT NULL, -- Contoh: Pimpinan
    receiver_id INT NOT NULL, -- Contoh: Staf
    kategori ENUM('P1', 'P2', 'P3') DEFAULT 'P3',
    lokasi VARCHAR(255) NULL,
    sla_konfirmasi VARCHAR(50) NULL,
    pesan_teks TEXT,
    file_url VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'on_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);
