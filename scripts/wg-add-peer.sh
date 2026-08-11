#!/bin/bash
# Tambah peer WireGuard baru secara live + tulis permanen ke wg0.conf
# Usage: wg-add-peer.sh <public_key> <allowed_ip/32>

set -e

WG_IFACE="wg0"
WG_CONF="/etc/wireguard/wg0.conf"

PUBKEY="$1"
ALLOWED_IP="$2"

if [ -z "$PUBKEY" ] || [ -z "$ALLOWED_IP" ]; then
    echo "Usage: $0 <public_key> <allowed_ip/32>"
    exit 1
fi

# Tambah peer secara live (langsung aktif tanpa restart)
wg set "$WG_IFACE" peer "$PUBKEY" allowed-ips "$ALLOWED_IP"

# Tulis juga ke wg0.conf supaya persist setelah reboot
{
    echo ""
    echo "[Peer]"
    echo "PublicKey = $PUBKEY"
    echo "AllowedIPs = $ALLOWED_IP"
} >> "$WG_CONF"

echo "OK: peer $PUBKEY ditambahkan dengan IP $ALLOWED_IP"
