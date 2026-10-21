#!/usr/bin/env bash
# Genera un certificado HTTPS de desarrollo para la IP de esta PC (y localhost).
# Crea una "autoridad" propia (dev-ca.crt) que se instala UNA VEZ en la tablet o
# el celular para que confíe en el sitio; el certificado del servidor (dev.crt)
# la usa de respaldo. Uso: scripts/generar-certificado-dev.sh 172.20.21.84
set -euo pipefail
# Git Bash en Windows convierte "/CN=..." en una ruta: se desactiva
export MSYS_NO_PATHCONV=1

IP="${1:?Indica la IP de esta PC, ej: scripts/generar-certificado-dev.sh 172.20.21.84}"
DIR="$(cd "$(dirname "$0")/.." && pwd)/storage/ssl"
mkdir -p "$DIR"
cd "$DIR"

openssl genrsa -out dev-ca.key 2048
openssl req -x509 -new -nodes -key dev-ca.key -sha256 -days 825 \
  -subj "/CN=Hiperfaz Dev CA" -out dev-ca.crt

openssl genrsa -out dev.key 2048
openssl req -new -key dev.key -subj "/CN=$IP" -out dev.csr
cat > dev.ext <<EXT
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage=digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectAltName=@alt
[alt]
IP.1=$IP
IP.2=127.0.0.1
DNS.1=localhost
EXT
openssl x509 -req -in dev.csr -CA dev-ca.crt -CAkey dev-ca.key -CAcreateserial \
  -out dev.crt -days 825 -sha256 -extfile dev.ext
rm -f dev.csr dev.ext dev-ca.srl

echo "Listo en storage/ssl: dev.crt / dev.key (servidor) y dev-ca.crt (instalar en la tablet)."
