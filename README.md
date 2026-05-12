# AYU Wallet (Web Wallet + JSON-RPC)

Sistem ini ialah web wallet untuk blockchain jenis “Bitcoin-alike” (traditional JSON-RPC wallet). Ia berhubung terus dengan node melalui JSON-RPC (contoh: `getbalance`, `listtransactions`, `getnewaddress`, `sendfrom`).

Rangkaian menyokong PoS (Proof of Stake) dan paparan “stake balance” wujud, tetapi sistem web ini **tidak menjalankan staking** (tiada fungsi stake/claim/coin control untuk staking).

## Komponen

- **Frontend/Views**: PHP + Bootstrap (UI wallet pengguna)
- **Admin Panel**: pengurusan node status, users, transaksi, API keys, dan All Wallet
- **RPC Client**: `classes/Client.php` (user), `classes/AdminClient.php` (admin)
- **API**: `/api/` untuk integrasi sistem luar (ber-signature HMAC + nonce)
- **Database (MySQL)**: users + profiles + API clients/nonces

## Wallet Types (Label/Account)

Sistem guna konsep “account/label” dalam wallet RPC:

- `Semasa_id_{userId}` = Current
- `Simpanan_id_{userId}` = Saving
- `Stake_id_{userId}` = Stake (paparan sahaja, bukan staking automation)
- `Outside_id_{userId}` = Outside (wallet/address yang datang dari integrasi API / sistem luar)

Nota legacy:
- Ada address yang pernah disimpan di bawah account = `username` (contoh `mohdsaidy`) oleh sistem luar. Admin boleh migrate ke `Outside_id_{userId}` melalui menu **All Wallet**.

## Keperluan

- PHP (dengan `curl` + `openssl`)
- MySQL / MariaDB
- Node blockchain yang expose JSON-RPC (HTTP basic auth)
- Composer dependencies (untuk Google OAuth) jika guna login Google

## Konfigurasi (selamat untuk GitHub)

Fail [config/config.php](config/config.php) **tidak simpan secret hardcoded**. Set melalui environment variables:

```bash
export AYU_BASE_URL="http://localhost/AYU/"

export AYU_DB_HOST="localhost"
export AYU_DB_USER="..."
export AYU_DB_PASS="..."
export AYU_DB_NAME="..."

export AYU_RPC_HOST="127.0.0.1"
export AYU_RPC_PORT="32720"
export AYU_RPC_USER="..."
export AYU_RPC_PASS="..."

export AYU_GOOGLE_CLIENT_ID="..."
export AYU_GOOGLE_CLIENT_SECRET="..."
```

Opsyen: jika wujud `config/config.local.php`, ia akan di-load dahulu untuk override (untuk server production). Fail ini patut di-ignore dalam Git.

## Setup Database

Minimum tables yang digunakan:

- `users`
- `user_profiles`

Admin/API akan auto-create jika tiada:

- `api_clients` (API keys)
- `api_nonces` (anti replay)

## Admin Panel

URL:

- `.../admin/`

Menu penting:

- **API Keys**: generate UUID + SecretKey, enable/disable, rotate secret, dokumentasi signature
- **All Wallet**: paparkan semua address dalam RPC (`listaddressgroupings`) dan “Related” kepada user (Semasa/Simpanan/Stake/Outside)
  - Boleh assign address kepada `Outside_id_{userId}`
  - Wallet yang sudah berada dalam label `*_id_{userId}` akan **Locked** (action disable) untuk elak pertukaran akaun tidak sah

## API Integrasi (`/api/`)

Endpoint contoh:

- `GET /AYU/api/?action=balance&label=Outside_id_10`
- `GET /AYU/api/?action=new_address&label=Outside_id_10`
- `POST /AYU/api/` dengan JSON `{"action":"send", "fromLabel":"Outside_id_10", ...}`

### Auth Headers (wajib)

- `X-API-UUID`
- `X-API-TS` (unix timestamp seconds)
- `X-API-NONCE` (unique setiap request)
- `X-API-SIGN` (HMAC-SHA256)

### Canonical String

```
ts + "\n" + nonce + "\n" + METHOD + "\n" + PATH + "\n" + CANONICAL_QUERY + "\n" + SHA256(BODY)
```

`X-API-SIGN = HMAC_SHA256(canonical, secretKey)`

Nota label:
- Jika sistem luar hantar `label=username`, API akan auto-map ke `Outside_id_{userId}` dan balance akan campur (label + legacy username) supaya kiraan betul.

## Keselamatan

- Session hardening + regenerate session ID selepas login
- CSRF token untuk action POST admin
- API guna signature + nonce + expiry (anti replay)
- Admin “All Wallet” lock action untuk label yang sudah assigned

## Had Sistem

- PoS disokong oleh chain tetapi web wallet ini tidak jalankan staking.
- Integrasi “account/label” ikut kemampuan wallet RPC; beberapa method (contoh `setaccount`) bergantung kepada implementasi node.

## Lesen

Sila rujuk fail lesen dalam repository ini (jika disediakan) sebelum digunakan untuk production/commercial.
