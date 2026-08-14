# 🔗 Interkonek — Panduan Deploy ke VPS

## Prasyarat VPS (Ubuntu 20.04/22.04)

```bash
# 1. Update sistem
sudo apt update && sudo apt upgrade -y

# 2. Install WireGuard + tools
sudo apt install -y wireguard wireguard-tools

# 3. Web Server & PHP
# Sangat disarankan menginstal aaPanel (https://www.aapanel.com)
# untuk manajemen database MySQL, PHP, dan Web Server (Nginx/Apache) dengan mudah.

# 5. Install Git
sudo apt install -y git
```

---

## Setup WireGuard Server

```bash
# Generate keypair server
cd /etc/wireguard
wg genkey | sudo tee server_private.key | wg pubkey | sudo tee server_public.key
sudo chmod 600 /etc/wireguard/server_private.key

# Catat public key (akan diisi ke config.php)
cat /etc/wireguard/server_public.key

# Buat konfigurasi WireGuard server
sudo nano /etc/wireguard/wg0.conf
```

Isi `/etc/wireguard/wg0.conf`:
```ini
[Interface]
PrivateKey = <isi dari server_private.key>
Address = 10.0.0.1/24
ListenPort = 51820
PostUp   = iptables -A FORWARD -i wg0 -j ACCEPT; iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT; iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE
```

```bash
# Aktifkan WireGuard
sudo systemctl enable --now wg-quick@wg0

# Cek status
sudo wg show
```

---

## Deploy Aplikasi

### 1. Upload ke aaPanel

1. Buka dashboard aaPanel.
2. Buat **Website** baru (contoh: `vpn.domain.com`) dan buat database **MySQL**.
3. Buka File Manager, masuk ke folder website Anda (misal: `/www/wwwroot/vpn.domain.com`).
4. Upload file-file aplikasi Interkonek ini ke dalam folder tersebut.
5. Akses domain website Anda di browser. Web Installer akan memandu Anda untuk mengisi kredensial database MySQL dan informasi WireGuard.

### 2. Install Script Privileged (wg-add-peer, wg-remove-peer)

Script ini dibutuhkan agar web PHP bisa menambah/menghapus peer WireGuard secara aman.

```bash
# Pastikan Anda masuk SSH sebagai root
# Ganti /www/wwwroot/vpn.domain.com dengan folder website Anda
cd /www/wwwroot/vpn.domain.com

sudo cp scripts/wg-add-peer.sh /usr/local/bin/
sudo cp scripts/wg-remove-peer.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/wg-add-peer.sh
sudo chmod +x /usr/local/bin/wg-remove-peer.sh
```

### 3. Setup Sudoers (izinkan web server jalankan script WG)

Buka konfigurasi sudoers:
```bash
sudo visudo
```

Tambahkan baris ini di bagian paling bawah. **Penting**: aaPanel menggunakan user `www` (bukan `www-data`):
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

### 4. Amankan URL (URL Rewrite)

Agar file script dan `.git` tidak bisa di-download oleh publik, tambahkan baris berikut di menu **URL rewrite** pada pengaturan Website di aaPanel Anda:

```nginx
# Blokir akses ke file sensitif
location ~ /\.(git|env|gitignore) { deny all; }
location ~ ^/scripts/ { deny all; }
```

---

## Update Aplikasi (dari GitHub)

```bash
cd /var/www/interkonek
sudo git pull origin main
sudo chown -R www-data:www-data .
```

> **⚠️ PENTING:** File `includes/config.php` tidak masuk git (ada di `.gitignore`).
> Jadi config dan database di VPS **tidak akan tertimpa** saat `git pull`.

---

## Firewall

```bash
# Buka port WireGuard
sudo ufw allow 51820/udp

# Buka port HTTP (dan HTTPS kalau pakai SSL)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

sudo ufw enable
```

---

## Verifikasi

1. Buka browser ke `http://IP_VPS/`
2. Login dengan username dan password di `config.php`
3. Tambah router baru → salin config ke RouterOS MikroTik
4. Tunggu 30 detik → status berubah **Connected** ✅
