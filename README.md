# Kolai Plugin

Kolai API entegrasyonu icin WordPress plugin'i.

## API Genel

Base URL: `https://your-site.com/wp-json/kolai/v1`

Tumu JSON request/response kullanir.

### Base Response

Tum endpoint'ler asagidaki formatta doner:

```json
{
  "status": "success",
  "systemTime": "2026-02-04T10:15:30+00:00",
  "errorCode": null,
  "errorMessage": null,
  "woocommerceVersion": "9.1.0",
  "wordpressVersion": "6.5.3",
  "phpVersion": "8.1.20",
  "data": {}
}
```

`status` degeri:
- `success`: HTTP status < 400
- `failure`: HTTP status >= 400

### Error Codes

#### 1xxx - Kolai Plugin Errors
- `1000` Internal error
- `1001` Bad request
- `1002` Not found
- `1003` Service unavailable
- `1004` WooCommerce inactive
- `1005` Unauthorized

#### 2xxx - Product Errors
- `2000` Invalid product id
- `2001` Product not found
- `2002` Product not visible
- `2003` Variation parent not found
- `2004` Invalid product list

#### 3xxx - Shipping Errors
- `3000` Invalid address
- `3001` No shipping options

#### 4xxx - Order Errors
- `4000` Invalid order request
- `4001` Invalid shipment option
- `4002` Insufficient stock
- `4003` Discount exceeds total

#### 5xxx - Contract Errors
- `5000` Invalid contract type
- `5001` Invalid contract request
- `5002` Contract not found

#### 6xxx - Review Errors
- `6000` Invalid review request
- `6001` Review not found
- `6003` Invalid rating
- `6004` Reviews disabled

## Endpoints

Detayli istek/yanit ornekleri ve aciklamalar icin ilgili dokümana gidin:

| Alan       | Doküman      | Özet |
|-----------|--------------|------|
| **Kimlik Dogrulama** | [AUTH.md](AUTH.md) | HMAC-SHA256 imza dogrulamasi, scope eslemesi |
| **Ürün**  | [PRODUCT.md](PRODUCT.md)  | `GET /products` (sayfalama + ID/modified_after filtresi, pagination response body içinde), `GET /products/{id}`, `GET /products-with-variants/{id}` |
| **Kargo** | [SHIPPING.md](SHIPPING.md) | `POST /shipment-options` (alias: `POST /shipping-options`) |
| **Sipariş** | [ORDER.md](ORDER.md)   | `GET /order-types`, `POST /orders`, `GET /orders/{orderId}`, `PATCH /orders/{orderId}` |
| **Sözleşme** | [CONTRACT.md](CONTRACT.md) | `POST /contracts` ve `GET /contracts/clarification-text` |
| **Yorum** | [REVIEW.md](REVIEW.md) | `GET /products/{id}/reviews` (sayfalama + status/rating/modified_after filtresi), `GET /reviews/{id}` |
| **İade / İptal** | [REFUND.md](REFUND.md) | WooCommerce iade/iptal işlemlerinin iyzico'ya otomatik iletilmesi (iyzipay-php) |
| **Loglama** | [LOGS.md](LOGS.md) | Yönetici panelinden açılıp kapatılabilen, DB tabanlı yapısal log altyapısı |

## Yönetim Sayfaları

| Sayfa | Yol | İçerik |
|---|---|---|
| Ayarlar | WP Admin → Kolai | API Key / Secret Key, Aydinlatma Metni sayfası, iyzico iade/iptal API bilgileri (key/secret/ortam) |
| Sözleşmeler | WP Admin → Kolai → Sozlesmeler | Satıcı bilgileri, mesafeli satış / ön bilgilendirme metinleri |
| Loglar | WP Admin → Kolai → Loglar | Log tutma anahtarı, seviye, retention; canlı log tablosu (filtre + temizle) — bkz. [LOGS.md](LOGS.md) |

## Yapı

```
kolai/
├── admin/
│   ├── class-kolai-admin.php
│   ├── class-kolai-settings.php
│   ├── css/
│   │   └── kolai-admin.css
│   ├── js/
│   │   ├── kolai-admin.js
│   │   └── kolai-logs.js
│   └── views/
│       ├── contracts-page.php
│       ├── logs-page.php
│       └── settings-page.php
├── includes/
│   ├── class-kolai-activator.php
│   ├── class-kolai-address.php
│   ├── class-kolai-api.php
│   ├── class-kolai-auth.php
│   ├── class-kolai-constants.php
│   ├── class-kolai-core.php
│   ├── class-kolai-deactivator.php
│   ├── class-kolai-exceptions.php
│   ├── class-kolai-loader.php
│   ├── class-kolai-logger.php
│   ├── class-kolai-response.php
│   ├── class-kolai-route-base.php
│   ├── contract/
│   │   ├── contract-routes.php
│   │   └── contract-service.php
│   ├── product/
│   │   ├── product-mapper.php
│   │   ├── product-routes.php
│   │   └── product-service.php
│   ├── order/
│   │   ├── order-routes.php
│   │   └── order-service.php
│   ├── payment/
│   │   ├── class-kolai-iyzico-client.php
│   │   ├── class-kolai-iyzico-gateway.php
│   │   └── class-kolai-refund-service.php
│   ├── shipping/
│   │   ├── shipping-routes.php
│   │   └── shipping-service.php
│   ├── review/
│   │   ├── review-mapper.php
│   │   ├── review-routes.php
│   │   └── review-service.php
│   └── vendor/
│       └── iyzipay-php/        # bundled iyzico PHP SDK
├── kolai.php
├── README.md
├── AUTH.md
├── PRODUCT.md
├── SHIPPING.md
├── ORDER.md
├── CONTRACT.md
├── REVIEW.md
├── REFUND.md
└── LOGS.md
```

## Sürüm Notları

### 1.4.0
- **iyzico İade / İptal entegrasyonu**: WooCommerce yönetici panelinden yapılan iade ve iptal işlemleri otomatik olarak iyzico'ya iletilir. Detay [REFUND.md](REFUND.md).
- iyzipay-php SDK `includes/vendor/iyzipay-php/` altında bundle edildi.
- İade, `kolai-app` ödeme geçidi üzerinden native "İade et" butonu ile çalışır (`process_refund`); tutar kayıtlı `itemTransactions` kalemlerine dağıtılır. İptal, `woocommerce_order_status_cancelled` hook'u ile `paymentId` üzerinden yapılır.
- Ayarlara iyzico API Key / Secret Key / Ortam (sandbox-production) alanları eklendi.

### 1.3.0
- **PATCH /orders/{orderId}** artık opsiyonel `paymentId` ve `itemTransactions` alanlarını sipariş meta'sı olarak kaydeder (`kolai_payment_id`, `kolai_item_transactions`). Detay [ORDER.md](ORDER.md).

### 1.2.0
- **Yorumlar / Değerlendirmeler**: `GET /products/{id}/reviews` (sayfalama + status/rating/modified_after filtresi) ve `GET /reviews/{id}` endpoint'leri eklendi. Default `approved` yorumlar döner. Detay [REVIEW.md](REVIEW.md).
- Yeni scope'lar: `RETRIEVE_REVIEWS`, `RETRIEVE_REVIEW`.
- Yeni hata kodları: `6000-6004` (review).
- PII alanları (author email/IP/agent) yanıttan kasıtlı olarak çıkarıldı.

### 1.1.1
- **HTTP/2 fix**: `/products` listesindeki pagination metadatası özel `X-Kolai-*` response header'larından response body'ye (`pagination` alanı) taşındı. Bazı proxy yığınları (Cloudflare strict, HTTP/2) custom header'ları "Header field must only have a single value" protocol error'u ile reddediyordu.
- **DB migration**: Plugin sürümü değiştiğinde (FTP/Git ile dosya güncelleme dahil) log tablosu otomatik oluşur — eklenti deaktive + tekrar aktive edilmesi gerekmez.
- **Logger guard**: Tablo yoksa `Kolai_Logger::is_enabled()` false döner; yazım denemeleri sessizce skip edilir.

### 1.1.0
- **Logging**: DB tabanlı yapısal log altyapısı eklendi. Yönetim sayfası, seviye/retention ayarları, günlük cleanup cron — detay [LOGS.md](LOGS.md).
- **Product list optimizasyonu**: `/products` endpoint'i artık zorunlu olarak sayfalanır (`page`, `per_page`, max 200). Liste yanıtı hafifletildi (variations/gallery/attributes detay endpoint'inde döner). Bulk cache priming + batch term fetch ile N+1 sorguları kaldırıldı. `?ids=` ve `?modified_after=` filtreleri eklendi. Detay [PRODUCT.md](PRODUCT.md).
- Variations için tek üründe `MAX_VARIATIONS_PER_PRODUCT = 100` tavanı.
- Auth, request ve servis katmanlarına structured log noktaları eklendi.

### 1.0.3
- Önceki kararlı sürüm.
