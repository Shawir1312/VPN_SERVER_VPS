# 🔗 Interkonek

**Dashboard web untuk manajemen interkoneksi WireGuard antara VPS dan banyak router MikroTik.**

VPS bertindak sebagai **hub sentral** (WireGuard server), setiap MikroTik adalah **peer/spoke** yang terhubung ke VPS melalui tunnel terenkripsi. Dari satu dashboard, kamu bisa menambah router, memantau status tunnel secara realtime, dan mendapatkan config RouterOS yang siap di-paste.

---

## ✨ Fitur

| Fitur | Keterangan |
|-------|------------|
| 🔐 **Login / Auth** | Proteksi dashboard dengan username & password |
| 📡 **Dashboard Realtime** | Status tunnel update otomatis via AJAX tanpa reload halaman |
| 📊 **Stats Cards** | Total router, online, offline — sekilas pandang |
| ➕ **Tambah Router** | Auto-generate keypair WireGuard, assign IP tunnel otomatis |
| 📄 **Config RouterOS** | Perintah siap-paste untuk terminal RouterOS (WireGuard native v7+) |
| 🔍 **Detail Router** | Info lengkap: handshake, traffic, endpoint IP MikroTik |
| 🏓 **Ping Test** | Ping IP tunnel router langsung dari VPS |
| 🔌 **Test API Port** | Cek apakah port 8728 (API MikroTik) bisa diakses lewat tunnel |
| 🚪 **Port Forwarding** | Teruskan port publik VPS ke perangkat lokal di belakang router (IP kustom) |
| 📋 **Log Aktivitas** | Catat semua event: tambah router, hapus router, login |
| ⚙️ **Pengaturan** | Edit konfigurasi WireGuard server & kredensial login via UI |
| 🌙 **Dark Mode** | UI premium dengan sidebar, glassmorphism, animasi realtime |

---

## 🏗️ Arsitektur

```
Internet
   │
   ▼
┌──────────────────────────────┐
│          VPS (Hub)           │
│  ┌────────────────────────┐  │
│  │  WireGuard wg0         │  │
│  │  10.0.0.1/24           │  │
│  └────────────────────────┘  │
│  ┌────────────────────────┐  │
│  │  Dashboard Interkonek  │  │
│  │  (PHP + MySQL)         │  │
│  └────────────────────────┘  │
└──────┬──────────┬────────────┘
       │          │  WireGuard Tunnel
       ▼          ▼
  ┌─────────┐ ┌─────────┐
  │MikroTik │ │MikroTik │  ...dst
  │ 10.0.0.2│ │ 10.0.0.3│
  └─────────┘ └─────────┘
```

**Topologi: Hub-and-Spoke**
- VPS = WireGuard server (hub), IP tunnel `10.0.0.1`
- Setiap MikroTik = peer (spoke), IP tunnel `10.0.0.2`, `10.0.0.3`, dst
- Lewat tunnel, VPS bisa akses API MikroTik meski IP publik router dynamic/di-NAT

---

## 📁 Struktur File

```
interkonek-app/
├── index.php                    # Dashboard utama
├── login.php                    # Halaman login
├── logout.php                   # Handler logout
├── add_router.php               # Form tambah router baru
├── view_config.php              # Tampilkan config RouterOS
├── delete_router.php            # Hapus router
│
├── api/
│   ├── status.php               # AJAX: status live semua peer
│   └── router_action.php        # AJAX: ping & test API per router
│
├── pages/
│   ├── router_detail.php        # Detail + diagnostik per router
│   ├── logs.php                 # Log aktivitas sistem
│   └── settings.php             # Pengaturan konfigurasi
│
├── includes/
│   ├── config.php               # Konfigurasi utama (tidak di-commit ke git)
│   ├── config.example.php       # Template konfigurasi
│   ├── helpers.php              # Fungsi-fungsi utama (WG, ping, format)
│   ├── layout_header.php        # Shared sidebar + topbar
│   └── layout_footer.php        # Shared footer + JS
│
├── assets/
│   └── style.css                # Stylesheet dark mode premium
│
├── scripts/
│   ├── wg-add-peer.sh           # Script privileged tambah peer WireGuard
│   └── wg-remove-peer.sh        # Script privileged hapus peer WireGuard
│
├── schema.sql                   # Skema database MySQL
├── DEPLOY.md                    # Panduan deploy lengkap ke VPS
└── .gitignore
```

---

## ⚙️ Prasyarat

| Komponen | Versi |
|----------|-------|
| OS VPS | Ubuntu 20.04 / 22.04 |
| PHP | 7.4+ (dengan ekstensi `pdo_mysql`) |
| WireGuard | Kernel 5.6+ / paket `wireguard-tools` |
| Web Server | Nginx/Apache (via aaPanel direkomendasikan) |
| RouterOS | v7+ (WireGuard native) |

---

## 🚀 Instalasi

### 1. Upload ke aaPanel

1. Buat Website baru di aaPanel (misal: `vpn.domain.com`) dengan database **MySQL**.
2. Upload file repository ini ke direktori website Anda (misal: `/www/wwwroot/vpn.domain.com`).
3. Jalankan `install.php` dari browser untuk memandu pembuatan konfigurasi `includes/config.php` dan tabel database otomatis.

### 2. Install Script WireGuard + Sudoers

Masuk ke terminal SSH aaPanel sebagai root:

```bash
# Ubah path sesuai direktori website Anda
cp /www/wwwroot/vpn.domain.com/scripts/wg-add-peer.sh /usr/local/bin/
cp /www/wwwroot/vpn.domain.com/scripts/wg-remove-peer.sh /usr/local/bin/
chmod +x /usr/local/bin/wg-add-peer.sh /usr/local/bin/wg-remove-peer.sh
```

Tambahkan ke sudoers (`visudo`):
```
www ALL=(ALL) NOPASSWD: /usr/local/bin/wg-add-peer.sh
www ALL=(ALL) NOPASSWD: /usr/local/bin/wg-remove-peer.sh
www ALL=(ALL) NOPASSWD: /usr/bin/wg
www ALL=(ALL) NOPASSWD: /usr/bin/wg-quick
www ALL=(ALL) NOPASSWD: /usr/sbin/ip
www ALL=(ALL) NOPASSWD: /sbin/ip
www ALL=(ALL) NOPASSWD: /usr/sbin/iptables
www ALL=(ALL) NOPASSWD: /sbin/iptables
```
*(Catatan: User default web server di aaPanel adalah `www`, bukan `www-data`)*

### 3. Keamanan di aaPanel

Buka pengaturan website di aaPanel -> menu **URL rewrite**:

```nginx
# Blokir akses ke file sensitif dan folder script
location ~ /\.(git|gitignore) { deny all; }
location ~ ^/scripts/ { deny all; }
```

> Panduan deploy lengkap (WireGuard server, firewall, dsb) lihat di **[DEPLOY.md](DEPLOY.md)**

---

## 🔄 Update Aplikasi

```bash
cd /var/www/interkonek
sudo git pull origin main
sudo chown -R www-data:www-data .
```

> ✅ File `includes/config.php` tidak masuk `.gitignore`,
> sehingga konfigurasi **tidak akan tertimpa** saat `git pull`.

---

## 📸 Tampilan

| Halaman | Deskripsi |
|---------|-----------|
| **Dashboard** | Stats cards (online/offline) + tabel router dengan status realtime |
| **Tambah Router** | Form isian + auto-assign IP tunnel + generate keypair |
| **Detail Router** | Info tunnel, ping test, test port API, config RouterOS |
| **Port Forwarding** | Daftar port forward VPS -> Router beserta IP target |
| **Log Aktivitas** | Riwayat semua event dengan timestamp |
| **Pengaturan** | Edit endpoint WireGuard, IP hub, kredensial login |

---

## 🔒 Keamanan

- ✅ Semua halaman dilindungi session login
- ✅ `includes/config.php` di-exclude dari git (tidak ada credentials di repo)
- ✅ Nginx dikonfigurasi blokir akses ke `scripts/`
- ✅ Input di-sanitasi dengan `htmlspecialchars()` dan `escapeshellarg()`
- ⚠️ Disarankan menggunakan HTTPS (Let's Encrypt via Certbot) sebelum production

---

## 🛠 Tech Stack

- **Backend**: PHP 8.x (vanilla, tanpa framework)
- **Database**: MySQL / MariaDB (via PDO)
- **WireGuard**: `wg show` + shell scripts via `sudo`
- **Frontend**: Vanilla JS + CSS (tanpa framework)
- **Font**: Inter + JetBrains Mono (Google Fonts)

---

## 📄 Lisensi

MIT License — bebas digunakan dan dimodifikasi.

---

> Dibuat untuk kebutuhan interkoneksi jaringan MikroTik multi-site via VPS menggunakan WireGuard.
