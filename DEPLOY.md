# 🔗 Interkonek — Panduan Deploy ke VPS

## Prasyarat VPS (Ubuntu 20.04/22.04)

```bash
# 1. Update sistem
sudo apt update && sudo apt upgrade -y

# 2. Install WireGuard + tools
sudo apt install -y wireguard wireguard-tools

# 3. Install PHP + SQLite
sudo apt install -y php php-sqlite3 php-cli

# 4. Install Nginx
sudo apt install -y nginx

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

### 1. Clone dari GitHub

```bash
cd /var/www
sudo git clone https://github.com/USERNAME/interkonek-app.git interkonek
sudo chown -R www-data:www-data /var/www/interkonek
```

### 2. Konfigurasi Aplikasi

```bash
# Salin template config
sudo cp /var/www/interkonek/includes/config.example.php \
        /var/www/interkonek/includes/config.php

# Edit config dengan nilai yang benar
sudo nano /var/www/interkonek/includes/config.php
```

Nilai yang perlu diisi di `config.php`:
- `WG_SERVER_PUBKEY` → dari `cat /etc/wireguard/server_public.key`
- `WG_SERVER_ENDPOINT` → IP publik VPS + port (misal `202.10.48.191:51820`)
- `AUTH_PASSWORD` → password login dashboard yang aman

### 3. Buat Folder Data

```bash
sudo mkdir -p /var/www/interkonek/data
sudo chown www-data:www-data /var/www/interkonek/data
sudo chmod 770 /var/www/interkonek/data
```

### 4. Install Script Privileged (wg-add-peer, wg-remove-peer)

```bash
# Salin scripts ke /usr/local/bin
sudo cp /var/www/interkonek/scripts/wg-add-peer.sh   /usr/local/bin/
sudo cp /var/www/interkonek/scripts/wg-remove-peer.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/wg-add-peer.sh
sudo chmod +x /usr/local/bin/wg-remove-peer.sh
```

### 5. Setup Sudoers (izinkan www-data jalankan script WG tanpa password)

```bash
sudo visudo
```

Tambahkan baris ini di bagian bawah:
```
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/wg-add-peer.sh
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/wg-remove-peer.sh
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg show wg0 dump
```

### 6. Konfigurasi Nginx

```bash
sudo nano /etc/nginx/sites-available/interkonek
```

```nginx
server {
    listen 80;
    server_name YOUR_DOMAIN_OR_IP;

    root /var/www/interkonek;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }

    # Blokir akses ke file sensitif
    location ~ /\.(git|env|gitignore) { deny all; }
    location ~ /data/ { deny all; }
    location ~ /scripts/ { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/interkonek /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## Update Aplikasi (dari GitHub)

```bash
cd /var/www/interkonek
sudo git pull origin main
sudo chown -R www-data:www-data .
```

> **⚠️ PENTING:** File `includes/config.php` dan folder `data/` tidak masuk git (ada di `.gitignore`).
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
